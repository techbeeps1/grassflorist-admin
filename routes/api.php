<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductSearchController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\CartController;
use Illuminate\Session\Middleware\StartSession;
use App\Http\Controllers\Api\DebugController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\CmsPageController;
use App\Http\Controllers\Api\CmsPostController;
use App\Http\Controllers\Api\OrderApiController;
use App\Http\Controllers\Api\HomePageController;
use App\Http\Controllers\Api\ContactFormController;
use App\Http\Controllers\Api\ContactPageController;
use App\Http\Controllers\Api\CustomerForgotPasswordController;
use App\Http\Controllers\Api\CustomerResetPasswordController;
use App\Http\Controllers\Api\NewsletterController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\Api\GlobalSettingController;
use App\Http\Controllers\Api\TestimonialController;
use App\Http\Controllers\Api\FaqController;

/*
|--------------------------------------------------------------------------
| Storefront API Route Definition Function
|--------------------------------------------------------------------------
| Registered under /api/en/..., /api/ar/..., and /api/... to provide
| first-class URL prefix localization across all endpoints.
*/
$registerStoreRoutes = function () {
    // 🌸 Catalog & Navigation
    Route::get('/category', [CategoryController::class, 'index']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{slug}', [ProductController::class, 'show']);
    Route::get('/category/{slug}', [ProductController::class, 'productsByCategorySlug']);
    Route::get('/search', [ProductSearchController::class, 'search']);
    Route::get('/navigation', [MenuController::class, 'getNavigation']);
    Route::get('menus/{menuName}', [MenuController::class, 'getMenuItems']);
    Route::get('/shipping-methods', [\App\Http\Controllers\Api\ShippingMethodController::class, 'index']);

    // 🔒 Auth & Password Reset
    Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail']);
    Route::post('/customer/forgot-password', [CustomerForgotPasswordController::class, 'sendResetLinkEmail']);
    Route::post('/customer/reset-password', [CustomerResetPasswordController::class, 'reset']);

    // 💌 Newsletter Subscription
    Route::post('/newsletter', [NewsletterController::class, 'subscribe']);

    // 🛒 Cart & Checkout (Canonical Endpoints)
    Route::prefix('cart')->group(function () {
        Route::middleware(['api', StartSession::class])->get('/viewcart', [CartController::class, 'viewcart']);
        Route::middleware(['api', StartSession::class])->match(['get', 'post'], '/empty', [CartController::class, 'empty']);
        Route::middleware(['api', StartSession::class])->post('add', [CartController::class, 'add']);
        Route::middleware(['api', StartSession::class])->post('merge', [CartController::class, 'merge']);
        Route::middleware(['api', StartSession::class])->post('cartupdate', [CartController::class, 'cartupdate']);
        Route::middleware(['api', StartSession::class])->post('remove', [CartController::class, 'remove']);
        Route::middleware(['api', StartSession::class])->match(['get', 'post'], '/clear', [CartController::class, 'clear']);
        Route::post('/checkout', [CheckoutController::class, 'process']);
        Route::post('/sync-customer', [CartController::class, 'syncCustomer']);
        Route::post('/coupon/{coupon_code}', [CheckoutController::class, 'showCouponCode']);
        Route::get('/coupons', [CheckoutController::class, 'getAvailableCoupons']);
    });

    Route::get('orders/{order_number}', [OrderApiController::class, 'show']);

    // 👤 Customer Account & Protected Dashboards
    Route::prefix('my-account')->group(function () {
        // Unprotected refresh & email verification endpoints
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
        Route::get('/verify-email', [AuthController::class, 'verifyEmail']);
        Route::post('/send-verification-email', [AuthController::class, 'sendVerificationEmail']);

        Route::middleware('auth:customer')->group(function () { 
            Route::post('/passwordchange', [AuthController::class, 'passwordchange']);
            Route::get('user_order/{user_id?}', [OrderApiController::class, 'userOrders']);
            Route::get('orders', [OrderApiController::class, 'userOrders']);
            Route::post('/cancel-order', [OrderApiController::class, 'cancelOrder']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/user', [AuthController::class, 'user']);
            Route::get('/view-address/{user_id}', [AuthController::class, 'viewAddress']);
            Route::get('/checkauth', [AuthController::class, 'checkAuth']);
            Route::post('/add-wishlist', [WishlistController::class, 'store']);
            Route::get('/view-wishlist', [WishlistController::class, 'index']);
            Route::get('/view-wishlistid', [WishlistController::class, 'wishlistid']);
            Route::delete('/delete-wishlist', [WishlistController::class, 'destroy']);
        });

        // 📅 Subscriptions Customer Dashboard Actions
        Route::get('/subscriptions', [\App\Http\Controllers\Api\SubscriptionApiController::class, 'index']);
        Route::get('/subscriptions/{id}', [\App\Http\Controllers\Api\SubscriptionApiController::class, 'show']);
        Route::post('/subscriptions/{id}/pause', [\App\Http\Controllers\Api\SubscriptionApiController::class, 'pause']);
        Route::post('/subscriptions/{id}/resume', [\App\Http\Controllers\Api\SubscriptionApiController::class, 'resume']);
        Route::post('/subscriptions/{id}/skip-next', [\App\Http\Controllers\Api\SubscriptionApiController::class, 'skipNext']);
        Route::post('/subscriptions/{id}/deliveries/{deliveryId}/reschedule', [\App\Http\Controllers\Api\SubscriptionApiController::class, 'reschedule']);
        Route::post('/subscriptions/{id}/address', [\App\Http\Controllers\Api\SubscriptionApiController::class, 'updateAddress']);
        Route::post('/subscriptions/{id}/quantity', [\App\Http\Controllers\Api\SubscriptionApiController::class, 'updateQuantity']);
        Route::post('/subscriptions/{id}/cancel', [\App\Http\Controllers\Api\SubscriptionApiController::class, 'cancel']);
        Route::post('/subscriptions/{id}/retry-payment', [\App\Http\Controllers\Api\SubscriptionApiController::class, 'retryPayment']);
    });

    // 🌸 Subscription Public Deals & Pricing Endpoints
    Route::get('/subscription-plans', [\App\Http\Controllers\Api\SubscriptionPlanController::class, 'index']);
    Route::get('/subscription-plans/{slug}', [\App\Http\Controllers\Api\SubscriptionPlanController::class, 'show']);
    Route::get('/subscriptions/settings', [\App\Http\Controllers\Api\SubscriptionApiController::class, 'settings']);
    Route::post('/subscriptions/calculate', [\App\Http\Controllers\Api\SubscriptionApiController::class, 'calculate']);
    Route::post('/subscriptions/checkout', [\App\Http\Controllers\Api\SubscriptionApiController::class, 'checkout']);

    // 🔑 Auth API (v1)
    Route::prefix('v1')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/updateuser', [AuthController::class, 'updateuser']);
    });

    // 📄 CMS Pages & Blog
    Route::get('cms-pages', [CmsPageController::class, 'index']);
    Route::get('cms-pages/{slug}', [CmsPageController::class, 'showBySlug']);
    Route::get('blog', [CmsPostController::class, 'index']);
    Route::get('blog/{slug}', [CmsPostController::class, 'showBySlug']);

    // 🏠 Home Page & Global Settings
    Route::get('/home-page', [HomePageController::class, 'index']);
    Route::get('/contact-page', [ContactPageController::class, 'index']);
    Route::get('/global-settings', [GlobalSettingController::class, 'index']);
    Route::post('/global-settings/fetch-currency-rate', [GlobalSettingController::class, 'fetchCurrencyRate']);
    Route::get('/currency-rates', [GlobalSettingController::class, 'fetchCurrencyRate']);

    // ⭐ Testimonials, Reviews & FAQs
    Route::get('/testimonials', [TestimonialController::class, 'index']);
    Route::get('/google-reviews', [\App\Http\Controllers\Api\GoogleReviewController::class, 'index']);
    Route::get('/faqs', [FaqController::class, 'index']);

    // 📝 Contact & Inquiries Forms
    Route::post('/contact-form', [ContactFormController::class, 'send']);
    Route::post('/event-booking', [\App\Http\Controllers\Api\EventBookingController::class, 'store']);
    Route::post('/partner-with-us', [\App\Http\Controllers\Api\PartnerWithUsController::class, 'store']);

    // 💳 Saudi Payment Gateways & Delivery Slots APIs (v1)
    Route::prefix('v1')->group(function () {
        Route::get('/payment-methods', [\App\Http\Controllers\Api\StorefrontPaymentController::class, 'getPaymentMethods']);
        Route::get('/delivery-slots', [\App\Http\Controllers\Api\StorefrontPaymentController::class, 'getDeliverySlots']);
        Route::get('/delivery-slots/blocked-dates', [\App\Http\Controllers\Api\StorefrontPaymentController::class, 'getBlockedDeliveryDates']);
        Route::post('/payments/initiate', [\App\Http\Controllers\Api\StorefrontPaymentController::class, 'initiatePayment']);
        Route::post('/payments/verify', [\App\Http\Controllers\Api\StorefrontPaymentController::class, 'verifyPaymentStatus']);
    });
};

