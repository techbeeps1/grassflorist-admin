<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Support\Facades\DB;


class ProductController extends Controller
{
    // public function index()
    // {
    //     return response()->json(Product::with('category')->get());
        
    // }
    public function index()
    {
        $products = Product::visibleToCustomers()->get();

        return response()->json($products);
    }


    public function show($slug)
    {
        $product = Product::visibleToCustomers()
            ->with(['production', 'categories'])
            ->where(function ($q) use ($slug) {
                $q->where('slug', $slug)
                  ->orWhere('slug_ar', $slug);
            })
            ->firstOrFail();

        $relatedProducts = Product::visibleToCustomers()
            ->where('id', '!=', $product->id)
            ->where(function ($query) use ($product) {
                $categoryIds = is_array($product->category_id)
                    ? $product->category_id
                    : json_decode($product->category_id, true);

                if (!empty($categoryIds)) {
                    foreach ($categoryIds as $category) {
                        $query->orWhereJsonContains('category_id', (int) $category)
                              ->orWhereJsonContains('category_id', (string) $category);
                    }
                }
            })
            ->inRandomOrder()
            ->limit(5)
            ->get();

        $excludeIds = $relatedProducts->pluck('id')->push($product->id);

        $bought = Product::visibleToCustomers()
            ->whereNotIn('id', $excludeIds)
            ->inRandomOrder()
            ->limit(3)
            ->get();

        return response()->json([
            'product' => $product,
            'related_products' => $relatedProducts,
            'bought_together' => $bought
        ]);
    }

    public function productsByCategorySlug($slug)
    {
        try {
            $category = Category::where('slug', $slug)->first();
            if (!$category) {
                return response()->json([
                    'error' => 'Category not found'
                ], 404);
            }

            $products = \App\Models\Product::visibleToCustomers()
                ->where(function ($query) use ($category) {
                    $query->whereJsonContains('category_id', (string) $category->id);
                })
                ->select(
                    'id',
                    'production_id',
                    'category_id',
                    'name',
                    'slug',
                    'sku',
                    'model',
                    'image',
                    'description',
                    'mrp',
                    'price',
                    'quantity',
                    'book_language',
                )
                ->get();

            // Get unique Sub Category IDs
            $subCategoryIds = $products->pluck('sub_category_id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

            // Get unique Production IDs
            $productionIds = $products->pluck('production_id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

            // Sub Categories
            // $subcategories = DB::table('categories')
            // ->select('id', 'name', 'slug', 'parent_id')
            // ->whereIn('id', $subCategoryIds)
            // ->get();

            $categoryIds = $products->flatMap(function ($product) {

            // category_id is stored as JSON
            $ids = is_array($product->category_id)
            ? $product->category_id
            : json_decode($product->category_id, true);

            return $ids ?: [];
            })->unique()->values()->toArray();

            // Get unique Sub Category IDs
            $subcategories = DB::table('categories')
            ->select('id', 'name')
            ->whereIn('id', $categoryIds)
            ->get();

            // Productions
            $productions = DB::table('productions')
            ->select('id', 'name')
            ->where('is_visible', 1)
            ->whereIn('id', $productionIds)
            ->get();

            $data = [
            'parent-cateogry'=> $category,
            'category'   => $subcategories,
            'products'   => $products,
            'production' => $productions,
            'seo'  => [
            'meta_title' => $category->meta_tag_title,
            'meta_description' => $category->meta_tag_description,
            'meta_keywords' => $category->meta_tag_keywords,
            'image' => $category->cat_image,
            ]
            ];



return response()->json($data);

        //return response()->json($products);
    }
        catch (\Exception $e) {
            logger()->error('Registration error:', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
