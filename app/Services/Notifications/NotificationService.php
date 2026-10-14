<?php

namespace App\Services\Notifications;

use App\Models\ContactInquiry;
use App\Models\ContactPage;
use App\Models\EmailTemplate;
use App\Models\GlobalSetting;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\SmsTemplate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;

class NotificationService
{
    protected MsegatSmsService $smsService;

    public function __construct(MsegatSmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * Send email using an active EmailTemplate and record in notification_logs
     */
    public function sendEmail(
        string $eventKey,
        string|array $recipients,
        array $data,
        string $locale = 'en',
        ?string $customSubject = null,
        ?string $ipAddress = null,
        ?int $orderId = null
    ): array {
        $template = EmailTemplate::where('event_key', $eventKey)->first();

        if (!$template || !$template->is_active) {
            $statusReason = !$template ? "Email template '{$eventKey}' not found in database." : "Email template '{$eventKey}' is currently marked inactive.";
            foreach ($recipientList as $email) {
                NotificationLog::create([
                    'order_id' => $orderId,
                    'channel' => 'email',
                    'event_key' => $eventKey,
                    'recipient' => $email,
                    'subject_or_sender' => $customSubject ?: "Template: {$eventKey}",
                    'content' => $statusReason,
                    'status' => 'failed',
                    'error_message' => $statusReason,
                    'response_payload' => ['template_missing' => !$template, 'is_active' => $template?->is_active ?? false],
                    'ip_address' => $ipAddress ?? request()?->ip(),
                ]);
            }

            return [
                'success' => false,
                'message' => $statusReason,
            ];
        }

        $recipientList = is_array($recipients)
            ? $recipients
            : array_values(array_filter(array_map('trim', explode(',', $recipients))));

        if (empty($recipientList)) {
            return [
                'success' => false,
                'message' => 'No recipient email addresses provided.',
            ];
        }

        $rendered = $template->render($data, $locale);
        $subject = $customSubject ?: $rendered['subject'];
        $body = $rendered['body'];

        // Wrap body inside brand luxury layout
        $fullHtml = View::make('emails.layouts.brand-email', [
            'slot' => $body,
            'subject' => $subject,
            'locale' => $locale,
        ])->render();

        $defaultMailer = config('mail.default', 'smtp');
        if ($defaultMailer === 'smtp') {
            $smtpHost = config('mail.mailers.smtp.host');
            $smtpPort = (int)config('mail.mailers.smtp.port', 587);
            if (!empty($smtpHost) && !in_array($smtpHost, ['127.0.0.1', 'localhost', '10.0.0.1'])) {
                $errno = 0;
                $errstr = '';
                $socket = @fsockopen($smtpHost, $smtpPort, $errno, $errstr, 2);
                if (!$socket) {
                    Log::warning("[NotificationService] SMTP server {$smtpHost}:{$smtpPort} probe failed ({$errstr}). Skipping email dispatch to avoid freezing workers.");
                    foreach ($recipientList as $email) {
                        NotificationLog::create([
                            'order_id' => $orderId,
                            'channel' => 'email',
                            'event_key' => $eventKey,
                            'recipient' => $email,
                            'subject_or_sender' => $subject,
                            'content' => $fullHtml,
                            'status' => 'failed',
                            'error_message' => "SMTP probe failed ({$errstr}): {$smtpHost}:{$smtpPort} not responding.",
                            'response_payload' => ['error' => 'smtp_host_unreachable'],
                            'ip_address' => $ipAddress ?? request()?->ip(),
                        ]);
                    }
                    return [
                        'success' => false,
                        'errors' => ["SMTP server {$smtpHost}:{$smtpPort} unreachable."],
                    ];
                }
                fclose($socket);
            }
        }

        $allSuccess = true;
        $errors = [];

        foreach ($recipientList as $email) {
            try {
                @ini_set('default_socket_timeout', '5');
                Mail::html($fullHtml, function ($message) use ($email, $subject, $eventKey) {
                    $message->to($email)
                        ->subject($subject);
                    $message->getHeaders()->addTextHeader('X-Grass-Logged', '1');
                    $message->getHeaders()->addTextHeader('X-Grass-Event', $eventKey);
                });

                NotificationLog::create([
                    'order_id' => $orderId,
                    'channel' => 'email',
                    'event_key' => $eventKey,
                    'recipient' => $email,
                    'subject_or_sender' => $subject,
                    'content' => $fullHtml,
                    'status' => 'sent',
                    'error_message' => null,
                    'response_payload' => ['driver' => config('mail.default')],
                    'ip_address' => $ipAddress ?? request()?->ip(),
                ]);
            } catch (\Throwable $e) {
                $allSuccess = false;
                $errors[] = "Failed to send to {$email}: " . $e->getMessage();
                Log::error("[NotificationService] Email error ({$eventKey}): " . $e->getMessage());

                NotificationLog::create([
                    'order_id' => $orderId,
                    'channel' => 'email',
                    'event_key' => $eventKey,
                    'recipient' => $email,
                    'subject_or_sender' => $subject,
                    'content' => $fullHtml,
                    'status' => 'failed',
                    'error_message' => $e->getMessage() . "\n" . $e->getTraceAsString(),
                    'response_payload' => ['exception' => $e->getMessage()],
                    'ip_address' => $ipAddress ?? request()?->ip(),
                ]);
            }
        }

        return [
            'success' => $allSuccess,
            'errors' => $errors,
        ];
    }

    /**
     * Send SMS using an active SmsTemplate via Msegat Gateway and record in notification_logs
     */
    public function sendSms(
        string $eventKey,
        string|array $recipients,
        array $data,
        string $locale = 'ar',
        ?string $ipAddress = null,
        ?int $orderId = null
    ): array {
        $template = SmsTemplate::where('event_key', $eventKey)->first();

        if (!$template || !$template->is_active) {
            return [
                'success' => false,
                'message' => "SMS template '{$eventKey}' not found or inactive.",
            ];
        }

        $message = $template->render($data, $locale);

        return $this->smsService->send($recipients, $message, $eventKey, $ipAddress, $orderId);
    }

    /**
     * Handle Contact Us inquiry notifications (Email & SMS to Admin and Customer)
     */
    public function sendContactInquiryNotification(ContactInquiry $inquiry, ?string $preferredLocale = null): void
    {
        $emailTemplate = EmailTemplate::where('event_key', 'contact_us')->first();
        $smsTemplate = SmsTemplate::where('event_key', 'contact_us')->first();

        $inquiryLocale = $preferredLocale
            ?: ($inquiry->locale ?? null)
            ?: (request()?->header('X-Locale')
            ?: (request()?->input('locale')
            ?: (request()?->input('lang')
            ?: (app()->getLocale() ?: 'ar'))));
        $locale = in_array(strtolower(substr((string)$inquiryLocale, 0, 2)), ['en', 'ar'], true) ? strtolower(substr((string)$inquiryLocale, 0, 2)) : 'ar';

        $data = [
            'customer_name' => $inquiry->name ?: 'Customer',
            'inquiry_name' => $inquiry->name ?: 'Customer',
            'inquiry_email' => $inquiry->email,
            'inquiry_phone' => $inquiry->phone ?: 'N/A',
            'inquiry_subject' => $inquiry->subject ?: 'General Inquiry',
            'inquiry_message' => $inquiry->message ?: '',
            'submission_date' => now()->format('d M Y, h:i A'),
        ];

        // 1. Dispatch Email Template
        if ($emailTemplate && $emailTemplate->is_active) {
            $recipientType = $emailTemplate->recipient_type; // customer, admin, both

            // Admin email (ONLY if explicitly configured in the template)
            if (in_array($recipientType, ['admin', 'both'], true) && !empty(trim((string)$emailTemplate->notification_emails))) {
                $this->sendEmail('contact_us', $emailTemplate->notification_emails, $data, 'en', null, $inquiry->ip_address);
            }

            // Customer confirmation auto-reply email (in customer's language)
            if (in_array($recipientType, ['customer', 'both'], true) && !empty($inquiry->email)) {
                $this->sendEmail('contact_us', $inquiry->email, $data, $locale, null, $inquiry->ip_address);
            }
        }

        // 2. Dispatch SMS Template via Msegat
        if ($smsTemplate && $smsTemplate->is_active) {
            $smsRecipientType = $smsTemplate->recipient_type;

            // Admin SMS (ONLY if explicitly configured in the template)
            if (in_array($smsRecipientType, ['admin', 'both'], true) && !empty(trim((string)$smsTemplate->notification_phones))) {
                $this->sendSms('contact_us', $smsTemplate->notification_phones, $data, 'ar', $inquiry->ip_address);
            }

            // Customer SMS (in customer's language)
            if (in_array($smsRecipientType, ['customer', 'both'], true) && !empty($inquiry->phone)) {
                $this->sendSms('contact_us', $inquiry->phone, $data, $locale, $inquiry->ip_address);
            }
        }
    }

    /**
     * Handle Order lifecycle notifications (Email & SMS to Customer and Admin)
     * Automatically dispatches to the background queue by default for fast responses.
     */
    public function sendOrderNotification(Order $order, string $eventKey, ?string $preferredLocale = null, bool $queue = true): void
    {
        if ($queue) {
            \App\Jobs\SendOrderNotificationJob::dispatch($order->id, $eventKey, $preferredLocale);
            return;
        }

        $this->sendOrderNotificationSync($order, $eventKey, $preferredLocale);
    }

    /**
     * Synchronous worker logic for sending order email & SMS notifications.
     */
    public function sendOrderNotificationSync(Order $order, string $eventKey, ?string $preferredLocale = null): void
    {
        $emailTemplate = EmailTemplate::where('event_key', $eventKey)->first();
        $smsTemplate = SmsTemplate::where('event_key', $eventKey)->first();

        if ((!$emailTemplate || !$emailTemplate->is_active) && (!$smsTemplate || !$smsTemplate->is_active)) {
            return;
        }

        // Determine customer notification language first:
        // Priority 1: explicitly passed $preferredLocale
        // Priority 2: $order->order_language saved at time of checkout
        // Priority 3: current request header X-Locale / locale / lang
        // Priority 4: app locale / fallback 'ar'
        $orderLocale = !empty($order->order_language)
            ? strtolower(substr((string)$order->order_language, 0, 2))
            : null;

        $locale = $preferredLocale
            ?: ($orderLocale
            ?: (request()?->header('X-Locale')
            ?: (request()?->input('locale')
            ?: (request()?->input('lang')
            ?: (app()->getLocale() ?: 'ar')))));

        $locale = in_array(strtolower((string)$locale), ['en', 'ar'], true) ? strtolower((string)$locale) : 'ar';

        $orderCustomer = $order->customer;
        $orderFullName = trim(($order->first_name ?? '') . ' ' . ($order->last_name ?? ''));
        $customerName = !empty($orderFullName)
            ? $orderFullName
            : (!empty($order->sender_name)
                ? trim((string)$order->sender_name)
                : (!empty($orderCustomer?->name)
                    ? trim((string)$orderCustomer->name)
                    : ($locale === 'ar' ? 'عميلنا العزيز' : 'Valued Customer')));

        $customerEmail = $order->email ?? ($orderCustomer?->email ?? null);
        $customerPhone = $order->customer_phone ?: ($order->phone ?? ($orderCustomer?->phone ?? null));


        // Resolve currency and total amount
        // Grass Florist store base currency is always Saudi Riyal (SAR / ر.س).
        $orderCurrency = strtoupper(trim((string)($order->currency ?: 'SAR')));
        $sarAmount = (float)($order->sar_amount ?: $order->total_amount);

        // If order was in SAR, or payment method was PayPal with SAR base amount:
        if ($orderCurrency === 'SAR' || empty($orderCurrency) || strtolower((string)$order->payment_method) === 'paypal') {
            $currencyLabel = ($locale === 'ar') ? 'ر.س' : 'SAR';
            $amountVal = $sarAmount;
        } else {
            // Customer explicitly ordered in USD on storefront
            $usdAmount = (float)($order->currency_amount ?? 0);
            if ($usdAmount <= 0 || $usdAmount >= $sarAmount) {
                $usdAmount = !empty($order->meta_data['usd_total'])
                    ? (float)$order->meta_data['usd_total']
                    : round($sarAmount * ((float)($order->exchange_rate ?: 0.2667)), 2);
            }
            $currencyLabel = 'USD';
            $amountVal = $usdAmount;
        }

        $totalFormatted = number_format($amountVal, 2);
        $totalWithCurrency = "{$totalFormatted} {$currencyLabel}";

        $deliveryDate = 'N/A';
        if (!empty($order->delivery_date)) {
            try {
                $deliveryDate = \Carbon\Carbon::parse($order->delivery_date)->format('Y-m-d');
            } catch (\Exception $e) {
                $deliveryDate = (string)$order->delivery_date;
            }
        }

        $deliveryTime = $order->delivery_time ?: '11:00 AM - 03:00 PM';
        $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:3000'), '/');
        $backendUrl = rtrim(config('app.url', 'http://127.0.0.1:8000'), '/');
        $langPrefix = $locale === 'en' ? '/en' : '';

        $orderIdentifier = (string)($order->order_number ?: $order->id);
        $trackingUrl = "{$frontendUrl}{$langPrefix}/account";
        $invoiceUrl = "{$backendUrl}/orders/{$orderIdentifier}/invoice?key=" . ($order->order_key ?? '') . ($locale === 'en' ? '&lang=en' : '&lang=ar');
        $retryUrl = "{$frontendUrl}{$langPrefix}/order-pay/{$orderIdentifier}";

        $data = [
            'customer_name' => $customerName,
            'order_id' => (string)($order->order_number ?: $order->id),
            'order_number' => (string)($order->order_number ?: $order->id),
            'total_amount' => $totalWithCurrency,
            'currency' => $currencyLabel,
            'payment_method' => $order->payment_method ?: 'Online Payment',
            'delivery_date' => $deliveryDate,
            'delivery_time' => $deliveryTime,
            'recipient_name' => $order->recipient_name ?: $customerName,
            'recipient_phone' => $order->recipient_phone ?: ($customerPhone ?: 'N/A'),
            'gift_message' => $order->delivery_message ?: ($order->gift_message ?: 'None'),
            'tracking_link' => $trackingUrl,
            'invoice_url' => $invoiceUrl,
            'retry_payment_url' => $retryUrl,
        ];

        // 1. Dispatch Email Template
        if ($emailTemplate && $emailTemplate->is_active) {
            $recipType = $emailTemplate->recipient_type;

            // Send to Customer in customer's order language
            if (in_array($recipType, ['customer', 'both'], true) && !empty($customerEmail)) {
                $emailRes = $this->sendEmail($eventKey, $customerEmail, $data, $locale, null, null, $order->id);
                try {
                    \App\Models\OrderActivity::log(
                        $order,
                        'email',
                        "Customer Email: {$eventKey}",
                        "Email dispatched to {$customerEmail} (" . (($emailRes['success'] ?? false) ? 'Sent' : 'Failed') . ")",
                        ['event_key' => $eventKey, 'recipient' => $customerEmail, 'success' => ($emailRes['success'] ?? false)]
                    );
                } catch (\Throwable $e) {}
            }

            // Send to Admin (ONLY if explicitly configured in the template)
            if (in_array($recipType, ['admin', 'both'], true) && !empty(trim((string)$emailTemplate->notification_emails))) {
                $adminEmailRes = $this->sendEmail($eventKey, $emailTemplate->notification_emails, $data, 'en', null, null, $order->id);
                try {
                    \App\Models\OrderActivity::log(
                        $order,
                        'email',
                        "Admin Email: {$eventKey}",
                        "Email dispatched to admin: {$emailTemplate->notification_emails} (" . (($adminEmailRes['success'] ?? false) ? 'Sent' : 'Failed') . ")",
                        ['event_key' => $eventKey, 'recipient' => $emailTemplate->notification_emails, 'success' => ($adminEmailRes['success'] ?? false)]
                    );
                } catch (\Throwable $e) {}
            }
        }

        // 2. Dispatch SMS Template via Msegat
        if ($smsTemplate && $smsTemplate->is_active) {
            $smsRecipType = $smsTemplate->recipient_type;

            // Send Customer SMS in customer's order language
            if (in_array($smsRecipType, ['customer', 'both'], true) && !empty($customerPhone)) {
                $smsRes = $this->sendSms($eventKey, $customerPhone, $data, $locale, null, $order->id);
                try {
                    \App\Models\OrderActivity::log(
                        $order,
                        'sms',
                        "Customer SMS: {$eventKey}",
                        "SMS dispatched to {$customerPhone} (" . (($smsRes['success'] ?? false) ? 'Sent' : 'Failed: ' . ($smsRes['message'] ?? '')) . ")",
                        ['event_key' => $eventKey, 'recipient' => $customerPhone, 'success' => ($smsRes['success'] ?? false)]
                    );
                } catch (\Throwable $e) {}
            }

            // Send Admin SMS (ONLY if explicitly configured in the template)
            if (in_array($smsRecipType, ['admin', 'both'], true) && !empty(trim((string)$smsTemplate->notification_phones))) {
                $adminSmsRes = $this->sendSms($eventKey, $smsTemplate->notification_phones, $data, 'ar', null, $order->id);
                try {
                    \App\Models\OrderActivity::log(
                        $order,
                        'sms',
                        "Admin SMS: {$eventKey}",
                        "SMS dispatched to admin: {$smsTemplate->notification_phones} (" . (($adminSmsRes['success'] ?? false) ? 'Sent' : 'Failed: ' . ($adminSmsRes['message'] ?? '')) . ")",
                        ['event_key' => $eventKey, 'recipient' => $smsTemplate->notification_phones, 'success' => ($adminSmsRes['success'] ?? false)]
                    );
                } catch (\Throwable $e) {}
            }
        }
    }

    /**
     * Handle New User / Customer Registration notifications (Email & SMS)
     */
    public function sendUserRegistrationNotification(\App\Models\Customer $customer, ?string $preferredLocale = null): void
    {
        $locale = $preferredLocale
            ?: (request()?->header('X-Locale')
            ?: (request()?->input('locale')
            ?: (request()?->input('lang')
            ?: (app()->getLocale() ?: 'ar'))));
        $locale = in_array(strtolower(substr((string)$locale, 0, 2)), ['en', 'ar'], true) ? strtolower(substr((string)$locale, 0, 2)) : 'ar';

        $fullName = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''));
        $customerName = !empty($fullName) ? $fullName : ($customer->username ?: 'Valued Customer');
        $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:3000'), '/');
        $langPrefix = $locale === 'en' ? '/en' : '';

