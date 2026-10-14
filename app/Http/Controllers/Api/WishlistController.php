<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Wishlist;
use App\Models\Product;

class WishlistController extends Controller
{
    /**
     * Get all wishlist products for the authenticated customer
     */
    public function index()
    {
        $user = auth('customer')->user();
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated',
                'data' => []
            ], 401);
        }

        $wishlist = Wishlist::where('user_id', $user->id)->first();

        if (!$wishlist || empty($wishlist->products)) {
            return response()->json([
                'status' => true,
                'data' => []
            ]);
        }

        $products = Product::visibleToCustomers()
            ->whereIn('id', (array)$wishlist->products)
            ->get();

        return response()->json([
            'status' => true,
            'data' => $products
        ]);
    }

    /**
     * Get array of product IDs in wishlist for authenticated customer
     */
    public function wishlistid()
    {
        $user = auth('customer')->user();
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated',
                'data' => []
            ], 401);
        }

        $wishlist = Wishlist::where('user_id', $user->id)->first();

        $ids = ($wishlist && is_array($wishlist->products))
            ? array_values(array_map('intval', array_filter($wishlist->products)))
            : [];

        return response()->json([
            'status' => true,
            'data' => $ids
        ]);
    }

    /**
     * Add product to customer wishlist
     */
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer'
        ]);

        $user = auth('customer')->user();
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Please log in to add items to your wishlist.',
            ], 401);
        }

        $productId = (int)$request->product_id;
        $product = Product::visibleToCustomers()->find($productId);
        if (!$product) {
            return response()->json([
                'status' => false,
                'message' => 'Product not found or unavailable.',
            ], 404);
        }

        $wishlist = Wishlist::firstOrCreate(
            ['user_id' => $user->id],
            ['products' => []]
        );

        $products = is_array($wishlist->products) ? $wishlist->products : [];

        if (in_array($productId, $products, true)) {
            return response()->json([
                'status' => true,
                'message' => 'Product is already in your wishlist.',
                'data' => array_values($products)
            ]);
        }

        $products[] = $productId;
        $wishlist->products = array_values(array_unique($products));
        $wishlist->save();

        return response()->json([
            'status' => true,
            'message' => 'Wishlist updated successfully.',
            'data' => array_values($wishlist->products)
        ]);
    }

    /**
     * Remove product from customer wishlist
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer'
        ]);

        $user = auth('customer')->user();
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Please log in to manage your wishlist.',
            ], 401);
        }

        $productId = (int)$request->product_id;
        $wishlist = Wishlist::where('user_id', $user->id)->first();

        if (!$wishlist) {
            return response()->json([
                'status' => true,
                'message' => 'Wishlist not found.',
                'data' => []
            ]);
        }

        $products = is_array($wishlist->products) ? $wishlist->products : [];
        $products = array_values(array_filter($products, function ($id) use ($productId) {
            return (int)$id !== $productId;
        }));

        $wishlist->products = $products;
        $wishlist->save();

        return response()->json([
            'status' => true,
            'message' => 'Product removed successfully.',
            'data' => $products
        ]);
    }
}