// 1. 🇬🇧 English API Endpoints (/api/en/...)
Route::prefix('en')->group($registerStoreRoutes);

// 2. 🇸🇦 Arabic API Endpoints (/api/ar/...)
Route::prefix('ar')->group($registerStoreRoutes);

// 3. Fallback for non-prefixed API requests: Redirect to /api/ar/... or /api/en/...
Route::fallback(function (Request $request) {
    $path = trim($request->path(), '/');
    if (str_starts_with($path, 'api/')) {
        $sub = substr($path, 4);
        if (
            !str_starts_with($sub, 'en/') &&
            !str_starts_with($sub, 'ar/') &&
            !str_starts_with($sub, 'v1/webhooks/')
        ) {
            $prefix = ($request->header('X-Locale') === 'en' || $request->input('locale') === 'en' || $request->query('lang') === 'en') ? 'en' : 'ar';
            $target = '/api/' . $prefix . '/' . $sub;
            $query = $request->getQueryString();
            if ($query) {
                $target .= '?' . $query;
            }
            return redirect($target, 307);
        }
    }
    return response()->json(['message' => 'Endpoint not found'], 404);
});

// --------------------------------------------------------------------------
// Standalone Server-to-Server Payment Webhooks & Callbacks (No language prefix)
// --------------------------------------------------------------------------
Route::prefix('v1/webhooks')->group(function () {
    Route::post('/hyperpay', [\App\Http\Controllers\Api\PaymentWebhookController::class, 'handleHyperPay']);
    Route::post('/tabby', [\App\Http\Controllers\Api\PaymentWebhookController::class, 'handleTabby']);
    Route::post('/tamara', [\App\Http\Controllers\Api\PaymentWebhookController::class, 'handleTamara']);
    Route::post('/paypal', [\App\Http\Controllers\Api\PaymentWebhookController::class, 'handlePayPal']);
});

Route::get('/debug', [DebugController::class, 'index'])->middleware('web');