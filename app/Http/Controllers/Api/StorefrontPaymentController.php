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

        $service = PaymentManager::resolveService($request->payment_gateway);
        if (!$service) {
            return response()->json(['status' => 'error', 'message' => 'Payment gateway not supported or inactive.'], 400);
        }

        $res = $service->initiateCheckout($order, $request->all());

        return response()->json($res);
    }

    /**
     * On-demand payment verification for Frontend return / recovery.
     */
    public function verifyPaymentStatus(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => 'required',
            'gateway' => 'required|string',
            'reference_id' => 'nullable|string',
        ]);

        $order = Order::where('id', $request->order_id)
            ->orWhere('order_number', $request->order_id)
            ->first();

        if (!$order) {
            return response()->json(['status' => 'error', 'message' => 'Order not found.'], 404);
        }

        $service = PaymentManager::resolveService($request->gateway);
        if (!$service) {
            return response()->json(['status' => 'error', 'message' => 'Payment gateway not supported.'], 400);
        }

        $refId = $request->reference_id ?? $order->razorpay_payment_id ?? $order->order_number ?? (string)$order->id;
        $res = $service->verifyPayment($refId);

        if (!empty($res['is_paid'])) {
            PaymentManager::markOrderAsPaid($order, $res['transaction_id'] ?? $refId, $request->gateway, $res['raw'] ?? [], 'FRONTEND_VERIFY');
        }

        return response()->json([
            'status' => 'success',
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'payment_status' => $order->fresh()->payment_status,
            'order_status' => $order->fresh()->status,
            'verification' => $res,
        ]);
    }
}
