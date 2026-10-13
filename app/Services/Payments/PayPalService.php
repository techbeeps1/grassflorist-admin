<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\PaymentLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayPalService implements PaymentGatewayInterface
{
    protected PaymentGateway $gateway;
    protected string $baseUrl;
    protected array $credentials;

    public function __construct()
    {
        $this->gateway = PaymentGateway::where('code', 'paypal')->first() ?? new PaymentGateway();
        $this->credentials = $this->gateway->credentials ?? [];
        $isSandbox = $this->gateway->is_sandbox ?? true;

        $this->baseUrl = $isSandbox ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
    }

    public function getClientId(): string
    {
        return $this->credentials['client_id'] ?? '';
    }

    public function getClientSecret(): string
    {
        return $this->credentials['client_secret'] ?? '';
    }

    /**
     * Get OAuth2 Access Token from PayPal (cached to eliminate redundant round-trips)
     */
    protected function getAccessToken(): ?string
    {
        $clientId = $this->getClientId();
        $clientSecret = $this->getClientSecret();

        if (empty($clientId) || empty($clientSecret)) {
            return null;
        }

        $cacheKey = 'paypal_access_token_' . md5($clientId . $this->baseUrl);
        $cachedToken = Cache::get($cacheKey);
        if (!empty($cachedToken)) {
            return $cachedToken;
        }

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->withBasicAuth($clientId, $clientSecret)
                ->post("{$this->baseUrl}/v1/oauth2/token", [
                    'grant_type' => 'client_credentials',
                ]);

            if ($response->successful()) {
                $token = $response->json('access_token');
                $expiresIn = (int) ($response->json('expires_in') ?? 32400);
                Cache::put($cacheKey, $token, max(60, $expiresIn - 300));
                return $token;
            }

            Log::error('PayPal getAccessToken failed: ' . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error('PayPal getAccessToken exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Initiate PayPal Order
     */
    public function initiateCheckout(Order $order, array $options = []): array
    {
        $token = $this->getAccessToken();

        if (empty($token)) {
            $err = 'PayPal credentials (Client ID or Secret) not configured in Admin Settings.';
            PaymentLog::create([
                'order_id' => $order->id,
                'order_number' => $order->order_number ?? (string) $order->id,
                'gateway' => 'paypal',
                'event_type' => 'initiate',
                'status' => 'failed',
                'amount' => $order->total_amount,
                'message' => $err,
                'payload' => ['error' => 'missing_credentials'],
            ]);

            return [
                'success' => false,
                'message' => $err,
            ];
        }

        // Amount calculation - lock order to USD and calculate exact USD amount
        $currency = 'USD';
        $conversionRate = (float) ($order->exchange_rate ?: 0.2667);
        if ($conversionRate <= 0 || $conversionRate >= 1.0) {
            $conversionRate = (float) (\App\Models\GlobalSetting::first()?->sar_to_usd_rate ?: 0.2667);
        }
        if ($conversionRate <= 0 || $conversionRate >= 1.0) {
            $conversionRate = 0.2667;
        }

        $sarAmount = (float) ($order->sar_amount ?: $order->total_amount);
        $amount = (float) ($order->currency_amount ?? 0);

        // If currency_amount was not set, was in SAR (>= sarAmount), or order wasn't previously USD
        if ($amount <= 0 || $amount >= $sarAmount || strtoupper((string) $order->currency) !== 'USD') {
            $amount = round($sarAmount * $conversionRate, 2);
        }

        // Freeze exchange_rate, currency_amount and sar_amount on order
        $updateData = [
            'exchange_rate' => $conversionRate,
            'sar_amount' => $sarAmount,
        ];

        if (strtoupper((string) $order->currency) === 'USD') {
            $updateData['currency'] = 'USD';
            $updateData['currency_amount'] = $amount;
        } else {
            $updateData['currency'] = 'SAR';
            $updateData['currency_amount'] = $sarAmount;
        }

        $metaData = (array) ($order->meta_data ?? []);
        $metaData['paypal_charge_usd'] = $amount;
        $updateData['meta_data'] = $metaData;

        $order->update($updateData);

        $locale = $options['locale'] ?? app()->getLocale();
        $prefix = $locale === 'ar' ? '' : '/en';
        $frontendBase = $this->getFrontendUrl($options);
        $returnUrl = $options['return_url'] ?? "{$frontendBase}{$prefix}/checkout?order_number={$order->order_number}&order_id={$order->id}&gateway=paypal&payment_success=1";
        $cancelUrl = $options['cancel_url'] ?? "{$frontendBase}{$prefix}/checkout?order_number={$order->order_number}&order_id={$order->id}&gateway=paypal&cancelled=1";

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => (string) ($order->order_number ?? $order->id),
                    'description' => "Grass Florist Order #{$order->order_number}",
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => number_format($amount, 2, '.', ''),
                    ],
                ],
            ],
            'application_context' => [
                'brand_name' => 'Grass Florist',
                'landing_page' => 'BILLING',
                'user_action' => 'PAY_NOW',
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
            ],
        ];

        // ⚡ Check if order contains a subscription product
        $hasSubscription = !empty($order->subscription_id)
            || !empty($order->meta_data['has_subscription'])
            || !empty($order->is_recurring_order)
            || (!empty($order->id) && $order->items->contains(function ($item) {
                $meta = is_array($item->meta_data) ? $item->meta_data : json_decode((string) $item->meta_data, true);
                return !empty($meta['is_subscription']);
            }));

        // 1. 🔄 SUBSCRIPTION PRODUCT: Compulsory PayPal Subscription Billing Flow
        if ($hasSubscription) {
            return $this->initiateSubscriptionCheckout($order, $amount, $options);
        }

        // 2. 🛍️ NORMAL PRODUCT: Standard One-Time PayPal Payment
        return $this->initiateStandardCheckout($order, $amount, $currency, $token, $returnUrl, $cancelUrl);
    }

    /**
     * Initiate compulsory PayPal Recurring Subscription session (/v1/billing/subscriptions)
     */
    protected function initiateSubscriptionCheckout(Order $order, float $amount, array $options = []): array
    {
        $token = $this->getAccessToken();
        if (empty($token)) {
            return [
                'success' => false,
                'message' => 'PayPal credentials / access token not configured.',
            ];
        }

        // Determine frequency
        $frequency = 'weekly';
        if (!empty($order->subscription_id)) {
            $sub = \App\Models\Subscription::find($order->subscription_id);
            if ($sub && !empty($sub->frequency)) {
                $frequency = $sub->frequency;
            }
        } elseif (!empty($order->items)) {
            foreach ($order->items as $item) {
                $meta = is_array($item->meta_data) ? $item->meta_data : json_decode((string) $item->meta_data, true);
                if (!empty($meta['is_subscription']) && !empty($meta['frequency'])) {
                    $frequency = $meta['frequency'];
                    break;
                }
            }
        }

        try {
            $planId = $this->getOrCreateBillingPlan($token, $frequency, $amount);

            $locale = $options['locale'] ?? app()->getLocale();
            $prefix = $locale === 'ar' ? '' : '/en';
            $frontendBase = $this->getFrontendUrl($options);
            $returnUrl = $options['return_url'] ?? "{$frontendBase}{$prefix}/checkout?order_number={$order->order_number}&order_id={$order->id}&gateway=paypal&payment_success=1";
            $cancelUrl = $options['cancel_url'] ?? "{$frontendBase}{$prefix}/checkout?order_number={$order->order_number}&order_id={$order->id}&gateway=paypal&cancelled=1";

            $subPayload = [
                'plan_id' => $planId,
                'custom_id' => (string) ($order->order_number ?? $order->id),
                'application_context' => [
                    'brand_name' => 'Grass Florist',
                    'locale' => $locale === 'ar' ? 'ar-SA' : 'en-US',
                    'shipping_preference' => 'NO_SHIPPING',
                    'user_action' => 'SUBSCRIBE_NOW',
                    'return_url' => $returnUrl,
                    'cancel_url' => $cancelUrl,
                ],
            ];

            $response = Http::withToken($token)
                ->timeout(12)
                ->post("{$this->baseUrl}/v1/billing/subscriptions", $subPayload);

            $data = $response->json() ?? [];

            if ($response->successful() && !empty($data['id'])) {
                $subscriptionId = $data['id'];
                $order->update(['razorpay_order_id' => $subscriptionId]);

                $approvalUrl = null;
                foreach ($data['links'] ?? [] as $link) {
                    if (($link['rel'] ?? '') === 'approve') {
                        $approvalUrl = $link['href'];
                        break;
                    }
                }

                PaymentLog::create([
                    'order_id' => $order->id,
                    'order_number' => $order->order_number ?? (string) $order->id,
                    'gateway' => 'paypal',
                    'event_type' => 'initiate_subscription',
                    'status' => 'initiated',
                    'razorpay_order_id' => $subscriptionId,
                    'amount' => $order->total_amount,
                    'message' => "PayPal recurring subscription session created: {$subscriptionId} (Plan: {$planId})",
                    'payload' => $data,
                ]);

                return [
                    'success' => true,
                    'checkout_id' => $subscriptionId,
                    'redirect_url' => $approvalUrl,
                    'is_sandbox' => $this->gateway->is_sandbox,
                    'is_subscription' => true,
                    'raw' => $data,
                ];
            }

            $errMsg = $data['message'] ?? 'Failed to initiate PayPal subscription agreement.';
            if (!empty($data['details'][0]['description'])) {
                $errMsg .= ' (' . $data['details'][0]['description'] . ')';
            }

            PaymentLog::create([
                'order_id' => $order->id,
                'order_number' => $order->order_number ?? (string) $order->id,
                'gateway' => 'paypal',
                'event_type' => 'initiate_subscription',
                'status' => 'failed',
                'amount' => $order->total_amount,
                'message' => $errMsg,
                'payload' => $data,
            ]);

            return [
                'success' => false,
                'message' => $errMsg,
                'raw' => $data,
            ];
        } catch (\Throwable $e) {
            Log::error('PayPal initiateSubscriptionCheckout error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'PayPal subscription error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Standard One-Time PayPal Checkout (/v2/checkout/orders)
     */
    protected function initiateStandardCheckout(Order $order, float $amount, string $currency, string $token, string $returnUrl, string $cancelUrl): array
    {
        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => (string) ($order->order_number ?? $order->id),
                    'description' => "Grass Florist Order #{$order->order_number}",
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => number_format($amount, 2, '.', ''),
                    ],
                ],
            ],
            'application_context' => [
                'brand_name' => 'Grass Florist',
                'landing_page' => 'BILLING',
                'user_action' => 'PAY_NOW',
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
            ],
        ];

        try {
            $response = Http::withToken($token)
                ->timeout(12)
                ->post("{$this->baseUrl}/v2/checkout/orders", $payload);

            $data = $response->json();

            if ($response->successful() && !empty($data['id'])) {
                $order->update(['razorpay_order_id' => $data['id']]);

                $approvalUrl = null;
                foreach ($data['links'] ?? [] as $link) {
                    if (($link['rel'] ?? '') === 'approve') {
                        $approvalUrl = $link['href'];
                        break;
                    }
                }

                // Log initiation
                PaymentLog::create([
                    'order_id' => $order->id,
                    'order_number' => $order->order_number ?? (string) $order->id,
                    'gateway' => 'paypal',
                    'event_type' => 'initiate',
                    'status' => 'initiated',
                    'razorpay_order_id' => $data['id'],
                    'amount' => $order->total_amount,
                    'message' => "PayPal standard one-time session initiated: {$data['id']}",
                    'payload' => $data,
                ]);

                return [
                    'success' => true,
                    'checkout_id' => $data['id'],
                    'redirect_url' => $approvalUrl,
                    'is_sandbox' => $this->gateway->is_sandbox,
                    'is_subscription' => false,
                    'raw' => $data,
                ];
            }

            $errMsg = $data['message'] ?? 'PayPal order initiation failed.';
            PaymentLog::create([
                'order_id' => $order->id,
                'order_number' => $order->order_number ?? (string) $order->id,
                'gateway' => 'paypal',
                'event_type' => 'initiate',
                'status' => 'failed',
                'amount' => $order->total_amount,
                'message' => $errMsg,
                'payload' => $data,
            ]);

            return [
                'success' => false,
                'message' => $errMsg,
                'raw' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('PayPal initiateStandardCheckout error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get or create a PayPal catalog product for recurring subscriptions.
     */
    protected function getOrCreateCatalogProduct(string $token): string
    {
        $cacheKey = 'paypal_catalog_prod_' . ($this->gateway->is_sandbox ? 'sandbox' : 'live');
        $cachedId = cache()->get($cacheKey);
        if ($cachedId) {
            return $cachedId;
        }

        $res = Http::withToken($token)->post("{$this->baseUrl}/v1/catalogs/products", [
            'name' => 'Grass Florist Recurring Subscription',
            'type' => 'SERVICE',
            'description' => 'Automated flower delivery recurring subscription',
        ]);

        $data = $res->json() ?? [];
        if ($res->successful() && !empty($data['id'])) {
            cache()->forever($cacheKey, $data['id']);
            return $data['id'];
        }

        // List existing products fallback
        $listRes = Http::withToken($token)->get("{$this->baseUrl}/v1/catalogs/products?page_size=5");
        $products = $listRes->json()['products'] ?? [];
        if (!empty($products[0]['id'])) {
            cache()->forever($cacheKey, $products[0]['id']);
            return $products[0]['id'];
        }

        throw new \Exception('Failed to create or find PayPal Catalog Product for Subscriptions.');
    }

    /**
     * Get or create a PayPal billing plan for specified frequency and amount.
     */
    protected function getOrCreateBillingPlan(string $token, string $frequency, float $usdAmount): string
    {
        $productId = $this->getOrCreateCatalogProduct($token);
        $amountStr = number_format($usdAmount, 2, '.', '');
        $freqKey = strtolower($frequency ?: 'weekly');

        $intervalUnit = 'WEEK';
        $intervalCount = 1;

        if ($freqKey === 'biweekly') {
            $intervalUnit = 'WEEK';
            $intervalCount = 2;
        } elseif ($freqKey === 'monthly') {
            $intervalUnit = 'MONTH';
            $intervalCount = 1;
        }

        $cacheKey = "paypal_plan_{$productId}_{$freqKey}_{$amountStr}_" . ($this->gateway->is_sandbox ? 'sandbox' : 'live');
        $cachedPlanId = cache()->get($cacheKey);
        if ($cachedPlanId) {
            return $cachedPlanId;
        }

        $planRes = Http::withToken($token)->post("{$this->baseUrl}/v1/billing/plans", [
            'product_id' => $productId,
            'name' => "Grass Florist " . ucfirst($freqKey) . " (\${$amountStr} USD)",
            'description' => "Grass Florist {$freqKey} delivery subscription at \${$amountStr} USD",
            'status' => 'ACTIVE',
            'billing_cycles' => [
                [
                    'frequency' => [
                        'interval_unit' => $intervalUnit,
                        'interval_count' => $intervalCount,
                    ],
                    'tenure_type' => 'REGULAR',
                    'sequence' => 1,
                    'total_cycles' => 0,
                    'pricing_scheme' => [
                        'fixed_price' => [
                            'value' => $amountStr,
                            'currency_code' => 'USD',
                        ],
                    ],
                ],
            ],
            'payment_preferences' => [
                'auto_bill_outstanding' => true,
                'setup_fee_failure_action' => 'CONTINUE',
                'payment_failure_threshold' => 3,
            ],
        ]);

        $planData = $planRes->json() ?? [];
        if ($planRes->successful() && !empty($planData['id'])) {
            cache()->forever($cacheKey, $planData['id']);
            return $planData['id'];
        }

        throw new \Exception('Failed to create PayPal billing plan: ' . ($planData['message'] ?? json_encode($planData)));
    }

    /**
     * Capture / Verify PayPal Order or Subscription
     */
    public function verifyPayment(string $referenceId): array
    {
        $token = $this->getAccessToken();
        if (empty($token)) {
            return ['is_paid' => false, 'status' => 'unconfigured'];
        }

        try {
            // If referenceId is an internal Order number or ID, resolve the PayPal token/subscription from Order
            if (str_starts_with($referenceId, 'ORD-') || is_numeric($referenceId)) {
                $dbOrder = Order::where('order_number', $referenceId)->orWhere('id', $referenceId)->first();
                if ($dbOrder && !empty($dbOrder->razorpay_order_id)) {
                    $referenceId = $dbOrder->razorpay_order_id;
                }
            }

            // Case 1: If referenceId is a PayPal Subscription ID (starts with I-)
            if (str_starts_with($referenceId, 'I-')) {
                return $this->verifySubscriptionPayment($referenceId, $token);
            }

            // Case 2: Standard One-Time Order Verification
            return $this->verifyStandardOrderPayment($referenceId, $token);
        } catch (\Throwable $e) {
            Log::error('PayPal verifyPayment error: ' . $e->getMessage());
            return [
                'is_paid' => false,
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify an active PayPal Subscription Agreement (/v1/billing/subscriptions/{id})
     */
    protected function verifySubscriptionPayment(string $subId, string $token): array
    {
        $res = Http::timeout(10)->withToken($token)
            ->get("{$this->baseUrl}/v1/billing/subscriptions/{$subId}");

        $subData = $res->json() ?? [];
        $status = strtoupper($subData['status'] ?? '');
        $isPaid = in_array($status, ['ACTIVE', 'APPROVED']);

        // Check if there are transactions recorded for this subscription
        $txId = $subId;
        $start = gmdate('Y-m-d\TH:i:s\Z', strtotime('-7 days'));
        $end = gmdate('Y-m-d\TH:i:s\Z');
        $txRes = Http::timeout(10)->withToken($token)
            ->get("{$this->baseUrl}/v1/billing/subscriptions/{$subId}/transactions?start_time={$start}&end_time={$end}");

        if ($txRes->successful()) {
            $transactions = $txRes->json()['transactions'] ?? [];
            if (!empty($transactions[0]['id'])) {
                $txId = $transactions[0]['id'];
            }
        }

        return [
            'is_paid' => $isPaid,
            'status' => strtolower($status),
            'transaction_id' => $txId,
            'payment_token' => $subId,
            'raw' => array_merge($subData, [
                'subscription_id' => $subId,
                'transactions_check' => $txRes->json() ?? [],
            ]),
        ];
    }

    /**
     * Verify a standard One-Time Order (/v2/checkout/orders/{id}/capture)
     */
    protected function verifyStandardOrderPayment(string $referenceId, string $token): array
    {
        // ⚡ 1. Direct Capture attempt
        $capRes = Http::timeout(8)
            ->withToken($token)
            ->withBody('{}', 'application/json')
            ->post("{$this->baseUrl}/v2/checkout/orders/{$referenceId}/capture");

        $capData = $capRes->json() ?? [];
        $status = strtoupper($capData['status'] ?? '');

        if ($capRes->successful() && $status === 'COMPLETED') {
            $txId = $capData['purchase_units'][0]['payments']['captures'][0]['id'] ?? $referenceId;
            return [
                'is_paid' => true,
                'status' => 'completed',
                'transaction_id' => $txId,
                'amount' => isset($capData['purchase_units'][0]['amount']['value']) ? (float) $capData['purchase_units'][0]['amount']['value'] : null,
                'raw' => $capData,
            ];
        }

        // ⚡ 2. Check if already captured earlier
        $issues = array_column($capData['details'] ?? [], 'issue');
        if (in_array('ORDER_ALREADY_CAPTURED', $issues)) {
            return [
                'is_paid' => true,
                'status' => 'completed',
                'transaction_id' => $referenceId,
                'raw' => $capData,
            ];
        }

        // ⚡ 3. Fallback: query order status
        $getRes = Http::timeout(8)->withToken($token)->get("{$this->baseUrl}/v2/checkout/orders/{$referenceId}");
        $orderData = $getRes->json() ?? [];
        $status = strtoupper($orderData['status'] ?? '');

        $isPaid = ($status === 'COMPLETED');
        $txId = $orderData['purchase_units'][0]['payments']['captures'][0]['id'] ?? $referenceId;

        return [
            'is_paid' => $isPaid,
            'status' => strtolower($status),
            'transaction_id' => $txId,
            'amount' => isset($orderData['purchase_units'][0]['amount']['value']) ? (float) $orderData['purchase_units'][0]['amount']['value'] : null,
            'raw' => $orderData,
        ];
    }

    /**
     * Handle PayPal Webhook
     */
    public function handleWebhook(array $payload, array $headers = []): array
    {
        $eventType = $payload['event_type'] ?? '';
        $resource = $payload['resource'] ?? [];
        $orderNumber = $resource['purchase_units'][0]['reference_id'] ?? null;
        $orderId = $resource['id'] ?? null;

        $isPaid = in_array($eventType, ['PAYMENT.CAPTURE.COMPLETED', 'CHECKOUT.ORDER.COMPLETED']);

        return [
            'handled' => true,
            'checkout_id' => $orderId,
            'order_number' => $orderNumber,
            'is_paid' => $isPaid,
            'transaction_id' => $orderId,
            'raw' => $payload,
        ];
    }

    protected function getFrontendUrl(array $options = []): string
    {
        if (!empty($options['frontend_url'])) {
            return rtrim($options['frontend_url'], '/');
        }
        $origin = request()->headers->get('origin') ?? request()->headers->get('referer');
        if ($origin) {
            $parsed = parse_url($origin);
            if (!empty($parsed['scheme']) && !empty($parsed['host'])) {
                $port = !empty($parsed['port']) ? ":{$parsed['port']}" : '';
                return "{$parsed['scheme']}://{$parsed['host']}{$port}";
            }
        }
        $envUrl = env('FRONTEND_URL', 'http://localhost:3000');
        if (app()->environment('local') && !str_contains($envUrl, 'localhost')) {
            return 'http://localhost:3000';
        }
        return rtrim($envUrl, '/');
    }

    /**
     * Charge a recurring subscription delivery token via PayPal.
     */
    public function chargeRecurringToken(Order $order, string $token, array $options = []): array
    {
        $conversionRate = (float) ($order->exchange_rate ?: 0.2667);
        if ($conversionRate <= 0 || $conversionRate >= 1.0) {
            $conversionRate = 0.2667;
        }

        $sarAmount = (float) ($order->sar_amount ?: $order->total_amount);
        $amount = round($sarAmount * $conversionRate, 2);

        $order->update([
            'currency' => 'USD',
            'currency_amount' => $amount,
            'sar_amount' => $sarAmount,
            'exchange_rate' => $conversionRate,
            'total_amount' => $sarAmount,
            'meta_data' => array_merge((array) ($order->meta_data ?? []), [
                'currency' => 'SAR',
                'exchange_rate' => $conversionRate,
                'sar_total' => $sarAmount,
                'currency_total' => $sarAmount,
                'usd_total' => $amount,
                'paypal_charge_usd' => $amount,
            ]),
        ]);

        $accessToken = $this->getAccessToken();
        if (empty($accessToken)) {
            Log::error("PayPal access token missing during chargeRecurringToken for order #{$order->order_number}");
            return [
                'success' => false,
                'message' => 'PayPal credentials / access token unavailable.',
            ];
        }

        // 🔄 Case 1: Active PayPal Subscription Agreement (starts with I-)
        if (str_starts_with($token, 'I-')) {
            return $this->chargeSubscriptionAgreement($order, $token, $amount, $accessToken);
        }

        try {
            $payload = [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'reference_id' => (string) ($order->order_number ?? $order->id),
                        'description' => "Grass Florist Recurring Subscription Order #{$order->order_number}",
                        'amount' => [
                            'currency_code' => 'USD',
                            'value' => number_format($amount, 2, '.', ''),
                        ],
                    ],
                ],
            ];

            if (!empty($token)) {
                $payload['payment_source'] = [
                    'token' => [
                        'id' => $token,
                        'type' => 'PAYMENT_METHOD_TOKEN',
                    ],
                ];
            }

            $response = Http::withToken($accessToken)
                ->timeout(10)
                ->post("{$this->baseUrl}/v2/checkout/orders", $payload);

            $data = $response->json() ?? [];
            $status = strtoupper($data['status'] ?? '');
            $isPaid = ($response->successful() && in_array($status, ['COMPLETED', 'APPROVED']));
            $txId = $data['purchase_units'][0]['payments']['captures'][0]['id'] ?? ($data['id'] ?? null);

            $errMsg = $data['message'] ?? 'PayPal recurring charge failed.';
            if (!empty($data['details'][0]['description'])) {
                $errMsg .= ' (' . $data['details'][0]['description'] . ')';
            }

            PaymentLog::create([
                'order_id' => $order->id,
                'order_number' => $order->order_number ?? (string) $order->id,
                'gateway' => 'paypal',
                'event_type' => 'recurring_charge',
                'status' => $isPaid ? 'success' : 'failed',
                'razorpay_payment_id' => $isPaid ? $txId : null,
                'amount' => $order->total_amount,
                'message' => $isPaid ? "PayPal recurring payment charged successfully: {$txId}" : "PayPal recurring charge failed: {$errMsg}",
                'payload' => $data,
            ]);

            return [
                'success' => $isPaid,
                'transaction_id' => $isPaid ? $txId : null,
                'message' => $isPaid ? 'PayPal recurring charge successful' : $errMsg,
                'raw' => $data,
            ];
        } catch (\Throwable $e) {
            Log::error('PayPal chargeRecurringToken exception: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'PayPal recurring payment exception: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Verify or capture recurring cycle from PayPal Subscription Agreement
     */
    protected function chargeSubscriptionAgreement(Order $order, string $subId, float $amount, string $accessToken): array
    {
        try {
            // 1. Get Subscription Status from PayPal
            $subRes = Http::timeout(10)->withToken($accessToken)
                ->get("{$this->baseUrl}/v1/billing/subscriptions/{$subId}");
            $subData = $subRes->json() ?? [];
            $status = strtoupper($subData['status'] ?? '');

            if (!in_array($status, ['ACTIVE', 'APPROVED'])) {
                $errMsg = "PayPal Subscription agreement {$subId} is not active (Status: {$status}).";
                return [
                    'success' => false,
                    'message' => $errMsg,
                    'raw' => $subData,
                ];
            }

            // 2. Query transactions recorded by PayPal for this subscription
            $start = gmdate('Y-m-d\TH:i:s\Z', strtotime('-7 days'));
            $end = gmdate('Y-m-d\TH:i:s\Z');
            $txRes = Http::timeout(10)->withToken($accessToken)
                ->get("{$this->baseUrl}/v1/billing/subscriptions/{$subId}/transactions?start_time={$start}&end_time={$end}");

            $txData = $txRes->json() ?? [];
            $transactions = $txData['transactions'] ?? [];

            $completedTx = null;
            foreach ($transactions as $tx) {
                if (strtoupper($tx['status'] ?? '') === 'COMPLETED' && !empty($tx['id'])) {
                    // Check if this transaction has ALREADY been assigned to another order
                    $alreadyUsed = Order::where('razorpay_payment_id', $tx['id'])
                        ->where('id', '!=', $order->id)
                        ->exists();

                    if (!$alreadyUsed) {
                        $completedTx = $tx;
                        break;
                    }
                }
            }

            if (!empty($completedTx['id'])) {
                $txId = $completedTx['id'];
                PaymentLog::create([
                    'order_id' => $order->id,
                    'order_number' => $order->order_number ?? (string) $order->id,
                    'gateway' => 'paypal',
                    'event_type' => 'subscription_cycle_verified',
                    'status' => 'success',
                    'razorpay_payment_id' => $txId,
                    'amount' => $order->total_amount,
                    'message' => "PayPal subscription transaction verified: {$txId}",
                    'payload' => $completedTx,
                ]);

                return [
                    'success' => true,
                    'transaction_id' => $txId,
                    'message' => "PayPal recurring cycle payment verified ({$txId})",
                    'raw' => $completedTx,
                ];
            }

            // 3. Attempt capture on outstanding balance if not auto-captured yet
            $capRes = Http::timeout(10)->withToken($accessToken)
                ->post("{$this->baseUrl}/v1/billing/subscriptions/{$subId}/capture", [
                    'note' => "Recurring Delivery Order #{$order->order_number}",
                    'capture_type' => 'OUTSTANDING_BALANCE',
                    'amount' => [
                        'currency_code' => 'USD',
                        'value' => number_format($amount, 2, '.', ''),
                    ],
                ]);

            $capData = $capRes->json() ?? [];
            if ($capRes->successful() && strtoupper($capData['status'] ?? '') === 'COMPLETED') {
                $capturedId = $capData['id'] ?? $subId;
                PaymentLog::create([
                    'order_id' => $order->id,
                    'order_number' => $order->order_number ?? (string) $order->id,
                    'gateway' => 'paypal',
                    'event_type' => 'subscription_capture',
                    'status' => 'success',
                    'razorpay_payment_id' => $capturedId,
                    'amount' => $order->total_amount,
                    'message' => "PayPal subscription outstanding balance captured: {$capturedId}",
                    'payload' => $capData,
                ]);

                return [
                    'success' => true,
                    'transaction_id' => $capturedId,
                    'message' => "PayPal outstanding balance captured: {$capturedId}",
                    'raw' => $capData,
                ];
            }

            // 4. If no money has been charged by PayPal, do NOT fake paid status!
            $nextBilling = $subData['billing_info']['next_billing_time'] ?? null;
            $msg = $nextBilling
                ? "PayPal subscription is active. Next scheduled auto-debit is {$nextBilling}. Payment not charged yet."
                : ($capData['message'] ?? 'No completed payment recorded on PayPal subscription for this cycle.');

            PaymentLog::create([
                'order_id' => $order->id,
                'order_number' => $order->order_number ?? (string) $order->id,
                'gateway' => 'paypal',
                'event_type' => 'recurring_charge',
                'status' => 'pending',
                'amount' => $order->total_amount,
                'message' => $msg,
                'payload' => array_merge($subData, $capData),
            ]);

            return [
                'success' => false,
                'message' => $msg,
                'raw' => array_merge($subData, $capData),
            ];
        } catch (\Throwable $e) {
            Log::error("chargeSubscriptionAgreement exception for {$subId}: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'PayPal subscription charge exception: ' . $e->getMessage(),
            ];
        }
    }
}
