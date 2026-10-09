<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    /**
     * Resolve the authenticated customer ID from JWT Bearer token, guards, or request.
     */
    protected function resolveCustomerId(Request $request): ?int
    {
        // 1. Direct customer guard check
        if (auth('customer')->check()) {
            return (int) auth('customer')->id();
        }

        // 2. Try parsing JWT Bearer token if provided in header
        $token = $request->bearerToken();
        if ($token) {
            try {
                $customer = auth('customer')->setToken($token)->user();
                if ($customer) {
                    return (int) $customer->id;
                }
            } catch (\Exception $e) {
                // Ignore token exceptions
            }
        }

        // Customer ID must strictly come from authenticated customer guard, never web/admin session!
        return null;
    }

    /**
     * Resolve active cart instance for current request (by user_id if logged in, or session_id for guest).
     */
    protected function resolveCart(Request $request): ?Cart
    {
        $customerId = $this->resolveCustomerId($request);

        if ($customerId) {
            $userCart = Cart::where('user_id', $customerId)
                ->where('status', 'active')
                ->latest('updated_at')
                ->first();

            if ($userCart) {
                return $userCart;
            }
        }

        $sessionId = $request->session_id;
        if ($sessionId) {
            return Cart::where('session_id', $sessionId)
                ->whereNull('user_id')
                ->where('status', 'active')
                ->latest('updated_at')
                ->first();
        }

        return null;
    }

    /**
     * Format cart and its products for JSON response.
     */
    protected function formatCartResponse(Cart $cart): array
    {
        $items = DB::table('cart_items')
            ->join('products', 'cart_items.product_id', '=', 'products.id')
            ->select(
                'cart_items.id',
                'cart_items.product_id',
                'products.name as product_name',
                'products.category_id as category_id',
                'products.sub_category_id as sub_category_id',
                'products.slug as product_slug',
                'products.price as product_price',
                'products.mrp as product_mrp',
                'products.quantity as stock_quantity',
                'products.is_visible as is_visible',
                'cart_items.quantity',
                DB::raw('COALESCE(cart_items.image, products.image) as image'),
                'cart_items.product_weight',
                DB::raw('(cart_items.quantity * products.price) as subtotal'),
                'cart_items.created_at',
                'cart_items.updated_at'
            )
            ->where('cart_items.cart_id', $cart->id)
            ->get();

        $items = $items->map(function ($item) {
            if (is_string($item->product_name)) {
                $trimmed = trim($item->product_name);
                if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
                    $decoded = json_decode($trimmed, true);
                    if (is_array($decoded)) {
                        $item->product_name = $decoded;
                    }
                }
            }
            if (is_string($item->product_slug)) {
                $trimmed = trim($item->product_slug);
                if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
                    $decoded = json_decode($trimmed, true);
                    if (is_array($decoded)) {
                        $item->product_slug = $decoded;
                    }
                }
            }

            // Real-time In-Stock Validation
            $availableStock = (int) ($item->stock_quantity ?? 0);
            $isOutOfStock = ($availableStock <= 0) || !$item->is_visible;
            $item->is_out_of_stock = $isOutOfStock;
            $item->available_stock = max(0, $availableStock);

            if (!$isOutOfStock && $item->quantity > $availableStock) {
                $item->quantity = $availableStock;
                $item->subtotal = (float) ($availableStock * (float) $item->product_price);
                DB::table('cart_items')->where('id', $item->id)->update(['quantity' => $availableStock]);
            }

            return $item;
        });

        $total = (float) $items->sum('subtotal');
        $itemsCount = (int) $items->sum('quantity');

        return [
            'cart_id' => $cart->id,
            'session_id' => $cart->session_id,
            'user_id' => $cart->user_id,
            'items' => $items,
            'items_count' => $itemsCount,
            'total' => $total,
            'created_at' => $cart->created_at,
            'updated_at' => $cart->updated_at,
        ];
    }

    /**
     * Transfer/merge items from a guest cart into a user master cart without duplicates.
     */
    protected function mergeGuestIntoUserCart(Cart $guestCart, Cart $userCart): void
    {
        if ($guestCart->id === $userCart->id) {
            return;
        }

        foreach ($guestCart->items as $guestItem) {
            $product = Product::find($guestItem->product_id);
            if (!$product) {
                continue;
            }

            $availableStock = (int) ($product->quantity ?? 999);
            $existing = $userCart->items()->where('product_id', $guestItem->product_id)->first();

            if ($existing) {
                // Add quantities up
                $newQty = $existing->quantity + $guestItem->quantity;
                if ($availableStock > 0 && $newQty > $availableStock) {
                    $newQty = $availableStock;
                }
                $existing->update(['quantity' => $newQty]);
            } else {
                $qtyToAdd = ($availableStock > 0 && $guestItem->quantity > $availableStock)
                    ? $availableStock
                    : $guestItem->quantity;

                $userCart->items()->create([
                    'product_id' => $guestItem->product_id,
                    'quantity' => $qtyToAdd,
                    'price' => $guestItem->price ?: $product->price,
                    'image' => $guestItem->image ?: $product->image,
                    'product_weight' => $guestItem->product_weight ?: ($product->weight ?: '0'),
                ]);
            }
        }

        // Delete guest cart to avoid orphaned or duplicate records
        $guestCart->items()->delete();
        $guestCart->delete();
    }

    public function index(Request $request)
    {
        $cart = session()->get('cart', []);
        return response()->json(['cart' => $cart]);
    }

    /**
     * Add product to cart (cross-device user master cart if logged in, otherwise guest session).
     */
    public function add(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'product_id' => 'required|exists:products,id',
                'quantity' => 'required|integer|min:1',
            ]);

            $product = Product::visibleToCustomers()->findOrFail($request->product_id);
            $availableStock = (int) ($product->quantity ?? 0);

            if ($availableStock <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Sorry, '{$product->name}' is currently out of stock.",
                ], 422);
            }

            $customerId = $this->resolveCustomerId($request);

            if ($customerId) {
                $cart = Cart::where('user_id', $customerId)
                    ->where('status', 'active')
                    ->latest('updated_at')
                    ->first();

                if (!$cart) {
                    $cart = Cart::create([
                        'user_id' => $customerId,
                        'session_id' => $request->session_id,
                        'status' => 'active',
                    ]);
                } elseif ($request->session_id && $cart->session_id !== $request->session_id) {
                    $cart->session_id = $request->session_id;
                }

                $customer = Customer::find($customerId);
                if ($customer) {
                    $cart->customer_name = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''));
                    $cart->customer_email = $customer->email;
                    $cart->customer_phone = $customer->phone;
                }

                $cart->status = 'active';
                $cart->ensureRecoveryToken();
                $cart->save();

                $this->updateOrCreateCartItem($cart, $product, $request->quantity);
            } else {
                if (!$request->session_id) {
                    return response()->json(['error' => 'session_id is required for guest cart'], 422);
                }

                $cart = Cart::where('session_id', $request->session_id)
                    ->whereNull('user_id')
                    ->where('status', 'active')
                    ->latest('updated_at')
                    ->first();

                if (!$cart) {
                    $cart = Cart::create([
                        'session_id' => $request->session_id,
                        'user_id' => null,
                        'status' => 'active',
                    ]);
                }

                $cart->status = 'active';
                $cart->ensureRecoveryToken();
                $cart->save();

                $this->updateOrCreateCartItem($cart, $product, $request->quantity);
            }

            return response()->json([
                'message' => 'Product added to cart',
                'cart' => $this->formatCartResponse($cart),
                'total_products_count' => $cart->items()->sum('quantity'),
                'session_id' => $cart->session_id,
            ]);
        } catch (\Exception $e) {
            logger()->error('Cart add error:', [
                'message' => $e->getMessage(),
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    protected function updateOrCreateCartItem(Cart $cart, Product $product, int $quantity): void
    {
        $cartItem = $cart->items()->where('product_id', $product->id)->first();
        $availableStock = (int) ($product->quantity ?? 0);

        if ($cartItem) {
            $newQty = $cartItem->quantity + $quantity;
            if ($availableStock > 0 && $newQty > $availableStock) {
                $newQty = $availableStock;
            }
            $cartItem->update(['quantity' => $newQty]);
        } else {
            $qtyToAdd = ($availableStock > 0 && $quantity > $availableStock) ? $availableStock : $quantity;
            $cart->items()->create([
                'product_id' => $product->id,
                'quantity' => $qtyToAdd,
                'price' => $product->price,
                'image' => $product->image,
                'product_weight' => $product->weight ?: '0',
            ]);
        }
    }

    /**
     * View cart items (cross-device sync: logged-in user always gets their master cart).
     */
    public function viewcart(Request $request): JsonResponse
    {
        $customerId = $this->resolveCustomerId($request);
        $cart = null;

        if ($customerId) {
            // Find customer's master active cart
            $cart = Cart::where('user_id', $customerId)
                ->where('status', 'active')
                ->latest('updated_at')
                ->first();

            // Auto-merge if guest session exists on this device
            if ($request->session_id) {
                $guestCart = Cart::where('session_id', $request->session_id)
                    ->whereNull('user_id')
                    ->where('status', 'active')
                    ->first();

                if ($guestCart && $guestCart->items()->count() > 0) {
                    if (!$cart) {
                        $cart = Cart::create([
                            'user_id' => $customerId,
                            'session_id' => $request->session_id,
                            'status' => 'active',
                        ]);
                    }
                    $this->mergeGuestIntoUserCart($guestCart, $cart);
                }
            }
        } else {
            // Strictly guest cart: session_id must have user_id IS NULL
            if ($request->session_id) {
                $cart = Cart::where('session_id', $request->session_id)
                    ->whereNull('user_id')
                    ->where('status', 'active')
                    ->latest('updated_at')
                    ->first();
            }
        }

        if (!$cart) {
            return response()->json([
                'cart_id' => null,
                'session_id' => $request->session_id,
                'user_id' => $customerId,
                'items' => [],
                'items_count' => 0,
                'total' => 0,
            ]);
        }

        return response()->json($this->formatCartResponse($cart));
    }

    /**
     * Smart Cart Merge: Called upon login/registration.
     * Merges current guest cart (and local items) into user's master account cart.
     */
    public function merge(Request $request): JsonResponse
    {
        try {
            $customerId = $this->resolveCustomerId($request);

            if (!$customerId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User must be authenticated to merge cart',
                ], 401);
            }

            $customer = Customer::find($customerId);
            $sessionId = $request->session_id;

            // 1. Master User Cart
            $userCart = Cart::where('user_id', $customerId)
                ->where('status', 'active')
                ->latest('updated_at')
                ->first();

            if (!$userCart) {
                $userCart = Cart::create([
                    'user_id' => $customerId,
                    'session_id' => $sessionId,
                    'status' => 'active',
                ]);
            }

            if ($customer) {
                $userCart->customer_name = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''));
                $userCart->customer_email = $customer->email;
                $userCart->customer_phone = $customer->phone;
            }
            $userCart->session_id = $sessionId ?: $userCart->session_id;
            $userCart->status = 'active';
            $userCart->save();

            // 2. Transfer items from guest cart
            if ($sessionId) {
                $guestCart = Cart::where('session_id', $sessionId)
                    ->whereNull('user_id')
                    ->where('status', 'active')
                    ->first();

                if ($guestCart && $guestCart->id !== $userCart->id) {
                    $this->mergeGuestIntoUserCart($guestCart, $userCart);
                }
            }

            // 3. Merge local items passed in payload from frontend LocalStorage
            $localItems = $request->input('local_items', []);
            if (is_array($localItems) && count($localItems) > 0) {
                foreach ($localItems as $local) {
                    $pid = $local['productId'] ?? ($local['product_id'] ?? null);
                    $qty = (int) ($local['quantity'] ?? 1);
                    if (!$pid || $qty <= 0) {
                        continue;
                    }

                    $product = Product::find($pid);
                    if (!$product) {
                        continue;
                    }

                    $availableStock = (int) ($product->quantity ?? 999);
                    $existing = $userCart->items()->where('product_id', $pid)->first();

                    if ($existing) {
                        $newQty = max($existing->quantity, $qty);
                        if ($availableStock > 0 && $newQty > $availableStock) {
                            $newQty = $availableStock;
                        }
                        $existing->update(['quantity' => $newQty]);
                    } else {
                        $qtyToAdd = ($availableStock > 0 && $qty > $availableStock) ? $availableStock : $qty;
                        $userCart->items()->create([
                            'product_id' => $pid,
                            'quantity' => $qtyToAdd,
                            'price' => $product->price,
                            'image' => $product->image,
                            'product_weight' => $product->weight ?: '0',
                        ]);
                    }
                }
            }

            return response()->json($this->formatCartResponse($userCart));
        } catch (\Exception $e) {
            logger()->error('Cart merge error:', [
                'message' => $e->getMessage(),
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function remove(Request $request): JsonResponse
    {
        $productId = $request->product_id;
        $cart = $this->resolveCart($request);

        if ($cart) {
            $deleted = $cart->items()->where('product_id', $productId)->delete();
            return response()->json([
                'success' => (bool) $deleted,
                'message' => $deleted ? 'Item removed' : 'Item not found in cart',
            ]);
        }

        return response()->json(['success' => false, 'message' => 'Cart not found']);
    }

    public function empty(Request $request): JsonResponse
    {
        $cart = $this->resolveCart($request);

        if ($cart) {
            $cart->items()->delete();
            return response()->json(['message' => 'Cart emptied successfully']);
        }

        return response()->json(['message' => 'Cart not found']);
    }

    public function cartupdate(Request $request): JsonResponse
    {
        $productId = $request->product_id;
        $quantityChange = (int) $request->quantity_change;

        if (!in_array($quantityChange, [1, -1])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid quantity change value',
            ]);
        }

        $cart = $this->resolveCart($request);

        if ($cart) {
            $cartItem = $cart->items()->where('product_id', $productId)->first();

            if ($cartItem) {
                $newQuantity = $cartItem->quantity + $quantityChange;

                if ($newQuantity < 1) {
                    $cart->items()->where('product_id', $productId)->delete();
                    return response()->json([
                        'success' => true,
                        'message' => 'Item removed from cart',
                        'new_quantity' => 0,
                    ]);
                }

                $product = Product::find($productId);
                $availableStock = (int) ($product->quantity ?? 0);
                if ($product && $quantityChange > 0 && $newQuantity > $availableStock) {
                    return response()->json([
                        'success' => false,
                        'message' => "Only {$availableStock} unit(s) available in stock",
                    ], 422);
                }

                $updated = $cartItem->update(['quantity' => $newQuantity]);

                return response()->json([
                    'success' => (bool) $updated,
                    'message' => $updated ? 'Quantity updated' : 'Failed to update quantity',
                    'new_quantity' => $newQuantity,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Item not found in cart',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Cart not found',
        ]);
    }

    /**
     * Auto-sync customer contact info during checkout (for guest and authenticated users).
     */
    public function syncCustomer(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'session_id' => 'required|string',
                'customer_name' => 'nullable|string|max:150',
                'first_name' => 'nullable|string|max:100',
                'last_name' => 'nullable|string|max:100',
                'email' => 'nullable|email|max:150',
                'phone' => 'nullable|string|max:25',
            ]);

            $cart = $this->resolveCart($request) ?: Cart::where('session_id', $request->session_id)->first();
            if (!$cart) {
                return response()->json(['success' => false, 'message' => 'Cart not found'], 404);
            }

            $name = $request->customer_name;
            if (empty($name) && ($request->first_name || $request->last_name)) {
                $name = trim(($request->first_name ?? '') . ' ' . ($request->last_name ?? ''));
            }

            $updates = [];
            if (!empty($name)) {
                $updates['customer_name'] = $name;
            }
            if (!empty($request->email)) {
                $updates['customer_email'] = $request->email;
            }
            if (!empty($request->phone)) {
                $cleanPhone = preg_replace('/\D/', '', (string) $request->phone);
                if (str_starts_with($cleanPhone, '91') && strlen($cleanPhone) > 10) {
                    $cleanPhone = substr($cleanPhone, 2);
                } elseif (str_starts_with($cleanPhone, '0') && strlen($cleanPhone) > 10) {
                    $cleanPhone = substr($cleanPhone, 1);
                }
                $updates['customer_phone'] = $cleanPhone;
            }

            if (!empty($updates)) {
                $cart->update($updates);
            }

            return response()->json([
                'success' => true,
                'cart_id' => $cart->id,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Restore abandoned cart when customer visits recovery link.
     */
    public function recover(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'token' => 'required|string',
                'session_id' => 'required|string',
            ]);

            $cart = Cart::with(['items.product', 'customer'])
                ->where('recovery_token', $request->token)
                ->first();

            if (!$cart) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired recovery link.',
                ], 404);
            }

            $targetSessionId = $request->session_id;
            $targetCart = Cart::where('session_id', $targetSessionId)->first();

            if ($targetCart && $targetCart->id !== $cart->id) {
                foreach ($cart->items as $item) {
                    $existing = $targetCart->items()->where('product_id', $item->product_id)->first();
                    if ($existing) {
                        $existing->update(['quantity' => max($existing->quantity, $item->quantity)]);
                    } else {
                        $targetCart->items()->create([
                            'product_id' => $item->product_id,
                            'quantity' => $item->quantity,
                            'price' => $item->price,
                            'image' => $item->image,
                            'product_weight' => $item->product_weight,
                        ]);
                    }
                }

                $targetCart->update([
                    'customer_name' => $targetCart->customer_name ?: $cart->customer_name,
                    'customer_email' => $targetCart->customer_email ?: $cart->customer_email,
                    'customer_phone' => $targetCart->customer_phone ?: $cart->customer_phone,
                    'status' => 'active',
                ]);

                $activeCart = $targetCart;
            } else {
                $cart->update([
                    'session_id' => $targetSessionId,
                    'status' => 'active',
                ]);
                $activeCart = $cart;
            }

            return response()->json([
                'success' => true,
                'message' => 'Cart successfully restored!',
                'cart' => $activeCart->load('items.product'),
                'customer' => [
                    'name' => $activeCart->customer_name ?: ($activeCart->customer ? trim(($activeCart->customer->first_name ?? '') . ' ' . ($activeCart->customer->last_name ?? '')) : null),
                    'email' => $activeCart->customer_email ?: $activeCart->customer?->email,
                    'phone' => $activeCart->customer_phone ?: $activeCart->customer?->phone,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
