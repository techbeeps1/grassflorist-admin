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
                $expiresIn = (int)($response->json('expires_in') ?? 32400);
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
                'order_number' => $order->order_number ?? (string)$order->id,
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
        $conversionRate = (float)($order->exchange_rate ?: 0.2667);
        if ($conversionRate <= 0 || $conversionRate >= 1.0) {
            $conversionRate = (float)(\App\Models\GlobalSetting::first()?->sar_to_usd_rate ?: 0.2667);
        }
        if ($conversionRate <= 0 || $conversionRate >= 1.0) {
            $conversionRate = 0.2667;
        }

        $sarAmount = (float)($order->sar_amount ?: $order->total_amount);
        $amount = (float)($order->currency_amount ?? 0);

        // If currency_amount was not set, was in SAR (>= sarAmount), or order wasn't previously USD
        if ($amount <= 0 || $amount >= $sarAmount || strtoupper((string)$order->currency) !== 'USD') {
            $amount = round($sarAmount * $conversionRate, 2);
        }

        // Freeze exchange_rate, currency_amount and sar_amount on order
        $updateData = [
            'exchange_rate' => $conversionRate,
            'sar_amount' => $sarAmount,
        ];

        if (strtoupper((string)$order->currency) === 'USD') {
            $updateData['currency'] = 'USD';
            $updateData['currency_amount'] = $amount;
        } else {
            $updateData['currency'] = 'SAR';
            $updateData['currency_amount'] = $sarAmount;
        }

        $metaData = (array)($order->meta_data ?? []);
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
                    'reference_id' => (string)($order->order_number ?? $order->id),
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
                    'order_number' => $order->order_number ?? (string)$order->id,
                    'gateway' => 'paypal',
                    'event_type' => 'initiate',
                    'status' => 'initiated',
                    'razorpay_order_id' => $data['id'],
                    'amount' => $order->total_amount,
                    'message' => "PayPal session initiated: {$data['id']}",
                    'payload' => $data,
                ]);

                return [
                    'success' => true,
                    'checkout_id' => $data['id'],
                    'redirect_url' => $approvalUrl,
                    'is_sandbox' => $this->gateway->is_sandbox,
                    'raw' => $data,
                ];
            }

            $errMsg = $data['message'] ?? 'PayPal order initiation failed.';
            PaymentLog::create([
                'order_id' => $order->id,
                'order_number' => $order->order_number ?? (string)$order->id,
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
            Log::error('PayPal initiateCheckout error: ' . $e->getMessage());

            PaymentLog::create([
                'order_id' => $order->id,
                'order_number' => $order->order_number ?? (string)$order->id,
                'gateway' => 'paypal',
                'event_type' => 'initiate',
                'status' => 'error',
                'amount' => $order->total_amount,
                'message' => $e->getMessage(),
                'payload' => ['exception' => $e->getMessage()],
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Capture / Verify PayPal Order
     */
    public function verifyPayment(string $referenceId): array
    {
        $token = $this->getAccessToken();
        if (empty($token)) {
            return ['is_paid' => false, 'status' => 'unconfigured'];
        }

        try {
            // ⚡ 1. Direct Capture attempt (1 single fast API call instead of 2 sequential roundtrips)
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
                    'amount' => isset($capData['purchase_units'][0]['amount']['value']) ? (float)$capData['purchase_units'][0]['amount']['value'] : null,
                    'raw' => $capData,
                ];
            }

            // ⚡ 2. Check if already captured earlier by webhook or concurrent call
            $issues = array_column($capData['details'] ?? [], 'issue');
            if (in_array('ORDER_ALREADY_CAPTURED', $issues)) {
                return [
                    'is_paid' => true,
                    'status' => 'completed',
                    'transaction_id' => $referenceId,
                    'raw' => $capData,
                ];
            }

            // ⚡ 3. Fallback: query order status only if capture wasn't processed
            $getRes = Http::timeout(8)->withToken($token)->get("{$this->baseUrl}/v2/checkout/orders/{$referenceId}");
            $orderData = $getRes->json() ?? [];
            $status = strtoupper($orderData['status'] ?? '');

            $isPaid = ($status === 'COMPLETED');
            $txId = $orderData['purchase_units'][0]['payments']['captures'][0]['id'] ?? $referenceId;

            return [
                'is_paid' => $isPaid,
                'status' => strtolower($status),
                'transaction_id' => $txId,
                'amount' => isset($orderData['purchase_units'][0]['amount']['value']) ? (float)$orderData['purchase_units'][0]['amount']['value'] : null,
                'raw' => $orderData,
            ];
        } catch (\Exception $e) {
            Log::error('PayPal verifyPayment error: ' . $e->getMessage());
            return [
                'is_paid' => false,
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
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
        $conversionRate = (float)($order->exchange_rate ?: 0.2667);
        $sarAmount = (float)($order->sar_amount ?: $order->total_amount);
        $amount = round($sarAmount * $conversionRate, 2);

        $order->update([
            'currency' => 'USD',
            'currency_amount' => $amount,
            'sar_amount' => $sarAmount,
            'exchange_rate' => $conversionRate,
        ]);

        $accessToken = $this->getAccessToken();
        if (empty($accessToken)) {
            Log::error("PayPal access token missing during chargeRecurringToken for order #{$order->order_number}");
            return [
                'success' => false,
                'message' => 'PayPal credentials / access token unavailable.',
            ];
        }

        try {
            $payload = [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'reference_id' => (string)($order->order_number ?? $order->id),
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
            $txId = $data['purchase_units'][0]['payments']['captures'][0]['id'] ?? ($data['id'] ?? ('PP_REC_' . time()));

            PaymentLog::create([
                'order_id' => $order->id,
                'order_number' => $order->order_number ?? (string)$order->id,
                'gateway' => 'paypal',
                'event_type' => 'recurring_charge',
                'status' => $isPaid ? 'success' : 'failed',
                'razorpay_payment_id' => $txId,
                'amount' => $order->total_amount,
                'message' => $isPaid ? "PayPal recurring payment charged successfully: {$txId}" : "PayPal recurring charge failed.",
                'payload' => $data,
            ]);

            return [
                'success' => $isPaid,
                'transaction_id' => $txId,
                'message' => $isPaid ? 'PayPal recurring charge successful' : ($data['message'] ?? 'Payment declined'),
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
}
