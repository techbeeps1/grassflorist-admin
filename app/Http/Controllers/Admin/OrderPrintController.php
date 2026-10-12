<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderPrintController extends Controller
{
    /**
     * Render Printable Florist Gift Card
     */
    public function printGiftCard(Request $request, $id): View
    {
        $order = Order::with('items')->findOrFail($id);

        if ($request->has('custom_message')) {
            $order->delivery_message = $request->query('custom_message');
        }
        if ($request->has('sender_name')) {
            $order->sender_name = $request->query('sender_name');
        }
        if ($request->has('recipient_name')) {
            $order->recipient_name = $request->query('recipient_name');
        }

        return view('prints.gift_card', compact('order'));
    }

    /**
     * Render Saudi Arabia ZATCA-compliant Tax Invoice
     */
    public function printTaxInvoice(Request $request, $id): View
    {
        $cleanId = preg_replace('/^OR-/i', '', (string)$id);
        $order = Order::with('items')
            ->where('id', $cleanId)
            ->orWhere('id', $id)
            ->orWhere('order_number', $id)
            ->orWhere('order_number', 'OR-' . $cleanId)
            ->firstOrFail();

        // Ensure order_key exists
        if (empty($order->order_key)) {
            $order->order_key = 'wc_order_' . \Illuminate\Support\Str::random(20);
            $order->saveQuietly();
        }

        // Security Authorization Check:
        // 1. Admin/Vendor staff logged into backend
        $isAdmin = auth()->check() && (
            (method_exists(auth()->user(), 'isAdmin') && auth()->user()->isAdmin()) ||
            (method_exists(auth()->user(), 'isVendor') && auth()->user()->isVendor()) ||
            in_array(auth()->user()->role ?? '', ['admin', 'super_admin', 'staff'])
        );

        // 2. Customer key verification (like WooCommerce ?key=wc_order_...)
        if (!$isAdmin) {
            $providedKey = trim((string)$request->query('key'));
            $expectedKey = (string)$order->order_key;

            if (empty($providedKey) || !hash_equals($expectedKey, $providedKey)) {
                abort(403, 'Unauthorized access: Valid order key is required to view this invoice.');
            }
        }

        return view('prints.tax_invoice', compact('order'));
    }
}
