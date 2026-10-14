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

        // Look up active coupon flagged for subscribers (if configured)
        $subscriberCoupon = \App\Models\Coupon::where('is_active', true)
            ->where('is_subscriber_only', true)
            ->latest('id')
            ->first();
        $couponCode = $subscriberCoupon ? $subscriberCoupon->code : null;

        if ($existing) {
            // If already subscribed and active
            if ($existing->status === 'active') {
                return response()->json([
                    'status' => 'success',
                    'success' => true,
                    'already_subscribed' => true,
                    'coupon_code' => $couponCode,
                    'message' => $locale === 'ar'
                        ? ($couponCode
                            ? 'أنت مشترك بالفعل في نادي غراس للزهور! كود الخصم الحصري للمشتركين هو: ' . $couponCode
                            : 'أنت مشترك بالفعل في نادي غراس للزهور!')
                        : ($couponCode
                            ? 'You are already subscribed to the Grass Florist VIP Club! Your exclusive subscriber coupon is: ' . $couponCode
                            : 'You are already subscribed to the Grass Florist VIP Club!'),
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
                'coupon_code' => null, // Do not store coupon code on subscriber row
                'status' => 'active',
                'ip_address' => $request->ip(),
                'subscribed_at' => now(),
            ]);
        }

        // Dispatch Welcome Email (uses active subscriber coupon if available)
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
            'coupon_code' => $couponCode,
            'message' => $locale === 'ar'
                ? ($couponCode
                    ? 'شكراً لاشتراكك في نادي غراس! كود الخصم الخاص بك هو (' . $couponCode . ').'
                    : 'شكراً لاشتراكك في النشرة البريدية لنادي غراس!')
                : ($couponCode
                    ? 'Thank you for subscribing to Grass VIP Club! Your discount code is (' . $couponCode . ').'
                    : 'Thank you for subscribing to the Grass Florist newsletter!'),
        ]);
    }
}