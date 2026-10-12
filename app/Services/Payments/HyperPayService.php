<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\PaymentLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HyperPayService implements PaymentGatewayInterface
{
    protected PaymentGateway $gateway;
    protected string $baseUrl;
    protected array $credentials;

    public function __construct()
    {
        $this->gateway = PaymentGateway::where('code', 'hyperpay')->first() ?? new PaymentGateway();
        $this->credentials = $this->gateway->credentials ?? [];
        $isSandbox = $this->gateway->is_sandbox ?? true;

        $this->baseUrl = $isSandbox ? 'https://test.oppwa.com' : 'https://oppwa.com';
    }

    /**
     * Get appropriate Entity ID based on payment brand/method.
     */
    public function getEntityId(?string $brand = null): string
    {
        $brand = strtolower(trim((string)$brand));

        if (in_array($brand, ['mada', 'hyperpay_mada'])) {
            return $this->credentials['entity_id_mada'] ?? $this->credentials['entity_id_visa_master'] ?? '';
        }

        if (in_array($brand, ['applepay', 'apple_pay', 'hyperpay_applepay'])) {
            return $this->credentials['entity_id_applepay'] ?? $this->credentials['entity_id_visa_master'] ?? '';
        }

        if (in_array($brand, ['stcpay', 'stc_pay', 'hyperpay_stcpay'])) {
            return $this->credentials['entity_id_stcpay'] ?? $this->credentials['entity_id_visa_master'] ?? '';
        }

        return $this->credentials['entity_id_visa_master'] ?? $this->credentials['entity_id_mada'] ?? '';
    }

    public function getAccessToken(): string
    {
        return $this->credentials['access_token'] ?? '';
    }

    /**
     * Initiate HyperPay Checkout session
     */
    public function initiateCheckout(Order $order, array $options = []): array
    {
        $brand = $options['payment_brand'] ?? $order->payment_method ?? 'visa';
        $entityId = $this->getEntityId($brand);
        $token = $this->getAccessToken();

        if (empty($token) || empty($entityId)) {
            $msg = 'HyperPay credentials (Access Token or Entity ID) not configured in Admin Settings.';
            PaymentLog::create([
                'order_id' => $order->id,
                'order_number' => $order->order_number ?? (string)$order->id,
                'gateway' => 'hyperpay',
                'event_type' => 'initiate',
                'status' => 'failed',
                'amount' => $order->total_amount,
                'message' => $msg,
                'payload' => ['brand' => $brand, 'error' => 'missing_credentials'],
            ]);

            return [
                'success' => false,
                'message' => $msg,
            ];
        }

        $params = [
            'entityId' => $entityId,
            'amount' => number_format((float)($order->sar_amount ?: $order->total_amount), 2, '.', ''),
            'currency' => 'SAR',
            'paymentType' => 'DB', // Direct Debit / Purchase
            'merchantTransactionId' => (string)($order->order_number ?? $order->id),
            'customer.email' => $order->email ?? 'customer@grassflorist.com',
            'customer.givenName' => $order->first_name ?? 'Customer',
            'customer.surname' => $order->last_name ?? 'GrassFlorist',
            'billing.street1' => substr($order->address ?? 'Jeddah City', 0, 50),
            'billing.city' => $order->city ?? 'Jeddah',
            'billing.state' => $order->state ?? 'Makkah Region',
            'billing.country' => 'SA',
            'billing.postcode' => $order->zip_code ?? '22231',
        ];

        try {
            $response = Http::timeout(5)
                ->withToken($token)
                ->asForm()
                ->post("{$this->baseUrl}/v1/checkouts", $params);

            $data = $response->json();

            if ($response->successful() && !empty($data['id'])) {
                PaymentLog::create([
                    'order_id' => $order->id,
                    'order_number' => $order->order_number ?? (string)$order->id,
                    'gateway' => 'hyperpay',
                    'event_type' => 'initiate',
                    'status' => 'initiated',
                    'razorpay_order_id' => $data['id'],
                    'amount' => $order->total_amount,
                    'message' => "HyperPay checkout session created: {$data['id']} (brand: {$brand})",
                    'payload' => $data,
                ]);

                return [
                    'success' => true,
                    'checkout_id' => $data['id'],
                    'entity_id' => $entityId,
                    'is_sandbox' => $this->gateway->is_sandbox,
                    'script_url' => "{$this->baseUrl}/v1/paymentWidgets.js?checkoutId={$data['id']}",
                    'raw' => $data,
                ];
            }

            $errMsg = $data['result']['description'] ?? 'HyperPay checkout initiation failed.';
            PaymentLog::create([
                'order_id' => $order->id,
                'order_number' => $order->order_number ?? (string)$order->id,
                'gateway' => 'hyperpay',
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
            Log::error('HyperPay initiateCheckout error: ' . $e->getMessage());
            PaymentLog::create([
                'order_id' => $order->id,
                'order_number' => $order->order_number ?? (string)$order->id,
                'gateway' => 'hyperpay',
                'event_type' => 'initiate',
                'status' => 'error',
                'amount' => $order->total_amount,
                'message' => $e->getMessage(),
                'payload' => ['exception' => $e->getMessage()],
            ]);

            return [
                'success' => false,
                'message' => 'Exception in HyperPay initiateCheckout: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Query / Verify transaction status directly from HyperPay API
     */
    public function verifyPayment(string $referenceId, ?string $brand = null, ?string $resourcePath = null): array
    {
        $entityId = $this->getEntityId($brand);
        $token = $this->getAccessToken();

        if (empty($token) || empty($entityId)) {
            return [
                'is_paid' => false,
                'status' => 'unconfigured',
                'message' => 'HyperPay credentials missing.',
            ];
        }

        try {
            // Prioritize the matching entityId first
            $entityIds = array_filter(array_unique([
                $entityId,
                $this->credentials['entity_id_mada'] ?? '',
                $this->credentials['entity_id_visa_master'] ?? '',
                $this->credentials['entity_id_applepay'] ?? '',
                $this->credentials['entity_id_stcpay'] ?? '',
            ]));

            $data = null;
            $isSuccessful = false;
            $resultCode = '';

            $cleanResourcePath = !empty($resourcePath) ? '/' . ltrim(urldecode($resourcePath), '/') : null;

            foreach ($entityIds as $currentEntityId) {
                if ($cleanResourcePath && str_contains($cleanResourcePath, '/v1/checkouts/')) {
                    $url = "{$this->baseUrl}{$cleanResourcePath}?entityId={$currentEntityId}";
                } else {
                    $url = "{$this->baseUrl}/v1/checkouts/{$referenceId}/payment?entityId={$currentEntityId}";
                }

                $response = Http::timeout(3.5)->withToken($token)->get($url);
                $resData = $response->json();

                if (is_array($resData) && !empty($resData['result']['code'])) {
                    $data = $resData;
                    $resultCode = $resData['result']['code'];
                    $isSuccessful = (bool)preg_match('/^(000\.000\.|000\.100\.1|000\.[36]|000\.400\.[0-2])/', $resultCode);
                    if ($isSuccessful) {
                        break;
                    }
                }
            }

            if (!$data) {
                return [
                    'is_paid' => false,
                    'status' => 'not_found',
                    'message' => 'Transaction not found for reference ID.',
                ];
            }

            return [
                'is_paid' => $isSuccessful,
                'status' => $isSuccessful ? 'paid' : 'pending_or_failed',
                'result_code' => $resultCode,
                'result_description' => $data['result']['description'] ?? '',
                'transaction_id' => $data['id'] ?? null,
                'amount' => isset($data['amount']) ? (float)$data['amount'] : null,
                'payment_brand' => $data['paymentBrand'] ?? null,
                'raw' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('HyperPay verifyPayment error: ' . $e->getMessage());
            return [
                'is_paid' => false,
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Handle incoming server-to-server webhook from HyperPay
     */
    public function handleWebhook(array $payload, array $headers = []): array
    {
        // HyperPay webhook payload parsing
        $checkoutId = $payload['id'] ?? $payload['checkoutId'] ?? null;
        $merchantTxId = $payload['merchantTransactionId'] ?? null;
        $resultCode = $payload['result']['code'] ?? '';
        $isSuccessful = (bool)preg_match('/^(000\.000\.|000\.100\.1|000\.[36])/', $resultCode);

        return [
            'handled' => true,
            'checkout_id' => $checkoutId,
            'order_number' => $merchantTxId,
            'is_paid' => $isSuccessful,
            'transaction_id' => $payload['id'] ?? null,
            'raw' => $payload,
        ];
    }
}
