<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\OrderActivity;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    public function created(Order $order): void
    {
        try {
            $orderNumber = $order->order_number ?: $order->id;
            $customerName = trim(($order->first_name ?? '') . ' ' . ($order->last_name ?? ''));
            $currency = $order->currency ?: 'SAR';
            $amount = number_format((float)($order->total_amount ?? 0), 2);
            $lang = strtoupper((string)($order->order_language ?: 'AR'));

            OrderActivity::log(
                $order,
                'created',
                "Order Created (#{$orderNumber})",
                "New order placed for {$customerName} ({$order->email}). Total: {$amount} {$currency} | Language: {$lang} | Payment: " . ($order->payment_method ?: 'Pending'),
                [
                    'order_number' => $orderNumber,
                    'total_amount' => $order->total_amount,
                    'currency' => $currency,
                    'payment_method' => $order->payment_method,
                    'order_language' => $order->order_language,
                    'customer_ip' => $order->customer_ip,
                ]
            );
        } catch (\Throwable $e) {
            Log::error("[OrderObserver] Error logging created activity: " . $e->getMessage());
        }
    }

    public function updated(Order $order): void
    {
        // 1. Log Payment status updates
        if ($order->isDirty('payment_status')) {
            try {
                $oldPayment = $order->getOriginal('payment_status') ?: 'unpaid';
                $newPayment = $order->payment_status;
                $method = strtoupper((string)($order->payment_method ?: 'N/A'));
                $paidDate = $order->date_paid ? $order->date_paid->format('d M Y, h:i A') : now()->format('d M Y, h:i A');

                OrderActivity::log(
                    $order,
                    'paid',
                    "Payment Status: " . ucfirst((string)$oldPayment) . " ➔ " . ucfirst((string)$newPayment),
                    "Payment method: {$method} | Status: {$newPayment}" . ($newPayment === 'completed' || $newPayment === 'paid' ? " | Date Paid: {$paidDate}" : ""),
                    [
                        'from_payment_status' => $oldPayment,
                        'to_payment_status' => $newPayment,
                        'payment_method' => $order->payment_method,
                        'date_paid' => $order->date_paid?->toDateTimeString(),
                    ]
                );
            } catch (\Throwable $e) {
                Log::error("[OrderObserver] Error logging payment activity: " . $e->getMessage());
            }
        }

        // 2. Check if order status has been updated
        if ($order->isDirty('status')) {
            $fromStatus = $order->getOriginal('status') ?: 'new';
            $toStatus = $order->status;

            try {
                OrderActivity::log(
                    $order,
                    'status_change',
                    "Status Changed: " . ucfirst(str_replace('_', ' ', (string)$fromStatus)) . " ➔ " . ucfirst(str_replace('_', ' ', (string)$toStatus)),
                    "Order fulfillment status transitioned from " . ucfirst(str_replace('_', ' ', (string)$fromStatus)) . " to " . ucfirst(str_replace('_', ' ', (string)$toStatus)) . ".",
                    [
                        'from_status' => $fromStatus,
                        'to_status' => $toStatus,
                    ]
                );
            } catch (\Throwable $e) {
                Log::error("[OrderObserver] Error logging status activity: " . $e->getMessage());
            }

            $status = strtolower((string)$toStatus);

            $eventKey = match ($status) {
                'pending_payment', 'pending' => 'order_pending_payment',
                'processing' => 'order_confirmed',
                'printed' => 'order_printed',
                'shipped', 'out_for_delivery', 'dispatched' => 'order_shipped',
                'delivered' => 'order_delivered',
                'completed' => 'order_completed',
                'cancelled' => 'order_cancelled',
                'refunded' => 'order_refunded',
                'failed' => 'order_failed',
                'confirmed', 'paid' => 'order_confirmed',
                default => null,
            };

            if ($eventKey) {
                $orderId = $order->id;
                app()->terminating(function () use ($orderId, $eventKey) {
                    try {
                        $targetOrder = Order::find($orderId);
                        if ($targetOrder) {
                            /** @var NotificationService $notificationService */
                            $notificationService = app(NotificationService::class);
                            $notificationService->sendOrderNotification($targetOrder, $eventKey);
                        }
                    } catch (\Throwable $e) {
                        Log::error("OrderObserver terminating notification failed for Order #{$orderId} ({$eventKey}): " . $e->getMessage());
                    }
                });
            }
        }

        // 3. Trigger Odoo ERP Auto-sync (Strictly only when order is paid & not already synced, deferred to terminate phase)
        $isPaid = in_array(strtolower((string)$order->payment_status), ['paid', 'completed']);
        $becamePaid = $isPaid && (
            $order->isDirty('payment_status') ||
            ($order->isDirty('status') && strtolower((string)$order->status) === 'processing')
        );

        if ($becamePaid && $order->odoo_sync_status !== 'synced') {
            $orderId = $order->id;
            app()->terminating(function () use ($orderId) {
                try {
                    $targetOrder = Order::find($orderId);
                    if ($targetOrder && $targetOrder->odoo_sync_status !== 'synced') {
                        $odooService = app(\App\Services\Odoo\OdooService::class);
                        $odooService->syncOrder($targetOrder, 'system_auto');
                    }
                } catch (\Throwable $e) {
                    Log::error("[OrderObserver] Terminating Odoo auto-sync error for Order #{$orderId}: " . $e->getMessage());
                }
            });
        }
    }
}

