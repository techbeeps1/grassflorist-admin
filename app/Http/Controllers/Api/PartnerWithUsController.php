<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Models\PartnerInquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class PartnerWithUsController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'country' => 'required|string|max:190',
            'city' => 'required|string|max:190',
            'company_name' => 'required|string|max:190',
            'website' => 'nullable|string|max:255',
            'category' => 'required|string|max:190',
            'social_media' => 'nullable|string|max:255',
            'first_name' => 'required|string|max:190',
            'last_name' => 'nullable|string|max:190',
            'contact_role' => 'nullable|string|max:190',
            'email' => 'required|email|max:190',
            'country_code' => 'nullable|string|max:20',
            'phone' => 'required|string|max:50',
            'company_profile' => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx,jpg,jpeg,png,webp|max:15360',
            'product_list' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,csv,jpg,jpeg,png,webp|max:20480',
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

        // Handle uploaded company profile file
        $profilePath = null;
        if ($request->hasFile('company_profile')) {
            $profilePath = $request->file('company_profile')->store('partners/profiles', 'public');
        }

        // Handle uploaded product list file
        $productListPath = null;
        if ($request->hasFile('product_list')) {
            $productListPath = $request->file('product_list')->store('partners/product_lists', 'public');
        }

        // 1. Save Partner Application to Database
        $partner = PartnerInquiry::create([
            'country' => $validated['country'],
            'city' => $validated['city'],
            'company_name' => $validated['company_name'],
            'website' => $validated['website'] ?? null,
            'category' => $validated['category'],
            'social_media' => $validated['social_media'] ?? null,
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'] ?? null,
            'contact_role' => $validated['contact_role'] ?? 'Partner Representative',
            'email' => $validated['email'],
            'country_code' => $validated['country_code'] ?? '+966',
            'phone' => $validated['phone'],
            'company_profile_file' => $profilePath,
            'product_list_file' => $productListPath,
            'locale' => $locale,
            'status' => 'new',
            'ip_address' => $request->ip(),
        ]);

        // 2. Fetch configured Email Template from Admin
        $template = EmailTemplate::where('event_key', 'partner_with_us')
            ->where('is_active', true)
            ->first();

        if ($template) {
            $profileUrl = $partner->company_profile_url ?: 'No profile document uploaded';
            $productListUrl = $partner->product_list_url ?: 'No product list document uploaded';

            $shortcodes = [
                'company_name' => $partner->company_name,
                'contact_name' => $fullName,
                'first_name' => $partner->first_name,
                'last_name' => $partner->last_name ?? '',
                'contact_role' => $partner->contact_role ?? 'Partner',
                'email' => $partner->email,
                'phone' => $partner->full_phone,
                'country' => $partner->country,
                'city' => $partner->city,
                'category' => $partner->category,
                'website' => $partner->website ?: 'N/A',
                'social_media' => $partner->social_media ?: 'N/A',
                'company_profile_url' => $profileUrl,
                'product_list_url' => $productListUrl,
                'submission_date' => now()->timezone('Asia/Riyadh')->format('d M Y, h:i A') . ' (KSA)',
            ];

            $rendered = $template->render($shortcodes, $locale);

            // Determine recipient emails configured in admin template
            $recipientSetting = $template->notification_emails ?: config('mail.from.address', 'info@grassflorist.com');
            $recipients = array_values(array_filter(array_map('trim', explode(',', $recipientSetting))));
            if (empty($recipients)) {
                $recipients = ['info@grassflorist.com'];
            }

            // A. Send notification email to Admin recipients
            try {
                Mail::html($rendered['body'], function ($mail) use ($recipients, $rendered, $partner, $fullName) {
                    $mail->to($recipients)
                        ->replyTo($partner->email, $fullName)
                        ->subject($rendered['subject']);
                });
            } catch (\Throwable $e) {
                Log::error('[PartnerWithUs] Failed to dispatch admin notification email: ' . $e->getMessage(), [
                    'partner_id' => $partner->id,
                    'recipients' => $recipients,
                ]);
            }

            // B. If recipient_type is 'both', also send acknowledgment email to Brand
            if ($template->recipient_type === 'both' && filter_var($partner->email, FILTER_VALIDATE_EMAIL)) {
                try {
                    $brandSubject = $locale === 'ar'
                        ? 'تأكيد استلام طلب الشراكة - غراس فلوريست'
                        : 'We have received your Brand Partnership Application - Grass Florist';

                    Mail::html($rendered['body'], function ($mail) use ($partner, $brandSubject) {
                        $mail->to($partner->email)
                            ->subject($brandSubject);
                    });
                } catch (\Throwable $e) {
                    Log::error('[PartnerWithUs] Failed to dispatch brand confirmation email: ' . $e->getMessage(), [
                        'partner_id' => $partner->id,
                        'email' => $partner->email,
                    ]);
                }
            }
        } else {
            Log::warning('[PartnerWithUs] Active partner_with_us EmailTemplate not found.');
        }

        return response()->json([
            'success' => true,
            'message' => $locale === 'ar'
                ? 'شكراً لاهتمامكم بالشراكة مع غراس فلوريست! تم استلام طلبكم بنجاح وسيتواصل معكم فريقنا قريباً.'
                : 'Thank you for your interest in partnering with Grass Florist! Your application has been received and our partnerships team will reach out soon.',
            'inquiry_id' => $partner->id,
        ], 200);
    }
}
