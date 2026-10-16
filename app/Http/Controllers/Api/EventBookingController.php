<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Models\EventBooking;
use App\Services\EventBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class EventBookingController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $input = [
            'first_name' => $request->input('first_name') ?: $request->input('firstName'),
            'last_name' => $request->input('last_name') ?: $request->input('lastName'),
            'phone' => $request->input('phone'),
            'email' => $request->input('email'),
            'event_type' => $request->input('event_type') ?: $request->input('eventType'),
            'location' => $request->input('location'),
            'date' => $request->input('date') ?: $request->input('eventDate'),
            'guests' => $request->input('guests'),
            'message' => $request->input('message'),
            'locale' => $request->input('locale'),
        ];

        $validator = Validator::make($input, [
            'first_name' => 'required|string|max:190',
            'last_name' => 'nullable|string|max:190',
            'phone' => 'required|string|max:50',
            'email' => 'required|email|max:190',
            'event_type' => 'required|string|max:190',
            'location' => 'required|string|max:255',
            'date' => 'nullable|string|max:100',
            'guests' => 'nullable|string|max:100',
            'message' => 'nullable|string|max:5000',
            'locale' => 'nullable|string|in:en,ar',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $validated = $validator->validated();
        $locale = $validated['locale'] ?? ($request->header('X-Locale') === 'ar' ? 'ar' : 'en');
        $fullName = trim(($validated['first_name'] ?? '') . ' ' . ($validated['last_name'] ?? ''));

        // 1. Process Guest Count: ONLY applicable for Hall & House weddings
        $isGuestsApplicable = EventBookingService::isGuestsApplicable($validated['event_type']);
        $guests = $isGuestsApplicable ? (trim((string)($validated['guests'] ?? '')) ?: null) : null;

        // 2. Extract structured dynamic conditional fields
        $customFields = EventBookingService::extractCustomFields($validated['event_type'], $request->all());

        // 3. Extract clean user handwritten message (excluding frontend appended text)
        $cleanMessage = EventBookingService::extractCleanMessage($validated['message']);

        // 4. Save booking to database with structured custom fields
        $booking = EventBooking::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'] ?? null,
            'phone' => $validated['phone'],
            'email' => $validated['email'],
            'event_type' => $validated['event_type'],
            'location' => $validated['location'],
            'event_date' => $validated['date'] ?? null,
            'guests' => $guests,
            'custom_fields' => !empty($customFields) ? $customFields : null,
            'message' => $cleanMessage,
            'locale' => $locale,
            'status' => 'new',
            'ip_address' => $request->ip(),
        ]);

        // 5. Fetch configured Email Template from Admin
        $template = EmailTemplate::where('event_key', 'event_booking')
            ->where('is_active', true)
            ->first();

        if ($template) {
            $localizedEventType = EventBookingService::getLocalizedEventType($validated['event_type'], $locale);
            $customDetailsHtml = EventBookingService::formatCustomDetailsHtml($validated['event_type'], $customFields, $locale);
            $guestCountRow = EventBookingService::formatGuestCountRow($guests, $locale);

            $notesDisplay = !empty($cleanMessage)
                ? nl2br(e($cleanMessage))
                : ($locale === 'ar' ? 'لا توجد ملاحظات إضافية من العميل.' : 'No additional notes provided.');

            $shortcodes = [
                'client_name' => $fullName,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'] ?? '',
                'phone' => $validated['phone'],
                'email' => $validated['email'],
                'event_type' => $localizedEventType,
                'event_date' => $validated['date'] ?? ($locale === 'ar' ? 'سيتم التحديد لاحقاً' : 'To be scheduled'),
                'location' => $validated['location'],
                'guests' => $guests ?? ($locale === 'ar' ? 'غير محدد' : 'N/A'),
                'guest_count_row' => $guestCountRow,
                'custom_details_rows' => $customDetailsHtml,
                'message' => $notesDisplay,
                'submission_date' => now()->timezone('Asia/Riyadh')->format('d M Y, h:i A') . ' (KSA)',
            ];

            $rendered = $template->render($shortcodes, $locale);
            $body = $rendered['body'];

            // Fail-safe cleanup: If guest count is NOT applicable, strip any legacy hardcoded guest count table row
            if (!$isGuestsApplicable || empty($guests)) {
                $body = preg_replace('/<tr[^>]*>\s*<td[^>]*>(?:Guest Count|عدد الضيوف المتوقع)<\/td>[\s\S]*?<\/tr>/ui', '', $body);
            }

            // Fail-safe injection: If template did not have {custom_details_rows} placeholder, inject it right after location row
            if (!str_contains($template->body_en, '{custom_details_rows}') && !empty($customDetailsHtml)) {
                $body = preg_replace(
                    '/(<tr[^>]*>\s*<td[^>]*>(?:Venue \/ Location|موقع \/ قاعة المناسبة)<\/td>[\s\S]*?<\/tr>)/ui',
                    "$1\n" . $customDetailsHtml,
                    $body,
                    1
                );
            }

            $rendered['body'] = $body;

            // A. Send notification to Admin recipients ONLY if configured in the template
            if (!empty(trim((string)$template->notification_emails))) {
                $recipients = array_values(array_filter(array_map('trim', explode(',', $template->notification_emails))));
                if (!empty($recipients)) {
                    try {
                        Mail::html($rendered['body'], function ($mail) use ($recipients, $rendered, $validated, $fullName) {
                            $mail->to($recipients)
                                ->replyTo($validated['email'], $fullName)
                                ->subject($rendered['subject']);
                        });
                    } catch (\Throwable $e) {
                        Log::error('[EventBooking] Failed to dispatch admin notification email: ' . $e->getMessage(), [
                            'booking_id' => $booking->id,
                            'recipients' => $recipients,
                        ]);
                    }
                }
            }

            // B. If recipient_type is 'both', also send customer confirmation email
            if ($template->recipient_type === 'both' && filter_var($validated['email'], FILTER_VALIDATE_EMAIL)) {
                try {
                    $customerSubject = $locale === 'ar'
                        ? 'تأكيد استلام طلب حجز واستشارة المناسبة - غراس فلوريست'
                        : 'We have received your Event Booking Inquiry - Grass Florist';

                    Mail::html($rendered['body'], function ($mail) use ($validated, $customerSubject) {
                        $mail->to($validated['email'])
                            ->subject($customerSubject);
                    });
                } catch (\Throwable $e) {
                    Log::error('[EventBooking] Failed to dispatch customer confirmation email: ' . $e->getMessage(), [
                        'booking_id' => $booking->id,
                        'customer_email' => $validated['email'],
                    ]);
                }
            }
        } else {
            Log::warning('[EventBooking] Active event_booking EmailTemplate not found.');
        }

        return response()->json([
            'success' => true,
            'message' => $locale === 'ar'
                ? 'تم استلام طلب حجز واستشارة المناسبة بنجاح! سيتواصل معكم فريقنا قريباً.'
                : 'Thank you! Your event booking inquiry has been submitted successfully.',
            'booking_id' => $booking->id,
        ], 200);
    }
}
