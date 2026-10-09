<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Models\EventBooking;
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

        // 1. Save booking to database
        $booking = EventBooking::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'] ?? null,
            'phone' => $validated['phone'],
            'email' => $validated['email'],
            'event_type' => $validated['event_type'],
            'location' => $validated['location'],
            'event_date' => $validated['date'] ?? null,
            'guests' => $validated['guests'] ?? null,
            'message' => $validated['message'] ?? null,
            'locale' => $locale,
            'status' => 'new',
            'ip_address' => $request->ip(),
        ]);

        // 2. Fetch configured Email Template from Admin
        $template = EmailTemplate::where('event_key', 'event_booking')
            ->where('is_active', true)
            ->first();

        if ($template) {
            $shortcodes = [
                'client_name' => $fullName,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'] ?? '',
                'phone' => $validated['phone'],
                'email' => $validated['email'],
                'event_type' => $validated['event_type'],
                'event_date' => $validated['date'] ?? 'To be scheduled',
                'location' => $validated['location'],
                'guests' => $validated['guests'] ?? 'N/A',
                'message' => nl2br(e($validated['message'] ?? 'No additional notes provided.')),
                'submission_date' => now()->timezone('Asia/Riyadh')->format('d M Y, h:i A') . ' (KSA)',
            ];

            $rendered = $template->render($shortcodes, $locale);

            // Determine recipient emails configured in admin template
            $recipientSetting = $template->notification_emails ?: config('mail.from.address', 'info@grassflorist.com');
            $recipients = array_values(array_filter(array_map('trim', explode(',', $recipientSetting))));
            if (empty($recipients)) {
                $recipients = ['info@grassflorist.com'];
            }

            // A. Send notification to Admin recipients
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

            // B. If recipient_type is 'both', also send customer confirmation email
            if ($template->recipient_type === 'both' && filter_var($validated['email'], FILTER_VALIDATE_EMAIL)) {
                try {
                    $customerSubject = $locale === 'ar'
                        ? 'تأكيد استلام طلب استشارة المناسبة - غراس فلوريست'
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
