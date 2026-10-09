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
        DB::table('sessions')->upsert([
            'id' => $sessionId,
            'payload' => $sessionId,
            'last_activity' => 0
        ], ['id'], ['payload', 'last_activity']);

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
        
        if (!$cart) {
            return response()->json(['message' => 'Cart not found'], 404);
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
            if (!$product || !$product->is_visible) {
                return response()->json([
                    'message' => "The product '{$item->product_name}' is currently unavailable. Please remove it from your cart to proceed.",
                    'error' => 'Product unavailable',
                    'out_of_stock_product_id' => $item->product_id
                ], 422);
            }

            $availableStock = (int) ($product->quantity ?? 0);
            if ($availableStock <= 0) {
                return response()->json([
                    'message' => "Sorry, '{$product->name}' is currently out of stock. Please remove it from your cart to proceed.",
                    'error' => 'Product out of stock',
                    'out_of_stock_product_id' => $item->product_id
                ], 422);
            }

            if ($item->quantity > $availableStock) {
                return response()->json([
                    'message' => "Sorry, only {$availableStock} unit(s) available for '{$product->name}'. Please update your cart quantity to proceed.",
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
        $shippingMethod = ShippingMethod::where('code', $request->shipping_method)->firstOrFail();

        $shippingAmount = $shippingMethod->price;

        if($shippingAmount>0)
        {

            $baseCost = 49;
            
            $totalItems = $cart->items() ->selectRaw('SUM(quantity) as total_items')->value('total_items');
            $shippingAmount = $shippingAmount * $totalItems;

        }
        $discountAmount = $request->discount_amount ?? 0;
        $totalAmount = $subtotal + $shippingAmount - $discountAmount;
        $user = $this->getOrCreateUser($request, $dbPhone);

        $order = Order::with('items')
        ->latest()
        ->firstOrFail();



        
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
            'address_2' => $request->address2,
            'shipping_method' => $shippingMethod->name,
            'address' => $request->address,
            'coupon_code' => $request->coupon_code,
            'status' => 'payment_pending',
            'customer_phone'=> $dbPhone,
            'first_name'=> $request->first_name,
            'last_name'=> $request->last_name,
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
                
            DB::table('order_items')->insert([
            'order_id' => $order->id,
            'product_id' => $item->product_id,
            'product_name' => $item->product->name,
            'product_image' => $item->product->image,
            'price' => $product_price,
            'quantity' => $item->quantity,
            'product_weight' => $item->product->weight,
            'total' => $product_price * $item->quantity,
            'payment_method'=> $request->payment_method,
            'vendor_id' =>$item->product->vendor_id,
            'created_at' => now(),  // recommended to add timestamps if your table has them
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
            $totalAmount = $totalAmount + $request->delivery_amount; 

            $senderName = $request->sender_name ?: trim(($request->first_name ?? '') . ' ' . ($request->last_name ?? ''));
            $recipientName = $request->recipient_name ?: trim(($request->recipient_first_name ?? '') . ' ' . ($request->recipient_last_name ?? '')) ?: $senderName;
            $recipientPhone = $request->recipient_phone ?: $dbPhone;
            $locationLink = $request->shipping_address_link ?: $request->location_link;
            $deliveryDate = $request->delivery_date;
            $deliveryTime = $request->delivery_time ?: $request->delivery_slot;
            $deliveryMessage = $request->card_message ?: $request->delivery_message;
            $songLink = $request->song_link;

            $vatRate = (float)(\App\Models\GlobalSetting::current()->vat_percentage ?? 15.00);
            $taxAmount = round(max(0, $subtotal - $discountAmount) * ($vatRate / 100), 2);

            $currency = strtoupper($request->currency ?: 'SAR');
            $globalSetting = \App\Models\GlobalSetting::current();
            $exchangeRate = $currency === 'USD' ? (float)($request->exchange_rate ?: $globalSetting->sar_to_usd_rate ?: 0.2667) : 1.0;
            $sarAmount = $totalAmount;
            $currencyAmount = $currency === 'USD' ? round($totalAmount * $exchangeRate, 2) : $totalAmount;

            $metaData = is_array($request->meta_data) ? $request->meta_data : [];
            $metaData['currency'] = $currency;
            $metaData['exchange_rate'] = $exchangeRate;
            $metaData['sar_total'] = $sarAmount;
            $metaData['currency_total'] = $currencyAmount;
            $metaData['sar_subtotal'] = $subtotal;
            if ($currency === 'USD') {
                $metaData['usd_total'] = $currencyAmount;
                $metaData['usd_subtotal'] = round($subtotal * $exchangeRate, 2);
            }

            $order = Order::create([
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
                'payment_method' => $request->payment_method ?: 'mada',
                'payment_status' => 'pending',
                'address' => $request->address,
                'address_2' => $request->address_2 ?: $request->address2,
                'shipping_method' => $shippingMethod->name ?? 'Flat Rate',
                'coupon_code' => $request->coupon_code,
                'status' => 'pending',
                'customer_phone' => $dbPhone,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
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
                'delivery_amount' => $request->delivery_amount ?: 0,
            ]);

            $order->update([
            'order_number' => 'OR-' . $order->id,
            ]);
       
            
            foreach ($cartItems as $item) {

                $product_price = $this->calculateProductPriceWithCoupon($item, $request->coupon_code, $subtotal);
                
                DB::table('order_items')->insert([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name,
                    'product_image' => $item->product->image,
                    'price' => $product_price,
                    'quantity' => $item->quantity,
                    'product_weight' => $item->product->weight,
                    'total' => $product_price * $item->quantity,
                    'payment_method' => $request->payment_method,
                    'vendor_id' => $item->product->vendor_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Clear cart only for non-Razorpay payments
            $this->clearCartAndSendEmails($cart, $order, $request);

            return response()->json([
            'message' => 'Order placed successfully',
            'order' => $order,
            'order_number' => $order->order_number,
       
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
            $customerEmail = $order->email ?? ($request ? $request->email : null);
            // 1. Send confirmation email to customer
            if ($customerEmail) {
                Mail::to($customerEmail)->send(new OrderConfirmation($order));
            }

            // 2. Send notification to admin
            $adminEmail = env('ADMIN_EMAIL');
            if ($adminEmail) {
                Mail::to($adminEmail)->send(new AdminOrderNotification($order));
            }

            // 3. Send notification to each vendor who has items in this order
            $order->loadMissing(['items.product', 'items.vendor.user']);
            $vendorGroups = $order->items->whereNotNull('vendor_id')->groupBy('vendor_id');

            foreach ($vendorGroups as $vendorId => $vendorItems) {
                $vendor = $vendorItems->first()?->vendor ?? Vendor::with('user')->find($vendorId);
                $vendorEmail = $vendor?->user?->email;

                if ($vendor && $vendorEmail) {
                    Mail::to($vendorEmail)->send(new VendorOrderNotification($order, $vendor, $vendorItems));
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
            return 'Minimum cart amount should be ₹' . number_format((float)$coupon->min_cart_amount, 2) . ' to apply this coupon.';
        }
        if (!empty($coupon->max_cart_amount) && $subtotal > 0 && $subtotal > (float)$coupon->max_cart_amount) {
            return 'Maximum cart amount should be ₹' . number_format((float)$coupon->max_cart_amount, 2) . ' for this coupon.';
        }

        // 3. Payment Method Restriction
        $paymentMethod = $request->payment_method ?? null;
        if (!empty($coupon->payment_method_restriction) && $coupon->payment_method_restriction !== 'all' && !empty($paymentMethod)) {
            $isOnline = in_array(strtolower($paymentMethod), ['razorpay', 'online', 'prepaid']);
            $isCod = in_array(strtolower($paymentMethod), ['cod', 'cash_on_delivery']);

            if ($coupon->payment_method_restriction === 'online_only' && !$isOnline) {
                return 'This coupon is valid only on Online / Prepaid payments.';
            }
            if ($coupon->payment_method_restriction === 'cod_only' && !$isCod) {
                return 'This coupon is valid only on Cash on Delivery (COD) orders.';
            }
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
        $email = $request->email ? trim(strtolower($request->email)) : null;
        $phone = $request->phone ? preg_replace('/\D/', '', (string)$request->phone) : null;
        if ($phone && strlen($phone) > 10) {
            $phone = substr($phone, -10);
        }
        $userId = $request->user_id ?? (auth('api')->id() ?: (auth()->id() ?: null));

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
                return 'This coupon is valid only on your first order.';
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
                    return $userLimit === 1
                        ? 'You have already used this coupon on a previous order.'
                        : "You have reached the maximum allowed usage ({$userLimit} times) for this coupon.";
                }
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
                    'payment_method_restriction',
                    'valid_from',
                    'valid_to'
                ])
                ->orderBy('min_cart_amount', 'asc')
                ->get()
                ->map(function ($coupon) {
                    $coupon->is_first_order_only = (bool)($coupon->is_first_order_only ?? false);
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
}