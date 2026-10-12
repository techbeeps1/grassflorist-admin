<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class OrderApiController extends Controller
{
    public function show($orderNumber)
    {
        $order = DB::table('orders')
            ->where('order_number', $orderNumber)
            ->orWhere('id', $orderNumber)
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        // Parse meta_data and ensure frozen currency fields exist
        $metaData = is_string($order->meta_data) ? json_decode($order->meta_data, true) : (array)($order->meta_data ?? []);
        $order->meta_data = $metaData;

        $isPaypal = strtolower((string)($order->payment_method ?? '')) === 'paypal';
        $isUsd = strtoupper((string)($order->currency ?? '')) === 'USD';
        $rate = (float)($order->exchange_rate ?: ($metaData['exchange_rate'] ?? 0.2667));
        if ($rate <= 0 || $rate >= 1.0) {
            $rate = (float)(\App\Models\GlobalSetting::first()?->sar_to_usd_rate ?: 0.2667);
        }
        if ($rate <= 0 || $rate >= 1.0) {
            $rate = 0.2667;
        }
        $order->exchange_rate = $rate;

        $sarTotal = (float)($order->sar_amount ?: $order->total_amount);
        $order->sar_amount = $sarTotal;

        $usdVal = 0;
        if (!empty($metaData['usd_total']) && (float)$metaData['usd_total'] < $sarTotal) {
            $usdVal = (float)$metaData['usd_total'];
        } elseif (!empty($order->currency_amount) && (float)$order->currency_amount < $sarTotal) {
            $usdVal = (float)$order->currency_amount;
        } else {
            $usdVal = round($sarTotal * $rate, 2);
        }

        $order->usd_total = $usdVal;
        $order->usd_amount = $usdVal;

        if ($isUsd) {
            $order->currency = 'USD';
            $order->currency_amount = $usdVal;
        } else {
            $order->currency = 'SAR';
            $order->currency_amount = $sarTotal;
        }

        $items = DB::table('order_items')
            ->where('order_id', $order->id)
            ->leftJoin('products', 'order_items.product_id', '=', 'products.id')
            ->select(
                'order_items.*',
                DB::raw('COALESCE(products.name, order_items.product_name) as product_name'),
                DB::raw('COALESCE(order_items.product_image, products.image) as product_image'),
                'products.slug as product_slug'
            )
            ->get();

        $customer = null;
        if ($order->user_id) {
            $customer = DB::table('customers')
                ->where('id', $order->user_id)
                ->first();
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'order' => $order,
                'items' => $items,
                'customer' => $customer,
            ],
            'order' => $order,
            'items' => $items,
        ]);
    }

    public function userOrders($user_id = null)
    {
        // 1. Resolve customer from JWT authentication or $user_id parameter
        $customer = auth('customer')->user();
        if (!$customer && !empty($user_id) && $user_id !== '0' && is_numeric($user_id)) {
            $customer = \App\Models\Customer::find($user_id);
        }

        $customerId = $customer?->id ?: ((!empty($user_id) && $user_id !== '0' && is_numeric($user_id)) ? (int)$user_id : null);
        $email = $customer?->email ?: trim((string)request('email'));
        $phone = $customer?->phone ?: trim((string)request('phone'));

        // 2. Automatically link any guest/non-login orders matching this email to the customer
        if ($customerId && !empty($email)) {
            DB::table('orders')
                ->where(function ($q) {
                    $q->whereNull('user_id')->orWhere('user_id', 0);
                })
                ->where('email', $email)
                ->update(['user_id' => $customerId]);
        }

        // 3. Query all orders matching user_id OR email OR phone (login + guest orders)
        $ordersQuery = DB::table('orders');

        $ordersQuery->where(function ($query) use ($customerId, $email, $phone) {
            $hasCondition = false;

            if ($customerId) {
                $query->where('user_id', $customerId);
                $hasCondition = true;
            }

            if (!empty($email)) {
                if ($hasCondition) {
                    $query->orWhere('email', $email);
                } else {
                    $query->where('email', $email);
                    $hasCondition = true;
                }
            }

            if (!empty($phone)) {
                $cleanPhone = preg_replace('/[^0-9]/', '', (string)$phone);
                if (strlen($cleanPhone) >= 9) {
                    $last9 = substr($cleanPhone, -9);
                    $query->orWhere(function ($sub) use ($last9, $email) {
                        $sub->where(function ($p) use ($last9) {
                            $p->where('customer_phone', 'like', '%' . $last9)
                              ->orWhere('recipient_phone', 'like', '%' . $last9);
                        });
                        if (!empty($email)) {
                            $sub->where(function ($e) use ($email) {
                                $e->where('email', $email)
                                  ->orWhereNull('email')
                                  ->orWhere('email', '');
                            });
                        }
                    });
                }
            }

            if (!$hasCondition) {
                $query->whereRaw('1 = 0');
            }
        });

        $orders = $ordersQuery->orderBy('id', 'desc')->get();

        $ordersList = [];

        foreach ($orders as $order) {
            // Parse meta_data and ensure frozen currency fields exist
            $metaData = is_string($order->meta_data) ? json_decode($order->meta_data, true) : (array)($order->meta_data ?? []);
            $order->meta_data = $metaData;

            $isPaypal = strtolower((string)($order->payment_method ?? '')) === 'paypal';
            $isUsd = strtoupper((string)($order->currency ?? '')) === 'USD';
            $rate = (float)($order->exchange_rate ?: ($metaData['exchange_rate'] ?? 0.2667));
            $order->exchange_rate = $rate;

            if ($isUsd) {
                $order->currency = 'USD';
                if ($rate <= 0 || $rate >= 1.0) {
                    $rate = 0.2667;
                }
                $order->exchange_rate = $rate;

                $usdVal = 0;
                if (!empty($metaData['usd_total']) && (float)$metaData['usd_total'] < (float)$order->total_amount) {
                    $usdVal = (float)$metaData['usd_total'];
                } elseif (!empty($order->currency_amount) && (float)$order->currency_amount < (float)$order->total_amount) {
                    $usdVal = (float)$order->currency_amount;
                } else {
                    $usdVal = round((float)$order->total_amount * $rate, 2);
                }
                $order->currency_amount = $usdVal;
            } else {
                $order->currency = 'SAR';
                $order->currency_amount = (float)$order->total_amount;
            }

            if (!$order->sar_amount || (float)$order->sar_amount <= 0) {
                $order->sar_amount = (float)$order->total_amount;
            }

            // Get items for each order using leftJoin so items are never lost if product row changes
            $items = DB::table('order_items')
                ->where('order_id', $order->id)
                ->leftJoin('products', 'order_items.product_id', '=', 'products.id')
                ->select(
                    'order_items.*',
                    DB::raw('COALESCE(products.name, order_items.product_name) as product_name'),
                    DB::raw('COALESCE(order_items.product_image, products.image) as product_image'),
                    'products.slug as product_slug'
                )
                ->get();

            if (empty($order->order_key)) {
                $order->order_key = 'wc_order_' . \Illuminate\Support\Str::random(20);
                DB::table('orders')->where('id', $order->id)->update(['order_key' => $order->order_key]);
            }

            $ordersList[] = [
                'id' => $order->id,
                'order_number' => $order->order_number ?: 'OR-' . $order->id,
                'order_key' => $order->order_key,
                'order' => $order,
                'order_details' => $order,
                'items' => $items,
            ];
        }

        return response()->json([
            'status' => true,
            'data' => [
                'orders' => $ordersList,
            ],
            'orders' => $ordersList,
        ]);
    }

    public function cancelOrder(Request $request)
    {
        $request->validate([
            'order_number' => 'required',
            'reason' => 'required|string|max:500',
        ]);

        $user = auth('customer')->user();
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized. Please login to continue.'
            ], 401);
        }

        $cleanOrderNum = trim(str_replace('OR-', '', $request->order_number));

        $order = Order::where(function ($query) use ($request, $cleanOrderNum) {
                $query->where('order_number', $request->order_number)
                      ->orWhere('order_number', 'OR-' . $cleanOrderNum)
                      ->orWhere('order_number', $cleanOrderNum)
                      ->orWhere('id', $cleanOrderNum);
            })
            ->where('user_id', $user->id)
            ->first();

        if (!$order) {
            return response()->json([
                'status' => false,
                'message' => 'Order not found.'
            ], 404);
        }

        $currentStatus = strtolower(trim($order->status));

        // Shipping Guard: Cannot cancel if shipped, completed, or delivered
        if (in_array($currentStatus, ['order_shipped', 'shipped', 'completed', 'delivered']) || str_contains($currentStatus, 'ship') || str_contains($currentStatus, 'deliver')) {
            return response()->json([
                'status' => false,
                'message' => 'Order cannot be cancelled because it has already been shipped or completed.'
            ], 422);
        }

        // Already cancelled or refunded guard (including payment_cancelled, failed, declined, refunded)
        if (in_array($currentStatus, ['cancelled', 'payment_cancelled', 'declined', 'failed', 'refunded']) || str_contains($currentStatus, 'cancel') || str_contains($currentStatus, 'refund')) {
            return response()->json([
                'status' => false,
                'message' => 'This order cannot be cancelled as its status is ' . $order->status . '.'
            ], 422);
        }

        // Update order status
        $order->status = 'cancelled';
        $order->cancelled_by = 'customer';
        $order->cancellation_reason = $request->reason;
        $order->save();

        return response()->json([
            'status' => true,
            'message' => 'Order cancelled successfully.',
            'data' => [
                'order_number' => $order->order_number,
                'status' => $order->status,
                'cancelled_by' => $order->cancelled_by,
                'cancellation_reason' => $order->cancellation_reason,
            ]
        ]);
    }

}