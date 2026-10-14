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

                $meta = is_array($order->meta_data) ? $order->meta_data : [];
                $meta['payment_gateway'] = $gateway ?? $meta['payment_gateway'] ?? 'hyperpay';

                $brand = $rawPayload['paymentBrand'] ?? $rawPayload['brand'] ?? $meta['payment_brand'] ?? null;
                if ($brand) {
                    $meta['payment_brand'] = $brand;
                }
                if (!empty($rawPayload['card'])) {
                    $meta['payment_card'] = [
                        'last4' => $rawPayload['card']['last4Digits'] ?? null,
                        'holder' => $rawPayload['card']['holder'] ?? null,
                        'bank' => $rawPayload['card']['issuer']['bank'] ?? null,
                        'type' => $rawPayload['card']['type'] ?? null,
                    ];
                }
                $order->meta_data = $meta;

                // Determine specific payment method
                $normBrand = strtolower((string)$brand);
                if (str_contains($normBrand, 'mada')) {
                    $order->payment_method = 'mada';
                } elseif (str_contains($normBrand, 'apple')) {
                    $order->payment_method = 'apple_pay';
                } elseif (str_contains($normBrand, 'stc')) {
                    $order->payment_method = 'stc_pay';
                } elseif (str_contains($normBrand, 'visa')) {
                    $order->payment_method = 'visa';
                } elseif (str_contains($normBrand, 'master')) {
                    $order->payment_method = 'mastercard';
                } elseif (str_contains($normBrand, 'amex')) {
                    $order->payment_method = 'amex';
                } elseif (empty($order->payment_method) || in_array($order->payment_method, ['hyperpay', 'card'])) {
                    if (!empty($gateway)) {
                        $order->payment_method = strtolower($gateway);
                    }
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

            // ⚡ Automatically activate any subscriptions linked to this order and store payment token
            self::activateOrderSubscriptions($order, $gateway, $transactionId, $rawPayload);

            // 🛒 Permanently clear/recover cart for this order so it doesn't linger in user's cart
            self::clearOrderCart($order);

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

    /**
     * Charge recurring payment token using specified or default gateway.
     */
    public static function chargeRecurringToken(Order $order, string $token, string $gateway = 'hyperpay', array $options = []): array
    {
        $service = self::resolveService($gateway);
        if ($service && method_exists($service, 'chargeRecurringToken')) {
            return $service->chargeRecurringToken($order, $token, $options);
        }

        Log::warning("chargeRecurringToken: gateway [{$gateway}] does not support recurring charges for Order #{$order->order_number}.");
        return [
            'success' => false,
            'message' => "Payment gateway [{$gateway}] does not support automatic recurring debits.",
        ];
    }

    /**
     * Activate any subscriptions associated with an order and save payment token.
     */
    public static function activateOrderSubscriptions(Order $order, ?string $gateway = null, string $transactionId = '', array $rawPayload = []): void
    {
        try {
            $effectiveGateway = strtolower($gateway ?? $order->payment_method ?? 'hyperpay');

            // Gather all candidate subscription IDs for this order
            $subIds = [];
            if (!empty($order->subscription_id)) {
                $subIds[] = $order->subscription_id;
            }
            if (!empty($order->meta_data['subscription_ids']) && is_array($order->meta_data['subscription_ids'])) {
                $subIds = array_merge($subIds, $order->meta_data['subscription_ids']);
            }

            // Also check subscription_deliveries where order_id matches
            $deliverySubIds = \App\Models\SubscriptionDelivery::where('order_id', $order->id)->pluck('subscription_id')->toArray();
            if (!empty($deliverySubIds)) {
                $subIds = array_merge($subIds, $deliverySubIds);
            }

            // Fallback: If still empty, check if order customer has a recent pending_payment subscription matching
            if (empty($subIds) && !empty($order->user_id)) {
                $recentPendingSub = \App\Models\Subscription::where('customer_id', $order->user_id)
                    ->where('status', 'pending_payment')
                    ->where('created_at', '>=', now()->subHours(24))
                    ->latest('id')
                    ->first();
                if ($recentPendingSub) {
                    $subIds[] = $recentPendingSub->id;
                    if (empty($order->subscription_id)) {
                        $order->update(['subscription_id' => $recentPendingSub->id]);
                    }
                }
            }

            $subIds = array_unique(array_filter($subIds));

            if (empty($subIds)) {
                return;
            }

            // Extract recurring payment token based on gateway
            $token = null;
            $tokenDetails = [];

            if ($effectiveGateway === 'hyperpay') {
                $token = $rawPayload['registrationId'] ?? $rawPayload['id'] ?? null;
                $tokenDetails = [
                    'gateway' => 'hyperpay',
                    'brand' => $rawPayload['paymentBrand'] ?? 'VISA',
                    'last4' => $rawPayload['card']['last4Digits'] ?? null,
                    'expiry_month' => $rawPayload['card']['expiryMonth'] ?? null,
                    'expiry_year' => $rawPayload['card']['expiryYear'] ?? null,
                ];
            } elseif ($effectiveGateway === 'paypal') {
                $paypalSubId = null;
                if (!empty($rawPayload['subscription_id']) && str_starts_with((string)$rawPayload['subscription_id'], 'I-')) {
                    $paypalSubId = $rawPayload['subscription_id'];
                } elseif (!empty($rawPayload['id']) && str_starts_with((string)$rawPayload['id'], 'I-')) {
                    $paypalSubId = $rawPayload['id'];
                } elseif (!empty($order->razorpay_order_id) && str_starts_with((string)$order->razorpay_order_id, 'I-')) {
                    $paypalSubId = $order->razorpay_order_id;
                }

                $vaultToken = $rawPayload['payment_source']['paypal']['attributes']['vault']['id']
                    ?? $rawPayload['payment_source']['card']['attributes']['vault']['id']
                    ?? null;

                $token = $paypalSubId
                    ?? $vaultToken 
                    ?? $rawPayload['payer']['payer_id'] 
                    ?? $rawPayload['payment_source']['paypal']['account_id']
                    ?? $rawPayload['id'] 
                    ?? $transactionId;

                $tokenDetails = [
                    'gateway' => 'paypal',
                    'subscription_id' => $paypalSubId,
                    'vault_id' => $vaultToken,
                    'payer_id' => $rawPayload['payer']['payer_id'] ?? $rawPayload['payment_source']['paypal']['account_id'] ?? null,
                    'payer_email' => $rawPayload['payer']['email_address'] ?? $rawPayload['payment_source']['paypal']['email_address'] ?? null,
                    'capture_id' => $rawPayload['purchase_units'][0]['payments']['captures'][0]['id'] ?? $transactionId,
                ];
            } else {
                $token = $rawPayload['token'] ?? $rawPayload['id'] ?? $transactionId;
                $tokenDetails = ['gateway' => $effectiveGateway, 'transaction_id' => $transactionId];
            }

            foreach ($subIds as $sId) {
                $sub = \App\Models\Subscription::find($sId);
                if (!$sub) continue;

                $updates = [
                    'status' => 'active',
                    'payment_gateway' => $effectiveGateway,
                    'consecutive_failed_payments' => 0,
                ];

                if (!empty($token)) {
                    $updates['payment_token'] = $token;
                    $updates['payment_token_details'] = $tokenDetails;
                }

                $sub->update($updates);

                // Update initial delivery linked to this subscription
                $firstDel = $sub->deliveries()->where('order_id', $order->id)->first()
                    ?: $sub->deliveries()->orderBy('delivery_number', 'asc')->first()
                    ?: $sub->deliveries()->first();

                if ($firstDel) {
                    $firstDel->update([
                        'order_id' => $order->id,
                        'delivery_status' => 'processing',
                        'payment_status' => 'paid',
                        'payment_reference' => $transactionId,
                    ]);
                }

                $sub->logActivity('initial_payment_verified', "Payment verified for order #{$order->order_number}. Subscription activated.", [
                    'order_id' => $order->id,
                    'transaction_id' => $transactionId,
                    'gateway' => $effectiveGateway,
                    'token' => $token,
                ]);

                // 🌸 Send Subscription Confirmed & Admin Alert notifications (Email & SMS)
                try {
                    /** @var \App\Services\Notifications\NotificationService $notifService */
                    $notifService = app(\App\Services\Notifications\NotificationService::class);
                    $notifService->sendSubscriptionNotification($sub, 'subscription_confirmed');
                    $notifService->sendSubscriptionNotification($sub, 'admin_subscription_created');
                } catch (\Throwable $notifEx) {
                    Log::error("Failed to dispatch subscription_confirmed notification for Sub #{$sub->id}: " . $notifEx->getMessage());
                }

                Log::info("Subscription #{$sub->subscription_number} activated with token [{$token}] via {$effectiveGateway} for Order #{$order->order_number}");
            }
        } catch (\Throwable $e) {
            Log::error("Failed to activate subscriptions for order #{$order->id}: " . $e->getMessage());
        }
    }

    /**
     * Clear and resolve any active cart associated with an order once paid.
     * Optimized with batch queries for lightning-fast order verification.
     */
    public static function clearOrderCart(Order $order): void
    {
        try {
            $cartIds = \App\Models\Cart::query()
                ->where(function ($q) use ($order) {
                    $hasCond = false;
                    if (!empty($order->user_id)) {
                        $q->where('user_id', $order->user_id);
                        $hasCond = true;
                    }
                    if (!empty($order->session_id)) {
                        if ($hasCond) {
                            $q->orWhere('session_id', $order->session_id);
                        } else {
                            $q->where('session_id', $order->session_id);
                            $hasCond = true;
                        }
                    }
                    if (!$hasCond && !empty($order->email)) {
                        $q->where('customer_email', $order->email);
                    }
                })
                ->where('status', '!=', 'recovered')
                ->pluck('id')
                ->toArray();

            if (!empty($cartIds)) {
                \App\Models\CartItem::whereIn('cart_id', $cartIds)->delete();
                \App\Models\Cart::whereIn('id', $cartIds)->update([
                    'status' => 'recovered',
                    'recovered_at' => now(),
                    'recovered_order_id' => $order->id,
                ]);
                Log::info("Carts [" . implode(',', $cartIds) . "] items cleared and marked recovered for Order #{$order->order_number} ({$order->id})");
            }
        } catch (\Throwable $e) {
            Log::error("Failed to clear cart for order #{$order->id}: " . $e->getMessage());
        }
    }
}

