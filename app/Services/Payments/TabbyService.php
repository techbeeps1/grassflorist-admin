<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\PaymentLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TabbyService implements PaymentGatewayInterface
{
    protected PaymentGateway $gateway;
    protected string $baseUrl;
    protected array $credentials;

    public function __construct()
    {
        $this->gateway = PaymentGateway::where('code', 'tabby')->first() ?? new PaymentGateway();
        $this->credentials = $this->gateway->credentials ?? [];
        $this->baseUrl = 'https://api.tabby.ai/api/v2';
    }

    public function getPublicKey(): string
    {
        return $this->credentials['public_key'] ?? '';
    }

    public function getSecretKey(): string
    {
        return $this->credentials['secret_key'] ?? '';
    }

    public function getMerchantCode(): string
    {
        return $this->credentials['merchant_code'] ?? 'grassflorist';
    }

    public function initiateCheckout(Order $order, array $options = []): array
    {
        $secretKey = $this->getSecretKey();
        $merchantCode = $this->getMerchantCode();

        if (empty($secretKey)) {
            $msg = 'Tabby Secret Key not configured in Admin Settings.';
            PaymentLog::create([
                'order_id' => $order->id,
                'order_number' => $order->order_number ?? (string)$order->id,
                'gateway' => 'tabby',
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
                    'title' => $item->product_name ?? 'Flower Bouquet',
                    'quantity' => (int)($item->quantity ?? 1),
                    'unit_price' => number_format((float)($item->price ?? 0), 2, '.', ''),
                    'category' => 'Flowers & Gifts',
                ];
            }
        }

        if (empty($items)) {
            $items[] = [
                'title' => 'Fresh Flowers & Gifts Arrangement',
                'quantity' => 1,
                'unit_price' => number_format((float)($order->sar_amount ?: $order->total_amount), 2, '.', ''),
                'category' => 'Flowers & Gifts',
            ];
        }

        $locale = $options['locale'] ?? app()->getLocale();
        $prefix = $locale === 'ar' ? '' : '/en';
        $frontendBase = $this->getFrontendUrl($options);
        $returnUrl = $options['return_url'] ?? "{$frontendBase}{$prefix}/checkout?order_number={$order->order_number}&order_id={$order->id}&gateway=tabby&payment_success=1";
        $cancelUrl = $options['cancel_url'] ?? "{$frontendBase}{$prefix}/checkout?order_number={$order->order_number}&order_id={$order->id}&gateway=tabby&cancelled=1";
        $failureUrl = $options['failure_url'] ?? "{$frontendBase}{$prefix}/checkout?order_number={$order->order_number}&order_id={$order->id}&gateway=tabby&failed=1";

        $payload = [
            'payment' => [
                'amount' => number_format((float)($order->sar_amount ?: $order->total_amount), 2, '.', ''),
                'currency' => 'SAR',
                'description' => "Grass Florist Order #{$order->order_number}",
                'buyer' => [
                    'phone' => $order->customer_phone ?? $order->recipient_phone ?? '+966500000000',
                    'email' => $order->email ?? 'customer@grassflorist.com',
                    'name' => trim(($order->first_name ?? 'Valued') . ' ' . ($order->last_name ?? 'Customer')),
                ],
                'shipping_address' => [
                    'city' => $order->city ?? 'Jeddah',
                    'address' => $order->address ?? 'Jeddah, Saudi Arabia',
                    'zip' => $order->zip_code ?? '22231',
                ],
                'order' => [
                    'reference_id' => (string)($order->order_number ?? $order->id),
                    'items' => $items,
                    'shipping_amount' => number_format((float)($order->shipping_amount ?? 0), 2, '.', ''),
                    'tax_amount' => number_format((float)($order->tax_amount ?? 0), 2, '.', ''),
                ],
                'buyer_history' => [
                    'registered_since' => date('c'),
                    'loyalty_level' => 0,
                ],
            ],
            'lang' => app()->getLocale() === 'ar' ? 'ar' : 'en',
            'merchant_code' => $merchantCode,
            'merchant_urls' => [
                'success' => $returnUrl,
                'cancel' => $cancelUrl,
                'failure' => $failureUrl,
            ],
        ];

        try {
            $response = Http::timeout(5)->withToken($secretKey)
                ->post("{$this->baseUrl}/checkout", $payload);

            $data = $response->json();

            if ($response->successful() && !empty($data['id'])) {
                $webUrl = $data['configuration']['available_products']['installments'][0]['web_url'] ?? $data['web_url'] ?? null;

                PaymentLog::create([
                    'order_id' => $order->id,
                    'order_number' => $order->order_number ?? (string)$order->id,
                    'gateway' => 'tabby',
                    'event_type' => 'initiate',
                    'status' => 'initiated',
                    'razorpay_order_id' => $data['id'],
                    'amount' => $order->total_amount,
                    'message' => "Tabby checkout session created: {$data['id']}",
                    'payload' => $data,
                ]);

                return [
                    'success' => true,
                    'checkout_id' => $data['id'],
                    'redirect_url' => $webUrl,
                    'is_sandbox' => $this->gateway->is_sandbox,
                    'raw' => $data,
                ];
            }

            $errMsg = $data['error'] ?? $data['message'] ?? 'Tabby checkout session creation failed.';
            PaymentLog::create([
                'order_id' => $order->id,
                'order_number' => $order->order_number ?? (string)$order->id,
                'gateway' => 'tabby',
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
            Log::error('Tabby initiateCheckout error: ' . $e->getMessage());

            PaymentLog::create([
                'order_id' => $order->id,
                'order_number' => $order->order_number ?? (string)$order->id,
                'gateway' => 'tabby',
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
        $secretKey = $this->getSecretKey();
        if (empty($secretKey)) {
            return ['is_paid' => false, 'status' => 'unconfigured'];
        }

        try {
            $response = Http::timeout(5)->withToken($secretKey)->get("{$this->baseUrl}/payments/{$referenceId}");
            $data = $response->json();

            $status = strtolower($data['status'] ?? '');
            $isPaid = in_array($status, ['authorized', 'captured', 'closed']);

            return [
                'is_paid' => $isPaid,
                'status' => $status,
                'transaction_id' => $data['id'] ?? $referenceId,
                'amount' => isset($data['amount']) ? (float)$data['amount'] : null,
                'raw' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Tabby verifyPayment error: ' . $e->getMessage());
            return ['is_paid' => false, 'status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function handleWebhook(array $payload, array $headers = []): array
    {
        $paymentId = $payload['id'] ?? null;
        $orderNumber = $payload['order']['reference_id'] ?? null;
        $status = strtolower($payload['status'] ?? '');
        $isPaid = in_array($status, ['authorized', 'captured', 'closed']);

        return [
            'handled' => true,
            'checkout_id' => $paymentId,
            'order_number' => $orderNumber,
            'is_paid' => $isPaid,
            'transaction_id' => $paymentId,
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
