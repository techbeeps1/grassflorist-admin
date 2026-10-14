<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\SubscriptionDelivery;
use App\Services\Payments\HyperPayService;
use App\Services\Payments\PaymentManager;
use App\Services\Subscriptions\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SubscriptionApiController extends Controller
{
    protected SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Resolve authenticated or authorized customer.
     */
    protected function resolveCustomer(Request $request): ?Customer
    {
        $customer = auth('customer')->user();
        if ($customer) {
            return $customer;
        }

        // Try authorization Bearer token explicitly
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            try {
                $customer = auth('customer')->setToken($token)->user();
                if ($customer) {
                    return $customer;
                }
            } catch (\Throwable $e) {
                // Token invalid or expired
            }
        }

        $userId = $request->input('user_id') ?: $request->input('customer_id');
        if ($userId && is_numeric($userId)) {
            return Customer::find($userId);
        }

        return null;
    }

    /**
     * Get global subscription configuration settings.
     * GET /api/subscriptions/settings
     */
    public function settings(): JsonResponse
    {
        $settings = \App\Models\SubscriptionSetting::current();

        return response()->json([
            'success' => true,
            'data' => [
                'is_enabled' => (bool)$settings->is_enabled,
                'default_discount_percentage' => (float)$settings->default_discount_percentage,
                'allowed_frequencies' => $settings->allowed_frequencies ?? ['weekly', 'biweekly', 'monthly'],
                'duration_mode' => $settings->duration_mode ?? 'fixed',
                'fixed_duration' => $settings->fixed_duration ?? 'until_cancelled',
                'allowed_durations' => $settings->allowed_durations ?? ['3_months', '6_months', '12_months', 'until_cancelled'],
                'customer_notes_enabled' => (bool)$settings->customer_notes_enabled,
            ],
        ]);
    }

    /**
     * Preview / Calculate zero-trust subscription pricing.
     * POST /api/subscriptions/calculate
     */
    public function calculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'nullable|integer|min:1',
            'frequency' => 'nullable|string|in:weekly,biweekly,monthly,custom',
            'duration_type' => 'nullable|string|in:1_month,3_months,6_months,12_months,until_cancelled,custom',
            'shipping_method_code' => 'nullable|string',
            'custom_dates' => 'nullable|array',
            'custom_dates.*' => 'date',
        ]);

        try {
            $pricing = $this->subscriptionService->calculatePricing(
                (int)$validated['product_id'],
                (int)($validated['quantity'] ?? 1),
                $validated['frequency'] ?? 'weekly',
                $validated['duration_type'] ?? 'until_cancelled',
                $validated['shipping_method_code'] ?? null,
                $validated['custom_dates'] ?? []
            );

            return response()->json([
                'success' => true,
                'data' => $pricing,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => ['error' => [$e->getMessage()]],
            ], 500);
        }
    }

    /**
     * Checkout & Create Subscription.
     * POST /api/subscriptions/checkout
     */
    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'nullable|integer|min:1',
            'frequency' => 'required|string|in:weekly,biweekly,monthly,custom',
            'duration_type' => 'nullable|string|in:1_month,3_months,6_months,12_months,until_cancelled,custom',
            'start_date' => 'required|date',
            'delivery_time_slot' => 'nullable|string',
            'recipient_name' => 'required|string|max:255',
            'recipient_phone' => 'required|string|max:50',
            'email' => 'required|email|max:255',
            'address' => 'required|string|max:500',
            'address_2' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:20',
            'card_message' => 'nullable|string|max:1000',
            'special_instructions' => 'nullable|string|max:1000',
            'payment_gateway' => 'nullable|string|in:hyperpay,paypal,tabby,tamara,cod',
            'custom_dates' => 'nullable|array',
            'custom_dates.*' => 'date',
        ]);

        try {
            // 1. Resolve or create customer account
            $customer = $this->resolveCustomer($request);
            if (!$customer) {
                $email = strtolower(trim($validated['email']));
                $phone = trim($validated['recipient_phone']);

                $customer = Customer::where('email', $email)->orWhere('phone', $phone)->first();

                if (!$customer) {
                    $nameParts = explode(' ', trim($validated['recipient_name']), 2);
                    $customer = Customer::create([
                        'first_name' => $nameParts[0] ?? 'Subscriber',
                        'last_name' => $nameParts[1] ?? '',
                        'email' => $email,
                        'phone' => $phone,
                        'address' => $validated['address'],
                        'city' => $validated['city'] ?? 'Jeddah',
                        'district' => $validated['district'] ?? null,
                        'zip_code' => $validated['zip_code'] ?? null,
                        'password' => Hash::make(bin2hex(random_bytes(8))),
                    ]);
                }
            }

            // 2. Create the subscription with zero-trust pricing
            $gateway = $validated['payment_gateway'] ?? 'hyperpay';
            $subscription = $this->subscriptionService->createSubscription($customer->id, array_merge($validated, [
                'payment_gateway' => $gateway,
                'status' => in_array($gateway, ['hyperpay', 'paypal']) ? 'pending_payment' : 'active',
            ]));

            // 3. Initiate first delivery & payment session
            $firstDelivery = $subscription->deliveries()->orderBy('delivery_number', 'asc')->first() ?? $subscription->deliveries()->first();
            $initialOrder = null;
            $paymentData = null;

            if ($firstDelivery) {
                $initialOrder = $this->subscriptionService->generateOrderForDelivery($firstDelivery);

                if ($gateway === 'hyperpay') {
                    // Create checkout session with registration for recurring tokenization
                    $hyperPay = new HyperPayService();
                    // Request registration token on initial purchase
                    $paymentData = $hyperPay->initiateCheckout($initialOrder, [
                        'createRegistration' => true,
                        'standingInstruction' => [
                            'mode' => 'INITIAL',
                            'type' => 'RECURRING',
                        ],
                    ]);
                }
            }

            // 🌸 If subscription is directly active (e.g. COD / free), trigger confirmation notifications
            if ($subscription->status === 'active') {
                try {
                    $notifService = app(\App\Services\Notifications\NotificationService::class);
                    $notifService->sendSubscriptionNotification($subscription, 'subscription_confirmed');
                    $notifService->sendSubscriptionNotification($subscription, 'admin_subscription_created');
                } catch (\Throwable $ne) {
                    Log::error("Direct active notification failed: " . $ne->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Subscription created successfully.',
                'data' => [
                    'subscription' => $subscription->load(['deliveries', 'product:id,name,slug,image,price']),
                    'initial_order' => $initialOrder ? [
                        'id' => $initialOrder->id,
                        'order_number' => $initialOrder->order_number,
                        'total_amount' => $initialOrder->total_amount,
                        'status' => $initialOrder->status,
                    ] : null,
                    'payment' => $paymentData,
                ],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Subscription checkout error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create subscription: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List customer subscriptions.
     * GET /api/my-account/subscriptions
     */
    public function index(Request $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);
        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $status = $request->query('status');

        $query = Subscription::where('customer_id', $customer->id)
            ->with([
                'product:id,name,slug,image,price',
                'deliveries' => function ($q) {
                    $q->orderBy('delivery_number', 'asc');
                },
            ])
            ->withCount(['deliveries as scheduled_count' => function ($q) {
                $q->where('delivery_status', 'scheduled');
            }])
            ->withCount(['deliveries as delivered_count' => function ($q) {
                $q->where('delivery_status', 'delivered');
            }])
            ->latest();

        if (!empty($status) && $status !== 'all') {
            $query->where('status', $status);
        }

        $subscriptions = $query->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $subscriptions,
        ]);
    }

    /**
     * Show subscription details and delivery timeline.
     * GET /api/my-account/subscriptions/{id}
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);
        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $subscription = Subscription::where('id', $id)
            ->where('customer_id', $customer->id)
            ->with([
                'product:id,name,slug,image,price,description',
                'deliveries.order:id,order_number,status,payment_status,total_amount,ordered_at',
                'activities' => function ($q) {
                    $q->latest()->take(20);
                },
                'orders' => function ($q) {
                    $q->latest()->take(10);
                },
            ])
            ->first();

        if (!$subscription) {
            return response()->json(['success' => false, 'message' => 'Subscription not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $subscription,
        ]);
    }

    /**
     * Pause subscription.
     * POST /api/my-account/subscriptions/{id}/pause
     */
    public function pause(int $id, Request $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);
        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $subscription = Subscription::where('id', $id)->where('customer_id', $customer->id)->firstOrFail();

        $reason = $request->input('reason', 'Paused by customer from dashboard');
        $success = $this->subscriptionService->pauseSubscription($subscription, $reason);

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Subscription paused successfully.' : 'Unable to pause subscription.',
            'status' => $subscription->fresh()->status,
        ]);
    }

    /**
     * Resume subscription.
     * POST /api/my-account/subscriptions/{id}/resume
     */
    public function resume(int $id, Request $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);
        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $subscription = Subscription::where('id', $id)->where('customer_id', $customer->id)->firstOrFail();

        $success = $this->subscriptionService->resumeSubscription($subscription);

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Subscription resumed successfully.' : 'Unable to resume subscription.',
            'status' => $subscription->fresh()->status,
            'next_delivery_date' => $subscription->fresh()->next_delivery_date,
        ]);
    }

    /**
     * Skip next scheduled delivery.
     * POST /api/my-account/subscriptions/{id}/skip-next
     */
    public function skipNext(int $id, Request $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);
        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $subscription = Subscription::where('id', $id)->where('customer_id', $customer->id)->firstOrFail();

        // Get the earliest scheduled delivery
        $delivery = $subscription->deliveries()
            ->where('status', 'scheduled')
            ->whereDate('scheduled_delivery_date', '>=', now()->toDateString())
            ->orderBy('scheduled_delivery_date', 'asc')
            ->first();

        if (!$delivery) {
            return response()->json([
                'success' => false,
                'message' => 'No upcoming scheduled deliveries available to skip.',
            ], 422);
        }

        $reason = $request->input('reason', 'Skipped by customer');
        $this->subscriptionService->skipDelivery($delivery, $reason);

        return response()->json([
            'success' => true,
            'message' => "Delivery scheduled for {$delivery->scheduled_delivery_date} skipped successfully.",
            'next_delivery_date' => $subscription->fresh()->next_delivery_date,
        ]);
    }

    /**
     * Reschedule specific delivery cycle.
     * POST /api/my-account/subscriptions/{id}/deliveries/{deliveryId}/reschedule
     */
    public function reschedule(int $id, int $deliveryId, Request $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);
        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $subscription = Subscription::where('id', $id)->where('customer_id', $customer->id)->firstOrFail();

        $delivery = SubscriptionDelivery::where('id', $deliveryId)
            ->where('subscription_id', $subscription->id)
            ->firstOrFail();

        $validated = $request->validate([
            'new_date' => 'required|date|after_or_equal:today',
            'new_time_slot' => 'nullable|string|max:100',
            'reason' => 'nullable|string|max:500',
        ]);

        $this->subscriptionService->rescheduleDelivery(
            $delivery,
            $validated['new_date'],
            $validated['new_time_slot'] ?? null,
            $validated['reason'] ?? 'Rescheduled by customer'
        );

        return response()->json([
            'success' => true,
            'message' => 'Delivery rescheduled successfully.',
            'delivery' => $delivery->fresh(),
            'next_delivery_date' => $subscription->fresh()->next_delivery_date,
        ]);
    }

    /**
     * Update subscription address.
     * POST /api/my-account/subscriptions/{id}/address
     */
    public function updateAddress(int $id, Request $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);
        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $subscription = Subscription::where('id', $id)->where('customer_id', $customer->id)->firstOrFail();

        $validated = $request->validate([
            'recipient_name' => 'required|string|max:255',
            'recipient_phone' => 'required|string|max:50',
            'address' => 'required|string|max:500',
            'address_2' => 'nullable|string|max:500',
            'city' => 'required|string|max:100',
            'district' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:20',
        ]);

        $this->subscriptionService->updateAddress($subscription, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Delivery address updated successfully.',
            'subscription' => $subscription->fresh(),
        ]);
    }

    /**
     * Update subscription quantity.
     * POST /api/my-account/subscriptions/{id}/quantity
     */
    public function updateQuantity(int $id, Request $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);
        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $subscription = Subscription::where('id', $id)->where('customer_id', $customer->id)->firstOrFail();

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1|max:50',
        ]);

        $this->subscriptionService->updateQuantity($subscription, (int)$validated['quantity']);

        return response()->json([
            'success' => true,
            'message' => 'Quantity updated successfully.',
            'subscription' => $subscription->fresh(),
        ]);
    }

    /**
     * Cancel subscription.
     * POST /api/my-account/subscriptions/{id}/cancel
     */
    public function cancel(int $id, Request $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);
        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $subscription = Subscription::where('id', $id)->where('customer_id', $customer->id)->firstOrFail();

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $this->subscriptionService->cancelSubscription($subscription, $validated['reason'], 'customer');

        return response()->json([
            'success' => true,
            'message' => 'Subscription cancelled successfully.',
            'status' => $subscription->fresh()->status,
        ]);
    }

    /**
     * Retry payment for a failed delivery.
     * POST /api/my-account/subscriptions/{id}/retry-payment
     */
    public function retryPayment(int $id, Request $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);
        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $subscription = Subscription::where('id', $id)->where('customer_id', $customer->id)->firstOrFail();

        $failedDelivery = $subscription->deliveries()
            ->where('status', 'failed')
            ->latest('id')
            ->first();

        if (!$failedDelivery) {
            return response()->json([
                'success' => false,
                'message' => 'No failed deliveries found requiring payment retry.',
            ], 422);
        }

        // Retry via token or generate new checkout session
        if (!empty($subscription->payment_token)) {
            $order = $failedDelivery->order;
            if (!$order) {
                $order = $this->subscriptionService->generateOrderForDelivery($failedDelivery);
            }

            $gateway = $subscription->payment_gateway ?? 'hyperpay';
            $result = PaymentManager::chargeRecurringToken($order, $subscription->payment_token, $gateway);

            if (!empty($result['success'])) {
                PaymentManager::markOrderAsPaid($order, $result['transaction_id'] ?? 'RETRY_SUCCESS', $gateway, $result, 'RETRY');
                $failedDelivery->update(['status' => 'delivered', 'paid_at' => now(), 'failure_reason' => null]);
                $subscription->update(['failed_payment_attempts' => 0, 'status' => 'active']);

                return response()->json([
                    'success' => true,
                    'message' => 'Payment retry succeeded! Delivery is now scheduled.',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Payment retry declined: ' . ($result['message'] ?? 'Transaction failed.'),
            ], 400);
        }

        return response()->json([
            'success' => false,
            'message' => 'No saved card token found. Please update your payment method.',
        ], 422);
    }
}
