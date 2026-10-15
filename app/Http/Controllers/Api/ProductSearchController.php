<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ProductSearchController extends Controller
{
    protected function getLocale(Request $request): string
    {
        if (
            $request->routeIs('*en*') ||
            $request->segment(1) === 'en' ||
            $request->segment(2) === 'en' ||
            $request->query('lang') === 'en' ||
            $request->header('X-Locale') === 'en'
        ) {
            return 'en';
        }

        if (
            $request->routeIs('*ar*') ||
            $request->segment(1) === 'ar' ||
            $request->segment(2) === 'ar' ||
            $request->query('lang') === 'ar' ||
            $request->header('X-Locale') === 'ar'
        ) {
            return 'ar';
        }

        return app()->getLocale() ?: 'ar';
    }

    public function search(Request $request): JsonResponse
    {
        $locale = $this->getLocale($request);
        $query = Product::visibleToCustomers();
        $matchingCategories = collect();

        // 1. Keyword search (searches products AND categories)
        $searchTerm = $request->input('q', $request->input('key', $request->input('query')));
        if (!empty($searchTerm) && trim($searchTerm) !== '') {
            $rawTokens = preg_split('/[\s,\-_+\/]+/u', trim($searchTerm));
            $tokens = array_values(array_filter($rawTokens, fn ($t) => strlen(trim($t)) > 0));

            // Find matching categories by name, slug, slug_ar, or description
            $matchingCategories = Category::where('is_visible', true)
                ->where(function ($catQuery) use ($tokens, $searchTerm) {
                    $catQuery->where('name', 'like', "%{$searchTerm}%")
                             ->orWhere('slug', 'like', "%{$searchTerm}%")
                             ->orWhere('slug_ar', 'like', "%{$searchTerm}%")
                             ->orWhere('description', 'like', "%{$searchTerm}%");

                    foreach ($tokens as $token) {
                        $catQuery->orWhere('name', 'like', "%{$token}%")
                                 ->orWhere('slug', 'like', "%{$token}%")
                                 ->orWhere('slug_ar', 'like', "%{$token}%");
                    }
                })
                ->get();

            $matchingCategoryIds = $matchingCategories->pluck('id')->toArray();

            $query->where(function ($subQuery) use ($tokens, $matchingCategoryIds) {
                // Match products by name, description, sku, slug
                foreach ($tokens as $token) {
                    $subQuery->orWhere(function ($q) use ($token) {
                        $q->where('name', 'like', "%{$token}%")
                          ->orWhere('description', 'like', "%{$token}%")
                          ->orWhere('sku', 'like', "%{$token}%")
                          ->orWhere('slug', 'like', "%{$token}%")
                          ->orWhere('slug_ar', 'like', "%{$token}%");
                    });
                }

                // If any category matches the search term, include all products belonging to it
                foreach ($matchingCategoryIds as $catId) {
                    $subQuery->orWhereJsonContains('category_id', (int) $catId)
                             ->orWhereJsonContains('category_id', (string) $catId);
                }
            });
        }

        // 2. Category filter
        if ($request->filled('category')) {
            $catParam = $request->input('category');
            $targetCategory = is_numeric($catParam)
                ? Category::find($catParam)
                : Category::where('slug', $catParam)->orWhere('slug_ar', $catParam)->first();

            if ($targetCategory && ! $targetCategory->is_visible) {
                // Return empty result set since the requested category is hidden
                $query->whereRaw('1 = 0');
            } else {
                $category = $targetCategory ? $targetCategory->id : $catParam;
                $query->where(function ($q) use ($category) {
                    $q->whereJsonContains('category_id', (string) $category)
                      ->orWhereJsonContains('category_id', (int) $category);
                });
            }
        }

        // 3. Price range
        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float) $request->input('min_price'));
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->input('max_price'));
        }

        // 4. In Stock filter
        if ($request->boolean('in_stock_only')) {
            $query->where('quantity', '>', 0);
        }

        // 5. Sorting
        $sortField = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_dir', 'desc');
        $allowedSorts = ['created_at', 'price', 'name', 'id'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, strtolower($sortDirection) === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // 6. Pagination
        $perPage = (int) $request->input('per_page', 15);
        $products = $query->paginate($perPage);

        $productController = app(ProductController::class);
        $categoryController = app(CategoryController::class);

        $formattedProducts = collect($products->items())->map(fn ($p) => $productController->formatCardProduct($p, $locale))->values();
        $formattedCategories = $matchingCategories->map(fn ($c) => $categoryController->formatCategory($c, $locale))->values();

        return response()->json([
            'categories' => $formattedCategories,
            'current_page' => $products->currentPage(),
            'data' => $formattedProducts,
            'products' => $formattedProducts,
            'first_page_url' => $products->url(1),
            'from' => $products->firstItem(),
            'last_page' => $products->lastPage(),
            'last_page_url' => $products->url($products->lastPage()),
            'next_page_url' => $products->nextPageUrl(),
            'path' => $products->path(),
            'per_page' => $products->perPage(),
            'prev_page_url' => $products->previousPageUrl(),
            'to' => $products->lastItem(),
            'total' => $products->total(),
        ]);
    }
}