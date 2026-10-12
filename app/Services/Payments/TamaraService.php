<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\PaymentLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TamaraService implements PaymentGatewayInterface
{
    protected PaymentGateway $gateway;
    protected string $baseUrl;
    protected array $credentials;

    public function __construct()
    {
        $this->gateway = PaymentGateway::where('code', 'tamara')->first() ?? new PaymentGateway();
        $this->credentials = $this->gateway->credentials ?? [];
        $isSandbox = $this->gateway->is_sandbox ?? true;

        $this->baseUrl = $isSandbox ? 'https://api-sandbox.tamara.co' : 'https://api.tamara.co';
    }

    public function getApiToken(): string
    {
        return $this->credentials['api_token'] ?? '';
    }

    public function initiateCheckout(Order $order, array $options = []): array
    {
        $apiToken = $this->getApiToken();

        if (empty($apiToken)) {
            $msg = 'Tamara API Token not configured in Admin Settings.';
            PaymentLog::create([
                'order_id' => $order->id,
                'order_number' => $order->order_number ?? (string)$order->id,
                'gateway' => 'tamara',
                'event_type' => 'initiate',
                'status' => 'failed',
                'amount' => $order->total_amount,
                'message' => $msg,
                'payload' => ['error' => 'missing_credentials'],
            ]);

            return [
                'success' => false,
                'message' => $msg,
            ];
        }

        $items = [];
        if ($order->orderItems) {
            foreach ($order->orderItems as $item) {
                $items[] = [
                    'name' => $item->product_name ?? 'Fresh Flowers Arrangement',
                    'type' => 'Flowers',
                    'reference_id' => (string)($item->product_id ?? $item->id),
                    'sku' => (string)($item->product_id ?? 'FLOWER-01'),
                    'quantity' => (int)($item->quantity ?? 1),
                    'total_amount' => [
                        'amount' => (float)($item->price ?? 0),
                        'currency' => 'SAR',
                    ],
                ];
            }
        }

        if (empty($items)) {
            $items[] = [
                'name' => 'Flowers & Gifts Bouquet',
                'type' => 'Flowers',
                'reference_id' => (string)$order->id,
                'sku' => 'FLOWER-BOUQUET',
                'quantity' => 1,
                'total_amount' => [
                    'amount' => (float)($order->sar_amount ?: $order->total_amount),
                    'currency' => 'SAR',
                ],
            ];
        }

        $locale = $options['locale'] ?? app()->getLocale();
        $prefix = $locale === 'ar' ? '' : '/en';
        $frontendBase = $this->getFrontendUrl($options);
        $returnUrl = $options['return_url'] ?? "{$frontendBase}{$prefix}/checkout?order_number={$order->order_number}&order_id={$order->id}&gateway=tamara&payment_success=1";
        $cancelUrl = $options['cancel_url'] ?? "{$frontendBase}{$prefix}/checkout?order_number={$order->order_number}&order_id={$order->id}&gateway=tamara&cancelled=1";
        $failureUrl = $options['failure_url'] ?? "{$frontendBase}{$prefix}/checkout?order_number={$order->order_number}&order_id={$order->id}&gateway=tamara&failed=1";

        $payload = [
            'order_reference_id' => (string)($order->order_number ?? $order->id),
            'order_number' => (string)($order->order_number ?? $order->id),
            'total_amount' => [
                'amount' => (float)($order->sar_amount ?: $order->total_amount),
                'currency' => 'SAR',
            ],
            'description' => "Grass Florist Gift Order #{$order->order_number}",
            'country_code' => 'SA',
            'payment_type' => 'PAY_BY_INSTALMENTS',
            'instalments' => 3,
            'items' => $items,
            'consumer' => [
                'first_name' => $order->first_name ?? 'Customer',
                'last_name' => $order->last_name ?? 'Grass',
                'phone_number' => $order->customer_phone ?? $order->recipient_phone ?? '+966500000000',
                'email' => $order->email ?? 'customer@grassflorist.com',
            ],
            'shipping_address' => [
                'first_name' => $order->recipient_name ?? $order->first_name ?? 'Customer',
                'last_name' => $order->last_name ?? 'Florist',
                'line1' => $order->address ?? 'Jeddah City, Saudi Arabia',
                'city' => $order->city ?? 'Jeddah',
                'country_code' => 'SA',
                'phone_number' => $order->recipient_phone ?? '+966500000000',
            ],
            'merchant_url' => [
                'success' => $returnUrl,
                'cancel' => $cancelUrl,
                'failure' => $failureUrl,
                'notification' => url("/api/v1/webhooks/tamara"),
            ],
        ];

        try {
            $response = Http::withToken($apiToken)->post("{$this->baseUrl}/checkout", $payload);
            $data = $response->json();

            if ($response->successful() && !empty($data['checkout_id'])) {
                PaymentLog::create([
                    'order_id' => $order->id,
                    'order_number' => $order->order_number ?? (string)$order->id,
                    'gateway' => 'tamara',
                    'event_type' => 'initiate',
                    'status' => 'initiated',
                    'razorpay_order_id' => $data['checkout_id'],
                    'amount' => $order->total_amount,
                    'message' => "Tamara checkout session created: {$data['checkout_id']}",
                    'payload' => $data,
                ]);

                return [
                    'success' => true,
                    'checkout_id' => $data['checkout_id'],
                    'redirect_url' => $data['checkout_url'] ?? null,
                    'is_sandbox' => $this->gateway->is_sandbox,
                    'raw' => $data,
                ];
            }

            $errMsg = $data['message'] ?? $data['errors'][0]['error'] ?? 'Tamara checkout session creation failed.';
            PaymentLog::create([
                'order_id' => $order->id,
                'order_number' => $order->order_number ?? (string)$order->id,
                'gateway' => 'tamara',
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
            Log::error('Tamara initiateCheckout error: ' . $e->getMessage());

            PaymentLog::create([
                'order_id' => $order->id,
                'order_number' => $order->order_number ?? (string)$order->id,
                'gateway' => 'tamara',
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

    public function verifyPayment(string $referenceId): array
    {
        $apiToken = $this->getApiToken();
        if (empty($apiToken)) {
            return ['is_paid' => false, 'status' => 'unconfigured'];
        }

        try {
            $response = Http::withToken($apiToken)->get("{$this->baseUrl}/orders/reference-id/{$referenceId}");
            $data = $response->json();

            $status = strtolower($data['status'] ?? '');
            $isPaid = in_array($status, ['authorised', 'approved', 'fully_captured', 'captured']);

            return [
                'is_paid' => $isPaid,
                'status' => $status,
                'transaction_id' => $data['order_id'] ?? $referenceId,
                'amount' => isset($data['total_amount']['amount']) ? (float)$data['total_amount']['amount'] : null,
                'raw' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Tamara verifyPayment error: ' . $e->getMessage());
            return ['is_paid' => false, 'status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function handleWebhook(array $payload, array $headers = []): array
    {
        $orderReferenceId = $payload['order_reference_id'] ?? null;
        $orderId = $payload['order_id'] ?? null;
        $event = strtolower($payload['event_type'] ?? $payload['status'] ?? '');

        $isPaid = in_array($event, ['order_approved', 'order_authorised', 'order_captured', 'authorised', 'captured', 'approved']);

        return [
            'handled' => true,
            'checkout_id' => $orderId,
            'order_number' => $orderReferenceId,
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
}
