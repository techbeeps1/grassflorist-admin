<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\EmailTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PaymentManager
{
    /**
     * Resolve gateway service instance by code.
     */
    public static function resolveService(string $code): ?PaymentGatewayInterface
    {
        $code = strtolower(trim($code));

        if (
            str_starts_with($code, 'hyperpay') ||
            in_array($code, ['mada', 'applepay', 'apple_pay', 'stcpay', 'stc_pay', 'credit_card', 'creditcard', 'card', 'visa', 'mastercard'])
        ) {
            return new HyperPayService();
        }

        if (str_starts_with($code, 'tabby')) {
            return new TabbyService();
        }

        if (str_starts_with($code, 'tamara')) {
            return new TamaraService();
        }

        if (str_starts_with($code, 'paypal')) {
            return new PayPalService();
        }

        return null;
    }

    /**
     * Mark an order as paid, update stock, trigger email, and write payment log.
     */
    public static function markOrderAsPaid(Order $order, string $transactionId, ?string $gateway = null, array $rawPayload = [], string $source = 'WEBHOOK'): bool
    {
        try {
            DB::transaction(function () use ($order, $transactionId, $gateway, $rawPayload, $source) {
                $order->payment_status = 'paid';
                $order->date_paid = now();
                if (in_array($order->status, ['pending', 'pending_payment', 'payment_pending'])) {
                    $order->status = 'processing';
                }
                if (!empty($transactionId)) {
                    $order->razorpay_payment_id = $transactionId; // Transaction Ref
                }
                if (!empty($gateway)) {
                    $order->payment_method = strtolower($gateway);
                }
                if (strtolower((string)$order->payment_method) === 'paypal') {
                    $order->currency = 'USD';
                    $rate = (float)($order->exchange_rate ?: 0.2667);
                    $sarTotal = (float)($order->sar_amount ?: $order->total_amount);
                    
                    // If currency_amount is missing, 0, or equals the unconverted SAR total, calculate true USD
                    if (!$order->currency_amount || (float)$order->currency_amount <= 0 || (float)$order->currency_amount >= $sarTotal) {
                        $order->currency_amount = round($sarTotal * $rate, 2);
                    }
                    if (!$order->sar_amount || (float)$order->sar_amount <= 0) {
                        $order->sar_amount = $sarTotal;
                    }
                }
                $order->save();

                // Record in payment logs
                \App\Models\PaymentLog::create([
                    'order_id' => $order->id,
                    'order_number' => $order->order_number ?? (string)$order->id,
                    'gateway' => $gateway ?? $order->payment_method ?? 'hyperpay',
                    'event_type' => $source,
                    'status' => 'success',
                    'razorpay_payment_id' => $transactionId,
                    'amount' => $order->total_amount,
                    'message' => "Payment successfully recorded via {$source}",
                    'payload' => $rawPayload,
                ]);
            });

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to mark order #{$order->id} as paid: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send bilingual order confirmation email using dynamic admin template.
     */
    public static function sendOrderConfirmationEmail(Order $order): void
    {
        try {
            /** @var \App\Services\Notifications\NotificationService $notificationService */
            $notificationService = app(\App\Services\Notifications\NotificationService::class);
            $notificationService->sendOrderNotification($order, 'order_confirmed');
        } catch (\Throwable $e) {
            Log::error("Order confirmation notification failed for #{$order->id}: " . $e->getMessage());
        }
    }
}
