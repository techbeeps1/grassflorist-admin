<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Payments\HyperPayService;
use App\Services\Payments\TabbyService;
use App\Services\Payments\TamaraService;
use App\Services\Payments\PaymentManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    /**
     * HyperPay Webhook Handler
     */
    public function handleHyperPay(Request $request): JsonResponse
    {
        Log::info('HyperPay Webhook Received', ['payload' => $request->all()]);

        $service = new HyperPayService();
        $res = $service->handleWebhook($request->all(), $request->headers->all());

        if (!empty($res['order_number'])) {
            $order = Order::where('order_number', $res['order_number'])
                ->orWhere('id', $res['order_number'])
                ->first();

            if ($order && $res['is_paid']) {
                PaymentManager::markOrderAsPaid($order, $res['transaction_id'] ?? 'HYPERPAY_HOOK', 'hyperpay', $request->all(), 'WEBHOOK');
            }
        }

        return response()->json(['status' => 'received']);
    }

    /**
     * Tabby Webhook Handler
     */
    public function handleTabby(Request $request): JsonResponse
    {
        Log::info('Tabby Webhook Received', ['payload' => $request->all()]);

        $service = new TabbyService();
        $res = $service->handleWebhook($request->all(), $request->headers->all());

        if (!empty($res['order_number'])) {
            $order = Order::where('order_number', $res['order_number'])
                ->orWhere('id', $res['order_number'])
                ->first();

            if ($order && $res['is_paid']) {
                PaymentManager::markOrderAsPaid($order, $res['transaction_id'] ?? 'TABBY_HOOK', 'tabby', $request->all(), 'WEBHOOK');
            }
        }

        return response()->json(['status' => 'received']);
    }

    /**
     * Tamara Webhook Handler
     */
    public function handleTamara(Request $request): JsonResponse
    {
        Log::info('Tamara Webhook Received', ['payload' => $request->all()]);

        $service = new TamaraService();
        $res = $service->handleWebhook($request->all(), $request->headers->all());

        if (!empty($res['order_number'])) {
            $order = Order::where('order_number', $res['order_number'])
                ->orWhere('id', $res['order_number'])
                ->first();

            if ($order && $res['is_paid']) {
                PaymentManager::markOrderAsPaid($order, $res['transaction_id'] ?? 'TAMARA_HOOK', 'tamara', $request->all(), 'WEBHOOK');
            }
        }

        return response()->json(['status' => 'received']);
    }

    /**
     * PayPal Webhook Handler
     */
    public function handlePayPal(Request $request): JsonResponse
    {
        Log::info('PayPal Webhook Received', ['payload' => $request->all()]);

        $service = new \App\Services\Payments\PayPalService();
        $res = $service->handleWebhook($request->all(), $request->headers->all());

        if (!empty($res['order_number'])) {
            $order = Order::where('order_number', $res['order_number'])
                ->orWhere('id', $res['order_number'])
                ->first();

            if ($order && $res['is_paid']) {
                PaymentManager::markOrderAsPaid($order, $res['transaction_id'] ?? 'PAYPAL_HOOK', 'paypal', $request->all(), 'WEBHOOK');
            }
        }

        return response()->json(['status' => 'received']);
    }
}
