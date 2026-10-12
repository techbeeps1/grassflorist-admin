<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NewsletterController extends Controller
{
    /**
     * Subscribe an email to the newsletter and dispatch welcome email with 10% coupon
     */
    public function subscribe(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'locale' => 'nullable|string|in:ar,en',
        ]);

        $email = strtolower(trim($validated['email']));
        $reqLocale = $request->input('locale')
            ?: ($request->header('X-Locale')
            ?: ($request->input('lang')
            ?: (app()->getLocale() ?: 'ar')));
        $locale = in_array(strtolower(substr((string)$reqLocale, 0, 2)), ['en', 'ar'], true)
            ? strtolower(substr((string)$reqLocale, 0, 2))
            : 'ar';

        $existing = NewsletterSubscriber::where('email', $email)->first();

        if ($existing) {
            // If already subscribed and active
            if ($existing->status === 'active') {
                return response()->json([
                    'status' => 'success',
                    'success' => true,
                    'already_subscribed' => true,
                    'coupon_code' => $existing->coupon_code ?: 'SAVE10',
                    'message' => $locale === 'ar'
                        ? 'أنت مشترك بالفعل في نادي غراس للزهور! كود الخصم الترحيبي الخاص بك هو: ' . ($existing->coupon_code ?: 'SAVE10')
                        : 'You are already a member of the Grass Florist VIP Club! Your welcome coupon is: ' . ($existing->coupon_code ?: 'SAVE10'),
                ]);
            }

            // If was unsubscribed, re-activate
            $existing->update([
                'status' => 'active',
                'locale' => $locale,
                'subscribed_at' => now(),
            ]);
            $subscriber = $existing;
        } else {
            $subscriber = NewsletterSubscriber::create([
                'email' => $email,
                'locale' => $locale,
                'coupon_code' => 'SAVE10',
                'status' => 'active',
                'ip_address' => $request->ip(),
                'subscribed_at' => now(),
            ]);
        }

        // Dispatch Welcome Email with SAVE10 coupon & record in NotificationLog
        try {
            /** @var NotificationService $notificationService */
            $notificationService = app(NotificationService::class);
            $notificationService->sendNewsletterWelcomeNotification($subscriber, $locale);
        } catch (\Throwable $e) {
            Log::error('Newsletter welcome email dispatch error: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'success' => true,
            'coupon_code' => 'SAVE10',
            'message' => $locale === 'ar'
                ? 'شكراً لاشتراكك في نادي غراس! تم إرسال كود خصم 10% (SAVE10) إلى بريدك الإلكتروني.'
                : 'Thank you for subscribing to Grass VIP Club! Your 10% discount code (SAVE10) has been sent to your email.',
        ]);
    }
}