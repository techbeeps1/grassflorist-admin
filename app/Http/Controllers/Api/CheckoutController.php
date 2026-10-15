<?php 
// app/Http/Controllers/Api/CheckoutController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Mail;
use App\Mail\OrderConfirmation;
use App\Mail\AdminOrderNotification;
use App\Mail\VendorOrderNotification;
use App\Models\Vendor;
use App\Services\RazorpayService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;


class CheckoutController extends Controller
{
    public function process(Request $request)
    {
        try {

        $sessionId = $request->session_id;
        if (!empty($sessionId)) {
            DB::table('sessions')->upsert([
                'id' => $sessionId,
                'payload' => $sessionId,
                'last_activity' => 0
            ], ['id'], ['payload', 'last_activity']);
        }

        $request->validate([
            'session_id' => 'sometimes|string|nullable',
            'first_name' => 'required|string',
            'last_name' => 'nullable|string',
            'phone' => 'required|string',
            'shipping_method' => 'sometimes|string|nullable',
            'address' => 'required|string',
            'address_2' => 'string|nullable',
            'city' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string',
            'coupon_code' => 'nullable|string',
            'email' => 'nullable|email',
            'is_guest' => 'sometimes|boolean',
        ]);

        $dbPhone = (string) $request->phone;

        // Get cart based on user or provided session ID
        $cart = $this->getCart($request);
        
        if (!$cart && !empty($request->session_id)) {
            $cart = Cart::firstOrCreate(
                ['session_id' => $request->session_id],
                ['user_id' => Auth::id() ?: null]
            );
        }

        if (!$cart) {
            return response()->json(['message' => 'Cart not found'], 404);
        }

        // Synchronize cart items with submitted checkout items to prevent stale items
        if ($request->has('items') && is_array($request->items) && count($request->items) > 0) {
            $submittedProductIds = collect($request->items)->pluck('product_id')->filter()->map(fn($id) => (int)$id)->all();
            if (!empty($submittedProductIds)) {
                CartItem::where('cart_id', $cart->id)
                    ->whereNotIn('product_id', $submittedProductIds)
                    ->delete();

                foreach ($request->items as $reqItem) {
                    $pid = (int)($reqItem['product_id'] ?? 0);
                    $qty = max(1, (int)($reqItem['quantity'] ?? 1));
                    if ($pid > 0) {
                        $p = Product::find($pid);
                        if ($p) {
                            $itemMeta = $reqItem['meta_data'] ?? null;
                            $isSub = !empty($itemMeta['is_subscription']) || !empty($reqItem['is_subscription']);
                            $existingItem = CartItem::where('cart_id', $cart->id)
                                ->where('product_id', $pid)
                                ->when($isSub && !empty($itemMeta['frequency']), function ($q) use ($itemMeta) {
                                    return $q->whereJsonContains('meta_data->frequency', $itemMeta['frequency']);
                                })
                                ->first();

                            $unitPrice = $existingItem ? $existingItem->price : ($reqItem['price'] ?? (float)$p->price);
                            $metaToSave = $itemMeta ?? ($existingItem ? $existingItem->meta_data : null);

                            if ($existingItem) {
                                $existingItem->update([
                                    'quantity' => $qty,
                                    'price' => (float)$unitPrice,
                                    'meta_data' => $metaToSave,
                                    'image' => is_array($p->image) ? reset($p->image) : ($p->image ?: ''),
                                    'product_weight' => $p->weight ?: '0',
                                ]);
                            } else {
                                CartItem::create([
                                    'cart_id' => $cart->id,
                                    'product_id' => $pid,
                                    'quantity' => $qty,
                                    'price' => (float)$unitPrice,
                                    'meta_data' => $metaToSave,
                                    'image' => is_array($p->image) ? reset($p->image) : ($p->image ?: ''),
                                    'product_weight' => $p->weight ?: '0',
                                ]);
                            }
                        }
                    }
                }
            }
        }

        $cartItems = CartItem::with(['product' => function($query) {
        $query->select('*');
        }])->where('cart_id', $cart->id)
        ->get(['*']);
        if ($cartItems->isEmpty()) {
            return response()->json(['message' => 'Your cart is empty'], 400);
        }

        // Validate stock for each item in cart
        foreach ($cartItems as $item) {
            $product = $item->product;
            $prodName = $this->getProductName($product);
            if (!$product || !$product->is_visible) {
                return response()->json([
                    'message' => "The product '{$prodName}' is currently unavailable. Please remove it from your cart to proceed.",
                    'error' => 'Product unavailable',
                    'out_of_stock_product_id' => $item->product_id
                ], 422);
            }

            $availableStock = (int) ($product->quantity ?? 0);
            if ($availableStock <= 0) {
                return response()->json([
                    'message' => "Sorry, '{$prodName}' is currently out of stock. Please remove it from your cart to proceed.",
                    'error' => 'Product out of stock',
                    'out_of_stock_product_id' => $item->product_id
                ], 422);
            }

            if ($item->quantity > $availableStock) {
                return response()->json([
                    'message' => "Sorry, only {$availableStock} unit(s) available for '{$prodName}'. Please update your cart quantity to proceed.",
                    'error' => 'Insufficient stock',
                    'out_of_stock_product_id' => $item->product_id
                ], 422);
            }
        }

        // Calculate subtotal
        $subtotal = $cartItems->sum(function ($item) {
            return $item->price * $item->quantity;
        });

        // Process coupon if provided
        $discountAmount = $request->discount_amount ?? 0;
        
        if ($request->coupon_code) {
            $coupon = DB::table('coupons')->where('code', $request->coupon_code)->first();
            if ($coupon) {
                $couponError = $this->validateCouponRules($coupon, $request, $subtotal);
                if ($couponError) {
                    return response()->json([
                        'message' => $couponError,
                        'error' => $couponError
                    ], 422);
                }
            } else {
                return response()->json([
                    'message' => 'Invalid coupon code',
                    'error' => 'Invalid coupon code'
                ], 422);
            }
        }
            
        // Process shipping
        $shippingMethod = ShippingMethod::where('code', $request->shipping_method)->first()
            ?? ShippingMethod::where('is_active', true)->first()
            ?? new ShippingMethod(['name' => 'Delivery Inside Jeddah', 'code' => 'inside_jeddah', 'price' => 30.00]);

        $shippingAmount = $request->has('shipping_amount')
            ? (float)$request->shipping_amount
            : (float)($shippingMethod->price ?? 30.00);

        $globalSetting = \App\Models\GlobalSetting::current();
        $isVatIncluded = (bool)($globalSetting->is_vat_included ?? false);
        $vatRate = (float)($globalSetting->vat_percentage ?? 15.00);

        $discountAmount = (float)($request->discount_amount ?? 0);
        $discountedSubtotal = max(0, $subtotal - $discountAmount);

        if ($isVatIncluded) {
            // Prices already include VAT (Inclusive)
            $taxAmount = round($discountedSubtotal - ($discountedSubtotal / (1 + ($vatRate / 100))), 2);
            $totalAmount = round($discountedSubtotal + $shippingAmount, 2);
        } else {
            // VAT is EXCLUDED (Exclusive - Default): 15% VAT is calculated and added on top of subtotal!
            $taxAmount = round($discountedSubtotal * ($vatRate / 100), 2);
            $totalAmount = round($discountedSubtotal + $taxAmount + $shippingAmount, 2);
        }

        $user = $this->getOrCreateUser($request, $dbPhone);



        
         // Determine order language
        $rawOrderLanguage = $request->input('order_language')
            ?: ($request->input('locale')
            ?: ($request->input('lang')
            ?: ($request->header('X-Locale')
            ?: ($request->query('locale')
            ?: ($request->query('lang')
            ?: (app()->getLocale() ?: 'ar'))))));
        $orderLanguage = in_array(strtolower(substr((string)$rawOrderLanguage, 0, 2)), ['en', 'ar'], true)
            ? strtolower(substr((string)$rawOrderLanguage, 0, 2))
            : 'ar';

         //Check payemnt method
        if ($request->payment_method === 'razorpay') {
        
        $order = Order::create([
            'session_id' => $request->session_id,
            'email' => $request->email,
            'user_id' => $user ? $user->id : null,
            'subtotal' => $subtotal,
            'shipping_amount' => $shippingAmount,
            'discount_amount' => $discountAmount,
            'total_amount' => $totalAmount,
            'payment_method' => $request->payment_method,
            'payment_status' => 'payment_pending',
            'order_language' => $orderLanguage,
            'address_2' => $request->address2,
            'shipping_method' => format_translatable($shippingMethod->name) ?: 'Delivery Inside Jeddah',
            'address' => $request->address,
            'coupon_code' => $request->coupon_code,
            'status' => 'payment_pending',
            'customer_phone'=> $dbPhone,
            'first_name'=> $request->first_name,
            'last_name'=> $request->last_name ?: '',
            'zip_code' => $request->zip_code,
            'city' => $request->city,
            'district' => $request->district,
            'state' => $request->state,
            'country' => 'India',
        ]);

        $order->update([
        'order_number' => 'OR-' . $order->id,
        ]);
       
        // Create order items
        foreach ($cartItems as $item) {

        $product_price = $item->price;

        $product_price = $this->calculateProductPriceWithCoupon($item, $request->coupon_code, $subtotal);
                
            $prod = $item->product;
            $prodName = $this->getProductName($prod);
            $prodImage = $prod ? $prod->image : null;
            if (is_array($prodImage)) {
                $prodImage = reset($prodImage);
            }
            $prodWeight = $prod ? $prod->weight : null;
            if (is_array($prodWeight)) {
                $prodWeight = reset($prodWeight);
            }
            $vendorId = $prod ? $prod->vendor_id : null;
            if (is_array($vendorId)) {
                $vendorId = reset($vendorId);
            }

            $itemSubtotal = $product_price * $item->quantity;
            $itemTax = round($itemSubtotal * 0.15, 2);

            DB::table('order_items')->insert([
                'order_id' => $order->id,
                'product_id' => $item->product_id,
                'product_name' => $prodName,
                'product_image' => is_string($prodImage) ? $prodImage : '',
                'price' => $product_price,
                'quantity' => $item->quantity,
                'unit_price' => $product_price,
                'tax' => $itemTax,
                'subtotal' => $itemSubtotal,
                'product_weight' => $prodWeight !== null ? (string)$prodWeight : null,
                'total' => $itemSubtotal,
                'payment_method'=> $request->payment_method,
                'vendor_id' => $vendorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        }

        
            try {
                $razorpayService = new RazorpayService();
                $razorpayOrder = $razorpayService->createOrder($order, $order->order_number);

                $order->update([
                    'razorpay_order_id' => $razorpayOrder->id,
                    'payment_status' => 'created'
                ]);

                return response()->json([
                    'message' => 'Please complete payment',
                    'order' => $order,
                    'order_number' => $order->order_number,
                    'razorpay_order_id' => $razorpayOrder->id,
                    'razorpay_key' => config('services.razorpay.key') ?: env('RAZORPAY_KEY'),
                    'amount' => $totalAmount * 100,
                    'name' => $request->first_name . ' ' . $request->last_name,
                    'email' => $request->email,
                    'contact' => $cleanPhone,
                    'keep_cart' => true, // Tell frontend to keep cart
                ]);
            } catch (\Exception $e) {
                \Log::error('Razorpay Order Creation Error: ' . $e->getMessage(), [
                    'order_id' => $order->id,
                    'trace' => $e->getTraceAsString()
                ]);
                return response()->json([
                    'error' => 'Payment Gateway Error: ' . $e->getMessage() . '. Please verify Razorpay credentials or try Cash on Delivery.',
                    'message' => 'Payment Gateway Error: ' . $e->getMessage() . '. Please verify Razorpay credentials or try Cash on Delivery.'
                ], 500);
            }
        } else 
            {
            $paymentMethod = strtolower(trim((string)($request->payment_method ?: 'mada')));
            $isCod = in_array($paymentMethod, ['cod', 'cash_on_delivery']);
            $isPaypal = ($paymentMethod === 'paypal');

            $codAmount = $isCod ? (float)($request->delivery_amount ?: 0) : 0.00;
            $totalAmount = $totalAmount + $codAmount; 

            $senderName = $request->sender_name ?: trim(($request->first_name ?? '') . ' ' . ($request->last_name ?? ''));
            $recipientName = $request->recipient_name ?: trim(($request->recipient_first_name ?? '') . ' ' . ($request->recipient_last_name ?? '')) ?: $senderName;
            $recipientPhone = $request->recipient_phone ?: $dbPhone;
            $locationLink = $request->shipping_address_link ?: $request->location_link;
            $deliveryDate = $request->delivery_date;
            $deliveryTime = $request->delivery_time ?: $request->delivery_slot;

            // Format delivery time slot according to order language
            if (!empty($deliveryTime)) {
                $rawSlotTime = trim((string)$deliveryTime);
                $slotModel = \App\Models\DeliverySlot::where('start_time', $rawSlotTime)
                    ->orWhere('id', $rawSlotTime)
                    ->orWhere('title_en', $rawSlotTime)
                    ->orWhere('title_ar', $rawSlotTime)
                    ->first();

                if (!$slotModel) {
                    if (str_starts_with($rawSlotTime, '11:00')) {
                        $slotModel = \App\Models\DeliverySlot::where('start_time', '11:00:00')->first();
                    } elseif (str_starts_with($rawSlotTime, '19:00') || str_starts_with($rawSlotTime, '07:00')) {
                        $slotModel = \App\Models\DeliverySlot::where('start_time', '19:00:00')->first();
                    } elseif (str_starts_with($rawSlotTime, '16:00') || str_starts_with($rawSlotTime, '04:00')) {
                        $slotModel = \App\Models\DeliverySlot::where('start_time', '16:00:00')->first();
                    } elseif (str_starts_with($rawSlotTime, '19:30') || str_starts_with($rawSlotTime, '07:30')) {
                        $slotModel = \App\Models\DeliverySlot::where('start_time', '19:30:00')->first();
                    }
                }

                if ($slotModel) {
                    $deliveryTime = ($orderLanguage === 'ar')
                        ? ($slotModel->title_ar ?: $slotModel->title_en)
                        : ($slotModel->title_en ?: $slotModel->title_ar);
                }
            } else {
                $deliveryTime = ($orderLanguage === 'ar') ? 'من 11 صباحاً وحتى 3 مساءً' : '11:00 AM to 03:00 PM';
            }

            $deliveryMessage = $request->card_message ?: $request->delivery_message;
            $songLink = $request->song_link;

            $sarToUsdRate = (float)($globalSetting->sar_to_usd_rate ?: 0.2667);
            $exchangeRate = (float)($request->exchange_rate ?: $sarToUsdRate);
            if ($exchangeRate <= 0) {
                $exchangeRate = 0.2667;
            }

            // Only lock currency to USD if customer explicitly selected USD on the storefront
            $currency = (strtoupper((string)$request->currency) === 'USD') ? 'USD' : 'SAR';
            $sarAmount = (float)$totalAmount;
            $usdAmount = round($sarAmount * $exchangeRate, 2);
            $currencyAmount = ($currency === 'USD') ? $usdAmount : $sarAmount;

            $metaData = is_array($request->meta_data) ? $request->meta_data : [];
            if ($request->filled('sender_name_on_card')) {
                $metaData['sender_name_on_card'] = $request->sender_name_on_card;
            }
            if ($deliveryMessage) {
                $metaData['card_message'] = $deliveryMessage;
            }
            if ($songLink) {
                $metaData['song_link'] = $songLink;
            }
            $metaData['currency'] = $currency;
            $metaData['exchange_rate'] = $exchangeRate;
            $metaData['sar_total'] = $sarAmount;
            $metaData['usd_total'] = $usdAmount;
            $metaData['currency_total'] = $currencyAmount;
            $metaData['sar_subtotal'] = (float)$subtotal;
            $metaData['usd_subtotal'] = round((float)$subtotal * $exchangeRate, 2);

            // 🛑 Check if order contains subscription items
            $hasSubscription = $cartItems->contains(function ($item) {
                $meta = is_array($item->meta_data)
                    ? $item->meta_data
                    : (is_string($item->meta_data) ? json_decode($item->meta_data, true) : null);
                return !empty($meta['is_subscription']);
            });

            // Tabby and Tamara BNPL do not support automated recurring token debits for subscriptions
            if ($hasSubscription && in_array($paymentMethod, ['tabby', 'tamara'])) {
                $isAr = ($orderLanguage === 'ar');
                return response()->json([
                    'message' => $isAr
                        ? 'خدمات التقسيط تابي وتمارا غير متوفرة لطلبات الاشتراكات الدورية. يرجى اختيار بطاقة مدى، البطاقة الائتمانية، أو الدفع عند الاستلام.'
                        : 'Tabby and Tamara BNPL services are not available for recurring subscriptions. Please select Mada, Credit Card, or Cash on Delivery.',
                    'error' => 'gateway_not_supported_for_subscriptions',
                ], 422);
            }

            // 🔁 Order Re-use Rules:
            // - If the client explicitly sent an unpaid draft `order_number` (pending/payment_pending/failed),
            //   update and reuse it so the user can switch payment methods or update details without spamming IDs.
            // - If the sent order_number is ALREADY PAID or completed, NEVER reuse it and NEVER block the user:
            //   it means the user is placing a new order! A fresh order ID must be generated.
            // - NEVER search or match past orders by session_id alone! Each new checkout gets a fresh order ID.
            $existingOrder = null;
            if (!empty($request->order_number)) {
                $candidateOrder = Order::where('order_number', $request->order_number)->first();
                if ($candidateOrder && in_array($candidateOrder->payment_status, ['pending', 'payment_pending', 'failed']) && !in_array($candidateOrder->status, ['cancelled', 'completed', 'refunded', 'expired'])) {
                    $existingOrder = $candidateOrder;
                }
            }

            $orderData = [
                'session_id' => $request->session_id,
                'email' => $request->email ?: 'customer@grassflorist.com',
                'user_id' => $user ? $user->id : (auth('customer')->id() ?? null),
                'subtotal' => $subtotal,
                'shipping_amount' => $shippingAmount,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'currency' => $currency,
                'exchange_rate' => $exchangeRate,
                'currency_amount' => $currencyAmount,
                'sar_amount' => $sarAmount,
                'meta_data' => $metaData,
                'payment_method' => $paymentMethod,
                'payment_status' => 'pending',
                'address' => $request->address,
                'address_2' => $request->address_2 ?: $request->address2,
                'shipping_method' => format_translatable($shippingMethod->name) ?: 'Delivery Inside Jeddah',
                'coupon_code' => $request->coupon_code,
                'status' => $isCod ? 'processing' : 'pending_payment',
                'customer_phone' => $dbPhone,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name ?: '',
                'sender_name' => $senderName,
                'recipient_name' => $recipientName,
                'recipient_phone' => $recipientPhone,
                'location_link' => $locationLink,
                'delivery_date' => $deliveryDate,
                'delivery_time' => $deliveryTime,
                'delivery_message' => $deliveryMessage,
                'song_link' => $songLink,
                'zip_code' => $request->zip_code ?: '23434',
                'city' => $request->city ?: 'Jeddah',
                'district' => $request->district,
                'state' => 'Makkah',
                'country' => 'Saudi Arabia',
                'order_language' => $orderLanguage,
                'delivery_amount' => $codAmount,
            ];

            if ($existingOrder) {
                // Update existing order without changing order ID or order number
                $existingOrder->update($orderData);
                $order = $existingOrder;
                // Delete old items to refresh with current cart items and updated totals
                DB::table('order_items')->where('order_id', $order->id)->delete();
            } else {
                $order = Order::create($orderData);
                $order->update([
                    'order_number' => 'OR-' . $order->id,
                ]);
            }

            $createdSubIds = [];

            foreach ($cartItems as $item) {
                $product_price = $this->calculateProductPriceWithCoupon($item, $request->coupon_code, $subtotal);
                
                $prod = $item->product;
                $prodName = $this->getProductName($prod);
                $prodImage = $prod ? $prod->image : null;
                if (is_array($prodImage)) {
                    $prodImage = reset($prodImage);
                }
                $prodWeight = $prod ? $prod->weight : null;
                if (is_array($prodWeight)) {
                    $prodWeight = reset($prodWeight);
                }
                $vendorId = $prod ? $prod->vendor_id : null;
                if (is_array($vendorId)) {
                    $vendorId = reset($vendorId);
                }

                $itemSubtotal = $product_price * $item->quantity;
                $itemTax = round($itemSubtotal * 0.15, 2);

                $itemMeta = is_array($item->meta_data)
                    ? $item->meta_data
                    : (is_string($item->meta_data) ? json_decode($item->meta_data, true) : null);

                DB::table('order_items')->insert([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'product_name' => $prodName,
                    'product_image' => is_string($prodImage) ? $prodImage : '',
                    'price' => $product_price,
                    'quantity' => $item->quantity,
                    'unit_price' => $product_price,
                    'tax' => $itemTax,
                    'subtotal' => $itemSubtotal,
                    'product_weight' => $prodWeight !== null ? (string)$prodWeight : null,
                    'total' => $itemSubtotal,
                    'payment_method' => $paymentMethod,
                    'vendor_id' => is_numeric($vendorId) ? (int)$vendorId : null,
                    'meta_data' => !empty($itemMeta) ? json_encode($itemMeta) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // ⚡ Handle Subscription Item: Create Subscription & initial delivery schedule
                if (!empty($itemMeta['is_subscription'])) {
                    $customerId = $user ? $user->id : (auth('customer')->id() ?? null);
                    if (!$customerId && !empty($order->email)) {
                        $cust = Customer::where('email', strtolower($order->email))
                            ->orWhere('phone', $order->customer_phone)
                            ->first();
                        if (!$cust) {
                            $cust = Customer::create([
                                'first_name' => $order->first_name ?: 'Subscriber',
                                'last_name' => $order->last_name ?: '',
                                'email' => strtolower($order->email),
                                'phone' => $order->customer_phone,
                                'address' => $order->address,
                                'city' => $order->city ?: 'Jeddah',
                                'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(12)),
                            ]);
                        }
                        $customerId = $cust->id;
                    }

                    if ($customerId) {
                        try {
                            $subService = app(\App\Services\Subscriptions\SubscriptionService::class);

                            // 🔍 Deduplication: Check if a subscription already exists for this order & product
                            $existingSub = null;

                            // 1. Direct check: Order's current subscription_id
                            if (!empty($order->subscription_id)) {
                                $candidate = \App\Models\Subscription::find($order->subscription_id);
                                if ($candidate && (int)$candidate->product_id === (int)$item->product_id) {
                                    $existingSub = $candidate;
                                }
                            }

                            // 2. Metadata initial_order_id check
                            if (!$existingSub) {
                                $existingSub = \App\Models\Subscription::where('customer_id', $customerId)
                                    ->where('product_id', $item->product_id)
                                    ->where(function ($q) use ($order) {
                                        $q->where('metadata->initial_order_id', (int)$order->id)
                                          ->orWhere('metadata->initial_order_id', (string)$order->id);
                                    })
                                    ->first();
                            }

                            // 3. SubscriptionDelivery link check
                            if (!$existingSub) {
                                $existingDel = \App\Models\SubscriptionDelivery::where('order_id', $order->id)->first();
                                if ($existingDel && $existingDel->subscription && (int)$existingDel->subscription->product_id === (int)$item->product_id) {
                                    $existingSub = $existingDel->subscription;
                                }
                            }

                            if ($existingSub) {
                                // Subscription already exists for this order — update details instead of creating a duplicate!
                                $existingSub->update([
                                    'quantity' => $item->quantity,
                                    'frequency' => $itemMeta['frequency'] ?? $existingSub->frequency,
                                    'duration_type' => $itemMeta['duration_type'] ?? $existingSub->duration_type,
                                    'delivery_time_slot' => $deliveryTime ?: $existingSub->delivery_time_slot,
                                    'payment_gateway' => $paymentMethod,
                                    'gift_message' => $deliveryMessage ?: $existingSub->gift_message,
                                ]);

                                $subscription = $existingSub;
                            } else {
                                $subscription = $subService->createSubscription($customerId, [
                                    'product_id' => $item->product_id,
                                    'quantity' => $item->quantity,
                                    'frequency' => $itemMeta['frequency'] ?? 'weekly',
                                    'duration_type' => $itemMeta['duration_type'] ?? 'until_cancelled',
                                    'start_date' => $deliveryDate,
                                    'delivery_time_slot' => $deliveryTime,
                                    'recipient_name' => $recipientName,
                                    'recipient_phone' => $recipientPhone,
                                    'address' => $order->address,
                                    'address_2' => $order->address_2,
                                    'city' => $order->city ?: 'Jeddah',
                                    'district' => $order->district,
                                    'state' => $order->state,
                                    'country' => $order->country ?: 'Saudi Arabia',
                                    'zip_code' => $order->zip_code,
                                    'location_link' => $order->location_link,
                                    'card_message' => $deliveryMessage,
                                    'payment_gateway' => $paymentMethod,
                                    'status' => $isCod ? 'active' : 'pending_payment',
                                    'initial_order_id' => $order->id,
                                ]);
                            }

                            $firstDelivery = $subscription->deliveries()->orderBy('delivery_number', 'asc')->first() ?? $subscription->deliveries()->first();
                            if ($firstDelivery && (empty($firstDelivery->order_id) || $firstDelivery->order_id == $order->id)) {
                                $firstDelivery->update([
                                    'order_id' => $order->id,
                                    'payment_status' => $isCod ? 'cod_pending' : 'pending',
                                    'delivery_status' => 'processing',
                                ]);
                            }

                            if (empty($order->subscription_id)) {
                                $order->update([
                                    'subscription_id' => $subscription->id,
                                    'subscription_delivery_id' => $firstDelivery ? $firstDelivery->id : null,
                                    'is_recurring_order' => false,
                                ]);
                            }

                            $createdSubIds[] = $subscription->id;
                        } catch (\Throwable $subEx) {
                            \Log::error('Subscription creation in checkout failed: ' . $subEx->getMessage());
                        }
                    }
                }
            }

            if (!empty($createdSubIds)) {
                $curMeta = is_array($order->meta_data) ? $order->meta_data : [];
                $curMeta['has_subscription'] = true;
                $curMeta['subscription_ids'] = array_values(array_unique($createdSubIds));
                $order->update(['meta_data' => $curMeta]);
            }

            // Only clear cart and send confirmation email immediately if Cash on Delivery (COD)
            // For online gateways (HyperPay, Tabby, Tamara, PayPal), cart is preserved until payment is verified!
            if ($isCod) {
                $this->clearCartAndSendEmails($cart, $order, $request);
            }

            return response()->json([
                'message' => $isCod ? 'Order placed successfully' : 'Order session ready for payment',
                'order' => $order,
                'order_number' => $order->order_number,
                'requires_payment' => !$isCod,
                'payment_method' => $paymentMethod,
            ]);
        }
    }
     catch (\Exception $e) {
            logger()->error('Registration error:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
}
    
    protected function getCart(Request $request)
    {
        if (Auth::check()) {
            // For logged-in users, get their cart
            return Cart::where('user_id', Auth::id())->first();
        } else {
            // For guests, get cart by provided session ID
            return Cart::where('session_id', $request->session_id)->first();
        }
    }

    protected function clearCartAndSendEmails($cart, $order, $request)
    {
        try {
            // Clear the cart items
            CartItem::where('cart_id', $cart->id)->delete();

            // If the cart was abandoned or recovered, update its status instead of deleting
            if ($cart->status === 'abandoned' || ($request && $request->filled('recovery_token')) || !empty($cart->recovery_token)) {
                $cart->update([
                    'status' => 'recovered',
                    'recovered_at' => now(),
                    'recovered_order_id' => $order->id,
                ]);
            } else {
                $cart->delete();
            }

            // Also ensure any original cart matching recovery_token is marked recovered
            if ($request && $request->filled('recovery_token')) {
                Cart::where('recovery_token', $request->recovery_token)->update([
                    'status' => 'recovered',
                    'recovered_at' => now(),
                    'recovered_order_id' => $order->id,
                ]);
            }
        } catch (\Exception $e) {
            \Log::warning('Error resolving cart recovery: ' . $e->getMessage());
        }

        // Send emails
        $this->sendOrderEmails($order, $request);
    }


    protected function sendOrderEmails(Order $order, $request)
    {
        try {
            /** @var \App\Services\Notifications\NotificationService $notificationService */
            $notificationService = app(\App\Services\Notifications\NotificationService::class);
            $notificationService->sendOrderNotification($order, 'order_confirmed');
        } catch (\Throwable $e) {
            \Log::error("NotificationService order_confirmed error for #{$order->id}: " . $e->getMessage());
        }

        try {



            // 3. Send notification to each vendor who has items in this order
            $order->loadMissing(['items.product', 'items.vendor.user']);
            $vendorGroups = $order->items->whereNotNull('vendor_id')->groupBy('vendor_id');

            foreach ($vendorGroups as $vendorId => $vendorItems) {
                $vendor = $vendorItems->first()?->vendor ?? Vendor::with('user')->find($vendorId);
                $vendorEmail = $vendor?->user?->email;

                if ($vendor && $vendorEmail) {
                    Mail::to($vendorEmail)->queue(new VendorOrderNotification($order, $vendor, $vendorItems));
                }
            }

        } catch (\Exception $e) {
            logger()->error('Email sending error:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    protected function getOrCreateUser(Request $request, $cleanPhone = null)
    {
        if (Auth::check()) {
            return Auth::customer();
        }

        if ($request->email) {
            // For guest checkout, find or create a user without password

            if($request->password)
            {
                $pass = bcrypt($request->password);
            }
            else
            {
                $pass =bcrypt(Str::random(10));
            }
           
            return $user = Customer::firstOrCreate(
                ['email' => $request->email],
                [
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'phone'=> $cleanPhone ?? $request->phone,
                    'password' =>  $pass,
                    'address' => $request->address,
                    'address_2' => $request->address_2,
                    'zip_code' => $request->zip_code,
                    'city' => $request->city,
                    'district' => $request->district,
                    'state' => $request->state,
                    'country' => 'India',
                ]
            );
        }

        return null;
    }
    /**
     * Validate all coupon rules (dates, limits, first order, payment method, cart amount)
     */
    protected function validateCouponRules($coupon, Request $request, $subtotal = 0): ?string
    {
        $now = now();

        // 1. Active & Date validity
        if (!$coupon->is_active) {
            return 'This coupon is no longer active.';
        }
        if ($coupon->valid_from && \Carbon\Carbon::parse($coupon->valid_from)->gt($now)) {
            return 'This coupon is not yet valid.';
        }
        if ($coupon->valid_to && \Carbon\Carbon::parse($coupon->valid_to)->lt($now)) {
            return 'This coupon has expired.';
        }

        // 2. Minimum / Maximum Cart Amount
        if (!empty($coupon->min_cart_amount) && $subtotal > 0 && $subtotal < (float)$coupon->min_cart_amount) {
            return 'Minimum cart amount should be ' . number_format((float)$coupon->min_cart_amount, 2) . ' SAR to apply this coupon.';
        }
        if (!empty($coupon->max_cart_amount) && $subtotal > 0 && $subtotal > (float)$coupon->max_cart_amount) {
            return 'Maximum cart amount should be ' . number_format((float)$coupon->max_cart_amount, 2) . ' SAR for this coupon.';
        }

        // 4. Overall Usage Limit
        if (!empty($coupon->usage_limit)) {
            $totalUses = Order::where('coupon_code', $coupon->code)
                ->whereNotIn('status', ['payment_cancelled', 'cancelled'])
                ->count();
            if ($totalUses >= (int)$coupon->usage_limit) {
                return 'This coupon has reached its overall usage limit.';
            }
        }

        // Customer details for user-specific checks
        $rawEmail = $request->email 
            ?: ($request->customer_email 
            ?: ($request->input('billing_address.email') 
            ?: ($request->input('customer.email') 
            ?: (auth('customer')->user()?->email
            ?: (auth('api')->user()?->email 
            ?: (auth()->user()?->email ?: null))))));
        $email = $rawEmail ? trim(strtolower((string)$rawEmail)) : null;
        $phone = $request->phone ? preg_replace('/\D/', '', (string)$request->phone) : null;
        if ($phone && strlen($phone) > 10) {
            $phone = substr($phone, -10);
        }
        $userId = $request->user_id ?? (auth('customer')->id() ?: (auth('api')->id() ?: (auth()->id() ?: null)));

        $isAr = $request->header('X-Locale') === 'ar' || $request->input('locale') === 'ar' || app()->getLocale() === 'ar';

        // 5. First Order Only (New Customer Only)
        if (!empty($coupon->is_first_order_only)) {
            $hasPreviousOrders = false;
            if ($userId) {
                $hasPreviousOrders = Order::where('user_id', $userId)
                    ->whereNotIn('status', ['payment_cancelled', 'cancelled'])
                    ->exists();
            }
            if (!$hasPreviousOrders && $email) {
                $hasPreviousOrders = Order::where('email', $email)
                    ->whereNotIn('status', ['payment_cancelled', 'cancelled'])
                    ->exists();
            }
            if (!$hasPreviousOrders && $phone) {
                $hasPreviousOrders = Order::where(function ($q) use ($phone) {
                    $q->where('customer_phone', 'LIKE', "%{$phone}%");
                })->whereNotIn('status', ['payment_cancelled', 'cancelled'])->exists();
            }

            if ($hasPreviousOrders) {
                return $isAr
                    ? 'هذا الكوبون صالح فقط لطلبك الأول للعملاء الجدد.'
                    : 'This coupon is valid only on your first order.';
            }
        }

        // 6. Per-User Usage Limit (e.g. 1 time per user)
        if (!empty($coupon->user_limit)) {
            $userLimit = (int)$coupon->user_limit;

            if ($userId || $email || $phone) {
                $customerUsageCount = Order::where('coupon_code', $coupon->code)
                    ->whereNotIn('status', ['payment_cancelled', 'cancelled'])
                    ->where(function ($q) use ($userId, $email, $phone) {
                        $hasCondition = false;
                        if ($userId) {
                            $q->orWhere('user_id', $userId);
                            $hasCondition = true;
                        }
                        if ($email) {
                            $q->orWhere('email', $email);
                            $hasCondition = true;
                        }
                        if ($phone) {
                            $q->orWhere('customer_phone', 'LIKE', "%{$phone}%");
                            $hasCondition = true;
                        }
                        if (!$hasCondition) {
                            $q->whereRaw('1 = 0');
                        }
                    })
                    ->count();

                if ($customerUsageCount >= $userLimit) {
                    if ($userLimit === 1) {
                        return $isAr
                            ? 'لقد استخدمت هذا الكوبون بالفعل في طلب سابق.'
                            : 'You have already used this coupon on a previous order.';
                    }
                    return $isAr
                        ? "لقد وصلت إلى الحد الأقصى المسموح به لاستخدام هذا الكوبون ({$userLimit} مرات)."
                        : "You have reached the maximum allowed usage ({$userLimit} times) for this coupon.";
                }
            }
        }

        // 7. Newsletter Subscriber Only Check (Configured via is_subscriber_only toggle)
        if (!empty($coupon->is_subscriber_only) || in_array(strtoupper(trim((string)$coupon->code)), ['SAVE10', 'WELCOME10'], true)) {
            if (!$email) {
                return $isAr
                    ? 'يرجى إدخال البريد الإلكتروني للتحقق من أهلية كود المشتركين في النشرة البريدية.'
                    : 'Please enter your email address to verify newsletter subscriber eligibility.';
            }

            $isSubscribed = \App\Models\NewsletterSubscriber::where('email', $email)
                ->where('status', 'active')
                ->exists();
            if (!$isSubscribed) {
                return $isAr
                    ? 'هذا الكوبون مخصص حصرياً للمشتركين في النشرة البريدية. يرجى الاشتراك في النشرة أولاً للاستفادة من الخصم.'
                    : 'This coupon is valid exclusively for newsletter subscribers. Please subscribe to our newsletter first to use this discount.';
            }
        }

        return null;
    }

    public function showCouponCode(Request $request)
    {
        try {
            $code = $request->coupon_code ?: $request->route('coupon_code');

            if (!$code) {
                return response()->json(['message' => 'Please provide a coupon code'], 422);
            }

            $coupon = DB::table('coupons')
                ->where('code', $code)
                ->where('is_active', true)
                ->first();

            if (empty($coupon)) {
                return response()->json(['message' => 'Invalid or inactive coupon code'], 422);
            }

            $subtotal = (float)($request->subtotal ?? $request->cart_subtotal ?? 0);
            $couponError = $this->validateCouponRules($coupon, $request, $subtotal);

            if ($couponError) {
                return response()->json([
                    'message' => $couponError,
                    'error' => $couponError
                ], 422);
            }

            return response()->json($coupon);

        } catch (\Exception $e) {
            \Log::error('Coupon validation error:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Unable to validate coupon'], 500);
        }
    }

    public function getAvailableCoupons(Request $request)
    {
        try {
            $query = DB::table('coupons')
                ->where('is_active', true)
                ->where(function ($query) {
                    $query->where('valid_from', '<=', now())
                        ->orWhereNull('valid_from');
                })
                ->where(function ($query) {
                    $query->where('valid_to', '>=', now())
                        ->orWhereNull('valid_to');
                });

            // Check overall usage limit if column exists
            if (Schema::hasColumn('coupons', 'usage_limit')) {
                $query->where(function ($q) {
                    $q->whereNull('usage_limit')
                        ->orWhere('usage_limit', '<=', 0);
                });
            }

            $coupons = $query->select([
                    'id',
                    'code',
                    'type',
                    'value',
                    'max_discount_amount',
                    'category_id',
                    'min_cart_amount',
                    'max_cart_amount',
                    'user_limit',
                    'is_first_order_only',
                    'is_subscriber_only',
                    'payment_method_restriction',
                    'valid_from',
                    'valid_to'
                ])
                ->orderBy('min_cart_amount', 'asc')
                ->get()
                ->map(function ($coupon) {
                    $coupon->is_first_order_only = (bool)($coupon->is_first_order_only ?? false);
                    $coupon->is_subscriber_only = (bool)($coupon->is_subscriber_only ?? false);
                    return $coupon;
                });

            return response()->json([
                'success' => true,
                'coupons' => $coupons
            ]);
        } catch (\Exception $e) {
            \Log::error('Error fetching coupons: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch coupons',
                'error' => $e->getMessage(),
                'coupons' => []
            ], 500);
        }
    }

        //Cancel Payment
        public function handlePaymentCancel(Request $request)
        {
        $request->validate([
            'order_id' => 'required|numeric',
            'razorpay_order_id' => 'nullable|string'
        ]);

        $query = Order::where('id', $request->order_id);
        if ($request->filled('razorpay_order_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('razorpay_order_id', $request->razorpay_order_id)
                  ->orWhereNull('razorpay_order_id');
            });
        }
        $order = $query->first();

        if ($order) {
            DB::transaction(function () use ($order) {
                $order->update([
                    'payment_status' => 'payment_cancelled',
                    'status' => 'payment_cancelled',
                    'cancelled_by' => 'customer',
                    'cancellation_reason' => 'Payment cancelled by customer during checkout',
                ]);
            });

            $razorpayService = new RazorpayService();
            $razorpayService->logEvent([
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'event_type' => 'callback',
                'status' => 'cancelled',
                'razorpay_order_id' => $order->razorpay_order_id ?? $request->razorpay_order_id,
                'amount' => $order->total_amount,
                'message' => 'Payment cancelled by customer during checkout modal',
            ]);

            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false], 404);
        }

    // Handle Razorpay callback
    public function razorpayCallback(Request $request)
    {
        try {
            $request->validate([
                'razorpay_order_id' => 'required|string',
                'razorpay_payment_id' => 'required|string',
                'razorpay_signature' => 'required|string',
            ]);

            $razorpayService = new RazorpayService();

            $order = Order::with('items')
                ->where('razorpay_order_id', $request->razorpay_order_id)
                ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found for the given Razorpay Order ID.',
                ], 404);
            }

            // If already marked as paid (e.g. by webhook), return success immediately
            if ($order->payment_status === 'paid') {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment already verified.',
                    'order' => $order,
                    'order_number' => $order->order_number,
                ]);
            }

            if ($razorpayService->verifySignature(
                $request->razorpay_order_id,
                $request->razorpay_payment_id,
                $request->razorpay_signature
            )) {
                $recovery = $razorpayService->markOrderAsPaid(
                    $order,
                    $request->razorpay_payment_id,
                    'callback',
                    $request->all()
                );

                $updatedOrder = $recovery['order'] ?? $order;

                return response()->json([
                    'success' => true,
                    'message' => 'Payment verified successfully.',
                    'order' => $updatedOrder,
                    'order_number' => $updatedOrder->order_number,
                ]);
            } else {
                $this->handleFailedPayment($order);
                return response()->json([
                    'success' => false,
                    'message' => 'Payment signature verification failed.',
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Razorpay callback error:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

        protected function calculateProductPriceWithCoupon($item, $couponCode, $subtotal)
        {
            $product_price = $item->price;

            if ($couponCode) {
                $coupon = DB::table('coupons')
                    ->where('code', $couponCode)
                    ->where('is_active', true)
                    ->first();

                if ($coupon) {
                    if ((!$coupon->min_cart_amount || $subtotal >= (float)$coupon->min_cart_amount) &&
                        (!$coupon->max_cart_amount || $subtotal <= (float)$coupon->max_cart_amount)) {

                        $couponCategoryIds = json_decode($coupon->category_id, true) ?? [];

                        $product = DB::table('products')
                            ->where('id', $item->product_id)
                            ->where(function($query) use ($couponCategoryIds) {
                                $query->whereIn('category_id', $couponCategoryIds)
                                    ->orWhereIn('sub_category_id', $couponCategoryIds);
                            })
                            ->first();

                        $isApplicable = empty($coupon->category_id) || $product;

                        if ($isApplicable) {
                            if ($coupon->type === 'percent') {
                                $product_dis = $item->price * ((float)$coupon->value / 100);
                                if (!empty($coupon->max_discount_amount) && (float)$coupon->max_discount_amount > 0) {
                                    $product_dis = min($product_dis, (float)$coupon->max_discount_amount);
                                }
                            } elseif ($coupon->type === 'fixed') {
                                $product_dis = min((float)$coupon->value, $item->price);
                            }
                            $product_price = max(0, $item->price - ($product_dis ?? 0));
                        }
                    }
                }
            }

            return $product_price;
        }

    public function razorpayWebhook(Request $request)
    {
        try {
            $webhookBody = $request->getContent();
            $webhookSignature = $request->header('X-Razorpay-Signature');

            $razorpayService = new RazorpayService();

            // Validate webhook signature
            if (!$razorpayService->validateWebhookSignature($webhookBody, $webhookSignature)) {
                $razorpayService->logEvent([
                    'event_type' => 'webhook',
                    'status' => 'failed',
                    'message' => 'Razorpay webhook signature verification failed or invalid secret.',
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid webhook signature',
                ], 400);
            }

            $payload = json_decode($webhookBody, true);
            $event = $payload['event'] ?? '';

            $razorpayOrderId = null;
            $razorpayPaymentId = null;
            $amount = null;

            if (isset($payload['payload']['payment']['entity'])) {
                $paymentEntity = $payload['payload']['payment']['entity'];
                $razorpayPaymentId = $paymentEntity['id'] ?? null;
                $razorpayOrderId = $paymentEntity['order_id'] ?? null;
                $amount = isset($paymentEntity['amount']) ? ($paymentEntity['amount'] / 100) : null;
            } elseif (isset($payload['payload']['order']['entity'])) {
                $orderEntity = $payload['payload']['order']['entity'];
                $razorpayOrderId = $orderEntity['id'] ?? null;
                $amount = isset($orderEntity['amount']) ? ($orderEntity['amount'] / 100) : null;
            }

            // Find order by razorpay_order_id, with fallback to notes
            $order = null;
            if ($razorpayOrderId) {
                $order = Order::where('razorpay_order_id', $razorpayOrderId)->first();
            }

            // Fallback to notes if razorpay_order_id didn't match directly
            if (!$order && isset($paymentEntity['notes']['order_id'])) {
                $order = Order::find($paymentEntity['notes']['order_id']);
            }
            if (!$order && isset($paymentEntity['notes']['order_number'])) {
                $order = Order::where('order_number', $paymentEntity['notes']['order_number'])->first();
            }

            if (!$order) {
                $razorpayService->logEvent([
                    'event_type' => 'webhook',
                    'status' => 'skipped',
                    'razorpay_order_id' => $razorpayOrderId,
                    'razorpay_payment_id' => $razorpayPaymentId,
                    'amount' => $amount,
                    'message' => "Webhook received for '{$event}', but no matching order was found in the database.",
                    'payload' => $payload,
                ]);

                return response()->json(['status' => 'ok', 'message' => 'No matching order found']);
            }

            // Handle payment.captured or order.paid
            if (in_array($event, ['payment.captured', 'order.paid'])) {
                // If payment id was not in entity (e.g. order.paid), fetch payments from Razorpay
                if (!$razorpayPaymentId && $razorpayOrderId) {
                    try {
                        $payments = $razorpayService->fetchOrderPayments($razorpayOrderId);
                        if (!empty($payments['items'])) {
                            foreach ($payments['items'] as $item) {
                                if (($item['status'] ?? '') === 'captured') {
                                    $razorpayPaymentId = $item['id'];
                                    break;
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        Log::warning("Could not fetch payments for Razorpay order {$razorpayOrderId}: " . $e->getMessage());
                    }
                }

                if ($razorpayPaymentId) {
                    $razorpayService->markOrderAsPaid($order, $razorpayPaymentId, 'webhook', $payload);
                } else {
                    $razorpayService->logEvent([
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'event_type' => 'webhook',
                        'status' => 'pending',
                        'razorpay_order_id' => $razorpayOrderId,
                        'message' => "Event '{$event}' received, but no captured payment ID could be determined.",
                        'payload' => $payload,
                    ]);
                }
            } elseif ($event === 'payment.failed') {
                if (in_array($order->payment_status, ['payment_pending', 'created'])) {
                    $this->handleFailedPayment($order);
                    $razorpayService->logEvent([
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'event_type' => 'webhook',
                        'status' => 'failed',
                        'razorpay_order_id' => $razorpayOrderId,
                        'razorpay_payment_id' => $razorpayPaymentId,
                        'message' => 'Order marked as payment_cancelled due to Razorpay payment.failed webhook event.',
                        'payload' => $payload,
                    ]);
                }
            } else {
                $razorpayService->logEvent([
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'event_type' => 'webhook',
                    'status' => 'skipped',
                    'razorpay_order_id' => $razorpayOrderId,
                    'razorpay_payment_id' => $razorpayPaymentId,
                    'message' => "Webhook received unhandled event: {$event}",
                    'payload' => $payload,
                ]);
            }

            return response()->json(['status' => 'ok']);
        } catch (\Exception $e) {
            Log::error('Razorpay webhook processing error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    protected function handleFailedPayment(Order $order)
    {
        DB::transaction(function () use ($order) {
            // Option 1: Mark as failed (keeps record)
            $order->update([
                'payment_status' => 'payment_cancelled',
                'status' => 'payment_cancelled',
                'cancelled_by' => 'payment_failed',
                'cancellation_reason' => 'Payment failed or cancelled at payment gateway',
            ]);
            
            // Option 2: Or delete the order completely
            // $order->items()->delete();
            // $order->delete();
        });
    }


    protected function getCartFromOrder(Order $order)
    {
        if ($order->user_id) {
            return Cart::where('user_id', $order->user_id)->first();
        } else {
            return Cart::where('session_id', $order->session_id)->first();
        }
    }

    protected function getProductName($product): string
    {
        if (!$product) {
            return 'Product';
        }

        $name = $product->name;
        if (is_array($name)) {
            $locale = app()->getLocale();
            $val = $name[$locale] ?? $name['en'] ?? $name['ar'] ?? reset($name) ?? 'Product';
            return is_string($val) ? $val : (string)json_encode($val);
        }

        if (is_string($name)) {
            $decoded = json_decode($name, true);
            if (is_array($decoded)) {
                $locale = app()->getLocale();
                $val = $decoded[$locale] ?? $decoded['en'] ?? $decoded['ar'] ?? reset($decoded) ?? 'Product';
                return is_string($val) ? $val : (string)json_encode($val);
            }
            return $name;
        }

        return 'Product';
    }
}