        $data = [
            'customer_name' => $customerName,
            'first_name' => $customer->first_name ?: $customerName,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone ?: 'N/A',
            'registration_date' => now()->format('d M Y, h:i A'),
            'login_url' => "{$frontendUrl}{$langPrefix}/sign-in",
        ];

        $emailTemplate = EmailTemplate::where('event_key', 'user_registered')->first();
        $smsTemplate = SmsTemplate::where('event_key', 'user_registered')->first();

        // 1. Dispatch Email Template
        if ($emailTemplate && $emailTemplate->is_active) {
            $recipType = $emailTemplate->recipient_type; // customer, admin, both

            // Send to Customer in detected browsing language
            if (in_array($recipType, ['customer', 'both'], true) && !empty($customer->email)) {
                $this->sendEmail('user_registered', $customer->email, $data, $locale);
            }

            // Send to Admin (ONLY if explicitly configured in the template)
            if (in_array($recipType, ['admin', 'both'], true) && !empty(trim((string)$emailTemplate->notification_emails))) {
                $this->sendEmail('user_registered', $emailTemplate->notification_emails, $data, 'en');
            }
        }

        // 2. Dispatch SMS Template via Msegat in detected browsing language
        if ($smsTemplate && $smsTemplate->is_active) {
            $smsRecipType = $smsTemplate->recipient_type;

            // Customer SMS
            if (in_array($smsRecipType, ['customer', 'both'], true) && !empty($customer->phone)) {
                $this->sendSms('user_registered', $customer->phone, $data, $locale);
            }

            // Admin SMS (ONLY if explicitly configured in the template)
            if (in_array($smsRecipType, ['admin', 'both'], true) && !empty(trim((string)$smsTemplate->notification_phones))) {
                $this->sendSms('user_registered', $smsTemplate->notification_phones, $data, 'ar');
            }
        }
    }

    /**
     * Handle Customer Password Reset notifications (Email & SMS)
     */
    public function sendPasswordResetNotification(\App\Models\Customer $customer, string $resetUrl, string $token, ?string $preferredLocale = null): void
    {
        $locale = $preferredLocale
            ?: (request()?->header('X-Locale')
            ?: (request()?->input('locale')
            ?: (request()?->input('lang')
            ?: (app()->getLocale() ?: 'ar'))));
        $locale = in_array(strtolower(substr((string)$locale, 0, 2)), ['en', 'ar'], true) ? strtolower(substr((string)$locale, 0, 2)) : 'ar';

        $fullName = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''));
        $customerName = !empty($fullName) ? $fullName : ($customer->username ?: 'Customer');

        $data = [
            'customer_name' => $customerName,
            'customer_email' => $customer->email,
            'reset_url' => $resetUrl,
            'reset_token' => $token,
        ];

        // 1. Dispatch Email Template in user's current browsing language
        if (!empty($customer->email)) {
            $this->sendEmail('password_reset', $customer->email, $data, $locale);
        }

        // 2. Dispatch SMS Template via Msegat in user's current browsing language
        $smsTemplate = SmsTemplate::where('event_key', 'password_reset')->first();
        if ($smsTemplate && $smsTemplate->is_active && !empty($customer->phone)) {
            $this->sendSms('password_reset', $customer->phone, $data, $locale);
        }
    }

    /**
     * Handle Customer Email Verification notifications (Email & SMS) with live logging
     */
    public function sendEmailVerificationNotification(\App\Models\Customer $customer, string $verificationUrl, ?string $preferredLocale = null): array
    {
        $locale = $preferredLocale
            ?: (request()?->header('X-Locale')
            ?: (request()?->input('locale')
            ?: (request()?->input('lang')
            ?: (app()->getLocale() ?: 'ar'))));
        $locale = in_array(strtolower(substr((string)$locale, 0, 2)), ['en', 'ar'], true) ? strtolower(substr((string)$locale, 0, 2)) : 'ar';

        $fullName = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''));
        $customerName = !empty($fullName) ? $fullName : ($customer->name ?: ($customer->username ?: 'Customer'));

        $data = [
            'customer_name' => $customerName,
            'email' => $customer->email,
            'customer_email' => $customer->email,
            'verification_url' => $verificationUrl,
        ];

        // Dispatch Email Template in customer's browsing language (No SMS for email verification)
        return $this->sendEmail('email_verification', $customer->email, $data, $locale, null, request()?->ip());
    }

    /**
     * Handle Newsletter Subscription Welcome notifications (Email with 10% coupon)
     */
    public function sendNewsletterWelcomeNotification(\App\Models\NewsletterSubscriber $subscriber, ?string $preferredLocale = null): array
    {
        $locale = $preferredLocale
            ?: ($subscriber->locale ?? null)
            ?: (request()?->header('X-Locale')
            ?: (request()?->input('locale')
            ?: (request()?->input('lang')
            ?: (app()->getLocale() ?: 'ar'))));
        $locale = in_array(strtolower(substr((string)$locale, 0, 2)), ['en', 'ar'], true) ? strtolower(substr((string)$locale, 0, 2)) : 'ar';

        $origin = request()?->header('origin') ?: (request()?->header('referer') ? preg_replace('#^([^:]+://[^/]+).*$#', '$1', request()?->header('referer')) : null);
        $frontendUrl = $origin ?: rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');
        $langPrefix = $locale === 'en' ? '/en' : '';
        $shopUrl = "{$frontendUrl}{$langPrefix}/products";

        $data = [
            'email' => $subscriber->email,
            'coupon_code' => $subscriber->coupon_code ?: 'SAVE10',
            'discount_percent' => '10%',
            'shop_url' => $shopUrl,
        ];

        // Dispatch Email Template in subscriber's browsing language with live logging
        return $this->sendEmail('newsletter_welcome', $subscriber->email, $data, $locale, null, $subscriber->ip_address ?: request()?->ip());
    }

    /**
     * Handle Subscription lifecycle notifications (Email & SMS to Customer and Admin)
     */
    public function sendSubscriptionNotification(
        \App\Models\Subscription $subscription,
        string $eventKey,
        array $extraData = [],
        ?string $preferredLocale = null
    ): void {
        try {
            $emailTemplate = EmailTemplate::where('event_key', $eventKey)->first();
            $smsTemplate = SmsTemplate::where('event_key', $eventKey)->first();

            if ((!$emailTemplate || !$emailTemplate->is_active) && (!$smsTemplate || !$smsTemplate->is_active)) {
                return;
            }

            // Determine customer notification language:
            // 1. Explicitly passed locale
            // 2. Saved metadata locale / order_language
            // 3. Request header / param
            // 4. Default 'ar'
            $locale = $preferredLocale
                ?: ($subscription->metadata['locale'] ?? null)
                ?: ($subscription->metadata['order_language'] ?? null)
                ?: (request()?->header('X-Locale')
                ?: (request()?->input('locale')
                ?: (request()?->input('lang')
                ?: (app()->getLocale() ?: 'ar'))));
            $locale = in_array(strtolower((string)$locale), ['en', 'ar'], true) ? strtolower((string)$locale) : 'ar';
            $isAr = ($locale === 'ar');

            $customer = $subscription->customer;
            $addr = $subscription->delivery_address ?: [];

            $customerName = trim(($customer?->first_name ?? '') . ' ' . ($customer?->last_name ?? ''));
            if (empty($customerName)) {
                $customerName = $addr['recipient_name'] ?? ($customer?->name ?: ($isAr ? 'عميلنا العزيز' : 'Valued Subscriber'));
            }

            $customerEmail = $customer?->email ?? ($subscription->metadata['email'] ?? null);
            $customerPhone = $customer?->phone ?? ($addr['recipient_phone'] ?? null);

            // Frequency labels
            $freqLabels = [
                'weekly' => ['en' => 'Weekly', 'ar' => 'أسبوعياً'],
                'biweekly' => ['en' => 'Bi-weekly', 'ar' => 'كل أسبوعين'],
                'monthly' => ['en' => 'Monthly', 'ar' => 'شهرياً'],
                'custom' => ['en' => 'Custom Schedule', 'ar' => 'جدول مخصص'],
            ];
            $freqKey = strtolower((string)$subscription->frequency);
            $frequency = $freqLabels[$freqKey][$locale] ?? ($freqLabels[$freqKey]['en'] ?? ucfirst($subscription->frequency));

            // Duration labels
            $durLabels = [
                '1_month' => ['en' => '1 Month', 'ar' => 'شهر واحد'],
                '3_months' => ['en' => '3 Months', 'ar' => '3 أشهر'],
                '6_months' => ['en' => '6 Months', 'ar' => '6 أشهر'],
                '12_months' => ['en' => '12 Months', 'ar' => '12 شهراً'],
                'until_cancelled' => ['en' => 'Until Cancelled', 'ar' => 'حتى الإلغاء'],
                'custom' => ['en' => 'Custom Duration', 'ar' => 'مدة مخصصة'],
            ];
            $durKey = strtolower((string)$subscription->duration_type);
            $duration = $durLabels[$durKey][$locale] ?? ($durLabels[$durKey]['en'] ?? ($isAr ? '3 أشهر' : '3 Months'));

            // Price per delivery
            $totalAmount = (float)$subscription->total_per_delivery;
            $currencyLabel = $isAr ? 'ر.س' : 'SAR';
            $totalFormatted = number_format($totalAmount, 2) . ' ' . $currencyLabel;

            // Delivery date
            $nextDeliveryDate = $subscription->next_delivery_date
                ? \Carbon\Carbon::parse($subscription->next_delivery_date)->format('Y-m-d')
                : ($subscription->start_date ? \Carbon\Carbon::parse($subscription->start_date)->format('Y-m-d') : 'N/A');

            $deliveryTime = $subscription->delivery_time_slot ?: ($isAr ? 'الفترة الصباحية (09:00 ص - 01:00 م)' : 'Morning (09:00 AM - 01:00 PM)');

            // Product / Arrangement name
            $productName = $subscription->product?->name ?? ($subscription->metadata['plan_name'] ?? ($isAr ? 'باقة الزهور الدورية' : 'Floral Subscription Plan'));

            // Address formatting
            $recipientName = $addr['recipient_name'] ?? $customerName;
            $recipientPhone = $addr['recipient_phone'] ?? ($customerPhone ?: 'N/A');
            $fullAddress = trim(($addr['address'] ?? '') . ', ' . ($addr['city'] ?? 'Jeddah'));
            if (empty(trim($fullAddress, ', '))) {
                $fullAddress = 'Jeddah, Saudi Arabia';
            }

            $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');
            $langPrefix = $locale === 'en' ? '/en' : '';
            $subscriptionUrl = "{$frontendUrl}{$langPrefix}/account?tab=subscriptions";

            $cancellationReason = $extraData['reason'] ?? ($subscription->cancellation_reason ?: ($isAr ? 'بناءً على طلب العميل' : 'Requested by customer'));
            $newDeliveryDate = $extraData['new_delivery_date'] ?? $nextDeliveryDate;
            $newTimeSlot = $extraData['new_time_slot'] ?? $deliveryTime;
            $pauseResumeDate = $extraData['pause_resume_date'] ?? ($subscription->pause_resume_date ? \Carbon\Carbon::parse($subscription->pause_resume_date)->format('Y-m-d') : ($isAr ? 'عند الطلب من لوحة التحكم' : 'Upon request from dashboard'));

            $data = array_merge([
                'customer_name' => $customerName,
                'subscription_number' => $subscription->subscription_number,
                'product_name' => $productName,
                'frequency' => $frequency,
                'duration' => $duration,
                'total_per_delivery' => $totalFormatted,
                'currency' => $subscription->currency ?: 'SAR',
                'delivery_date' => $nextDeliveryDate,
                'delivery_time' => $deliveryTime,
                'recipient_name' => $recipientName,
                'recipient_phone' => $recipientPhone,
                'delivery_address' => $fullAddress,
                'cancellation_reason' => $cancellationReason,
                'new_delivery_date' => $newDeliveryDate,
                'pause_resume_date' => $pauseResumeDate,
                'subscription_url' => $subscriptionUrl,
            ], $extraData);

            // 1. Dispatch Customer Email
            if ($emailTemplate && $emailTemplate->is_active && in_array($emailTemplate->recipient_type, ['customer', 'both']) && !empty($customerEmail)) {
                $this->sendEmail($eventKey, $customerEmail, $data, $locale);
            }

            // 2. Dispatch Admin Email
            if ($emailTemplate && $emailTemplate->is_active && in_array($emailTemplate->recipient_type, ['admin', 'both'])) {
                $adminEmails = !empty($emailTemplate->notification_emails)
                    ? array_filter(array_map('trim', explode(',', $emailTemplate->notification_emails)))
                    : [config('mail.from.address', 'admin@grassflorist.com')];
                if (!empty($adminEmails)) {
                    $this->sendEmail($eventKey, $adminEmails, $data, 'en');
                }
            }

            // 3. Dispatch Customer SMS via Msegat
            if ($smsTemplate && $smsTemplate->is_active && in_array($smsTemplate->recipient_type, ['customer', 'both']) && !empty($customerPhone)) {
                $this->sendSms($eventKey, $customerPhone, $data, $locale);
            }

            // 4. Dispatch Admin SMS
            if ($smsTemplate && $smsTemplate->is_active && in_array($smsTemplate->recipient_type, ['admin', 'both'])) {
                $adminPhones = !empty($smsTemplate->notification_phones)
                    ? array_filter(array_map('trim', explode(',', $smsTemplate->notification_phones)))
                    : [];
                if (!empty($adminPhones)) {
                    $this->sendSms($eventKey, $adminPhones, $data, 'en');
                }
            }
        } catch (\Throwable $e) {
            Log::error("[NotificationService] Subscription notification error ({$eventKey}): " . $e->getMessage(), [
                'subscription_id' => $subscription->id,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
