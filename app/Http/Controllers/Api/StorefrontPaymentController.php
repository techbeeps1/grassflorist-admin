<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliverySlot;
use App\Models\PaymentGateway;
use App\Models\Order;
use App\Services\Payments\PaymentManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StorefrontPaymentController extends Controller
{
    /**
     * Get active payment methods for checkout.
     */
    public function getPaymentMethods(): JsonResponse
    {
        $gateways = PaymentGateway::where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->get(['id', 'code', 'name_en', 'name_ar', 'description_en', 'description_ar', 'icon', 'is_sandbox', 'extra_fee', 'min_order_amount', 'max_order_amount']);

        return response()->json([
            'status' => 'success',
            'data' => $gateways,
        ]);
    }

    /**
     * Get active delivery slots for a specific date (Friday vs Regular + Cutoffs + Blocked Holiday dates).
     */
    public function getDeliverySlots(Request $request): JsonResponse
    {
        $timezone = \App\Models\GlobalSetting::current()->timezone ?: 'Asia/Riyadh';
        $nowInTimezone = \Carbon\Carbon::now($timezone);
        $date = $request->query('date', $nowInTimezone->toDateString());

        $blockedInfo = \App\Models\DeliveryBlockedDate::getBlockedInfoForDate($date);
        $slots = DeliverySlot::getSlotsForDate($date);
        $upcomingBlockedDates = \App\Models\DeliveryBlockedDate::getUpcomingBlockedDates(60);

        return response()->json([
            'status' => 'success',
            'date' => $date,
            'timezone' => $timezone,
            'current_time' => $nowInTimezone->format('h:i A'),
            'is_friday' => (bool)(date('N', strtotime($date)) == 5),
            'is_blocked' => $blockedInfo !== null,
            'blocked_info' => $blockedInfo,
            'blocked_dates' => $upcomingBlockedDates,
            'slots' => $slots,
        ]);
    }

    /**
     * Get upcoming active blocked dates and holidays with multilingual notices.
     */
    public function getBlockedDeliveryDates(Request $request): JsonResponse
    {
        $days = (int) $request->query('days', 60);
        $dates = \App\Models\DeliveryBlockedDate::getUpcomingBlockedDates($days);

        return response()->json([
            'status' => 'success',
            'blocked_dates' => $dates,
        ]);
    }

    /**
     * Initiate checkout payment with selected gateway.
     */
    public function initiatePayment(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => 'required',
            'payment_gateway' => 'required|string',
        ]);

        $order = Order::where('id', $request->order_id)
            ->orWhere('order_number', $request->order_id)
            ->first();

        if (!$order) {
            return response()->json(['status' => 'error', 'message' => 'Order not found.'], 404);
        }

        // 🛑 If order was already paid, do NOT re-initiate payment; return success confirmation!
        if ($order->payment_status === 'paid') {
            $freshOrder = $order->fresh(['items']);
            return response()->json([
                'status' => 'success',
                'success' => true,
                'already_paid' => true,
                'message' => 'This order has already been paid successfully.',
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'payment_status' => 'paid',
                'order' => $freshOrder,
            ]);
        }

        // 🛑 If order was cancelled or expired, reject payment initiation
        if (in_array($order->status, ['cancelled', 'expired', 'refunded'])) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'is_cancelled' => true,
                'message' => 'This order has expired or was cancelled. Please place a new order.',
            ], 400);
        }

        $isSubOrder = !empty($order->subscription_id) || !empty($order->meta_data['has_subscription']);
        if ($isSubOrder && in_array(strtolower($request->payment_gateway), ['tabby', 'tamara'])) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'Tabby and Tamara are not available for subscription orders. Please use Mada, Credit Card, or Cash on Delivery.',
            ], 422);
        }

        $service = PaymentManager::resolveService($request->payment_gateway);
        if (!$service) {
            return response()->json(['status' => 'error', 'message' => 'Payment gateway not supported or inactive.'], 400);
        }

        $res = $service->initiateCheckout($order, $request->all());
        if (is_array($res)) {
            if (isset($res['success']) && !isset($res['status'])) {
                $res['status'] = $res['success'] ? 'success' : 'error';
            }
            if (isset($res['status']) && !isset($res['success'])) {
                $res['success'] = $res['status'] === 'success';
            }
        }

        return response()->json($res);
    }

    /**
     * On-demand payment verification for Frontend return / recovery.
     */
    public function verifyPaymentStatus(Request $request): JsonResponse
    {
        $json = json_decode($request->getContent(), true) ?: [];
        $orderId = $request->input('order_id')
            ?? $request->input('order_number')
            ?? ($json['order_id'] ?? null)
            ?? ($json['order_number'] ?? null)
            ?? $request->order_id;

        if (empty($orderId)) {
            return response()->json(['status' => 'error', 'message' => 'Order ID is required.'], 400);
        }

        $order = Order::with('items')
            ->where('id', $orderId)
            ->orWhere('order_number', $orderId)
            ->first();

        if (!$order) {
            return response()->json(['status' => 'error', 'message' => 'Order not found.'], 404);
        }

        $gateway = $request->input('gateway')
            ?? $request->input('payment_gateway')
            ?? ($json['gateway'] ?? null)
            ?? ($json['payment_gateway'] ?? null)
            ?? $order->payment_method
            ?? 'hyperpay';

        // ⚡ If order is ALREADY paid (via webhook or earlier call), return immediately
        if ($order->payment_status === 'paid') {
            return response()->json([
                'status' => 'success',
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'payment_status' => 'paid',
                'order_status' => $order->status,
                'total_amount' => (float)$order->total_amount,
                'sar_amount' => (float)($order->sar_amount ?: $order->total_amount),
                'currency' => $order->currency,
                'currency_amount' => (float)($order->currency_amount ?: $order->total_amount),
                'verification' => [
                    'is_paid' => true,
                    'status' => 'completed',
                    'transaction_id' => $order->transaction_id ?? $order->razorpay_payment_id ?? (string)$order->id,
                ],
                'order' => $order,
            ]);
        }

        $service = PaymentManager::resolveService($gateway);
        if (!$service) {
            return response()->json(['status' => 'error', 'message' => 'Payment gateway not supported.'], 400);
        }

        $brand = $request->input('brand')
            ?? $request->input('payment_brand')
            ?? ($json['brand'] ?? null)
            ?? ($json['payment_brand'] ?? null)
            ?? $order->payment_method;

        $resourcePath = $request->input('resourcePath')
            ?? $request->input('resource_path')
            ?? ($json['resourcePath'] ?? null)
            ?? ($json['resource_path'] ?? null);

        $refId = $request->input('reference_id')
            ?? $request->input('subscription_id')
            ?? $request->input('token')
            ?? $request->input('payment_id')
            ?? ($json['reference_id'] ?? null)
            ?? ($json['subscription_id'] ?? null)
            ?? ($json['token'] ?? null)
            ?? ($json['payment_id'] ?? null)
            ?? $order->razorpay_order_id
            ?? $order->razorpay_payment_id
            ?? $order->order_number
            ?? (string)$order->id;

        // For PayPal, if refId is just internal order number/ID but razorpay_order_id holds PayPal token/ID
        if (strtolower($gateway) === 'paypal' && (str_starts_with((string)$refId, 'ORD-') || is_numeric($refId)) && !empty($order->razorpay_order_id)) {
            $refId = $order->razorpay_order_id;
        }

        if ($service instanceof \App\Services\Payments\HyperPayService) {
            $res = $service->verifyPayment($refId, $brand, $resourcePath);
        } else {
            $res = $service->verifyPayment($refId);
        }

        if (!empty($res['is_paid'])) {
            PaymentManager::markOrderAsPaid($order, $res['transaction_id'] ?? $refId, $gateway, $res['raw'] ?? [], 'FRONTEND_VERIFY');
            $order->update(['payment_method' => strtolower($gateway)]);

            if (!empty($order->subscription_delivery_id)) {
                $del = \App\Models\SubscriptionDelivery::find($order->subscription_delivery_id);
                if ($del && $del->payment_status !== 'paid') {
                    $del->update([
                        'delivery_status' => 'processing',
                        'payment_status' => 'paid',
                        'payment_reference' => $res['transaction_id'] ?? $refId,
                    ]);
                }
            }
        } else {
            \App\Models\PaymentLog::create([
                'order_id' => $order->id,
                'order_number' => $order->order_number ?? (string)$order->id,
                'gateway' => $gateway,
                'event_type' => 'frontend_verify',
                'status' => 'failed',
                'razorpay_order_id' => $refId,
                'amount' => $order->total_amount,
                'message' => $res['result_description'] ?? $res['message'] ?? 'Payment verification returned unpaid or declined.',
                'payload' => $res['raw'] ?? $res,
            ]);
        }

        $freshOrder = $order->fresh(['items', 'subscription']);
        return response()->json([
            'status' => 'success',
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'payment_status' => $freshOrder->payment_status,
            'order_status' => $freshOrder->status,
            'total_amount' => (float)$freshOrder->total_amount,
            'sar_amount' => (float)($freshOrder->sar_amount ?: $freshOrder->total_amount),
            'currency' => $freshOrder->currency,
            'currency_amount' => (float)($freshOrder->currency_amount ?: $freshOrder->total_amount),
            'verification' => $res,
            'order' => $freshOrder,
        ]);
    }
}
