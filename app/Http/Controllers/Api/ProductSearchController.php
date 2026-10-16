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

        // 1. Keyword search (searches products AND categories with prioritized relevance)
        $searchTerm = trim((string)$request->input('q', $request->input('key', $request->input('query'))));

        if (!empty($searchTerm)) {
            $rawTokens = preg_split('/[\s,\-_+\/]+/u', $searchTerm);
            $tokens = array_values(array_filter($rawTokens, fn ($t) => mb_strlen(trim($t)) >= 2));

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
                ->get()
                ->sortByDesc(function ($cat) use ($searchTerm) {
                    $catName = is_array($cat->name) ? json_encode($cat->name, JSON_UNESCAPED_UNICODE) : (string)$cat->name;
                    return (stripos($catName, $searchTerm) !== false) ? 100 : 10;
                })
                ->values();

            $matchingCategoryIds = $matchingCategories->pluck('id')->toArray();

            // Build Relevance Scoring based on Order Priority:
            // 1. Product Name (10000 full phrase / 1000 per token)
            // 2. SKU (8000 exact, 6000 substring / 600 per token)
            // 3. Sub title (4000 full phrase / 400 per token)
            // 4. Description (2000 full phrase / 200 per token)
            $scoreCases = [];
            $bindings = [];

            // Priority 1: Full phrase in Name
            $scoreCases[] = "CASE WHEN name LIKE ? THEN 10000 ELSE 0 END";
            $bindings[] = "%{$searchTerm}%";

            // Priority 2: SKU match
            $scoreCases[] = "CASE WHEN sku = ? THEN 8000 WHEN sku LIKE ? THEN 6000 ELSE 0 END";
            $bindings[] = $searchTerm;
            $bindings[] = "%{$searchTerm}%";

            // Priority 3: Full phrase in Subtitle
            $scoreCases[] = "CASE WHEN sub_title LIKE ? THEN 4000 ELSE 0 END";
            $bindings[] = "%{$searchTerm}%";

            // Priority 4: Full phrase in Description
            $scoreCases[] = "CASE WHEN description LIKE ? THEN 2000 ELSE 0 END";
            $bindings[] = "%{$searchTerm}%";

            // Token-level scoring
            foreach ($tokens as $token) {
                // Name token
                $scoreCases[] = "CASE WHEN name LIKE ? THEN 1000 ELSE 0 END";
                $bindings[] = "%{$token}%";

                // SKU token
                $scoreCases[] = "CASE WHEN sku LIKE ? THEN 600 ELSE 0 END";
                $bindings[] = "%{$token}%";

                // Subtitle token
                $scoreCases[] = "CASE WHEN sub_title LIKE ? THEN 400 ELSE 0 END";
                $bindings[] = "%{$token}%";

                // Description token
                $scoreCases[] = "CASE WHEN description LIKE ? THEN 200 ELSE 0 END";
                $bindings[] = "%{$token}%";
            }

            $relevanceSql = '(' . implode(' + ', $scoreCases) . ')';

            $query->select('products.*')
                  ->selectRaw("{$relevanceSql} as search_relevance", $bindings);

            $query->where(function ($subQuery) use ($searchTerm, $tokens, $matchingCategoryIds) {
                // Match products by name, description, sku, sub_title, slug
                $subQuery->where('name', 'like', "%{$searchTerm}%")
                         ->orWhere('sku', 'like', "%{$searchTerm}%")
                         ->orWhere('sub_title', 'like', "%{$searchTerm}%")
                         ->orWhere('description', 'like', "%{$searchTerm}%")
                         ->orWhere('slug', 'like', "%{$searchTerm}%")
                         ->orWhere('slug_ar', 'like', "%{$searchTerm}%");

                foreach ($tokens as $token) {
                    $subQuery->orWhere('name', 'like', "%{$token}%")
                             ->orWhere('sku', 'like', "%{$token}%")
                             ->orWhere('sub_title', 'like', "%{$token}%")
                             ->orWhere('description', 'like', "%{$token}%");
                }

                if (!empty($matchingCategoryIds)) {
                    foreach ($matchingCategoryIds as $catId) {
                        $subQuery->orWhereJsonContains('category_id', (int) $catId)
                                 ->orWhereJsonContains('category_id', (string) $catId);
                    }
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

        // 5. Sorting (Priority: Search Relevance when searching, unless explicit user sort)
        if ($request->filled('sort_by') && $request->input('sort_by') !== 'relevance') {
            $sortField = $request->input('sort_by');
            $sortDirection = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
            $allowedSorts = ['created_at', 'price', 'name', 'id'];
            if (in_array($sortField, $allowedSorts)) {
                $query->orderBy($sortField, $sortDirection);
            } else {
                $query->orderBy('created_at', 'desc');
            }
        } else {
            if (!empty($searchTerm)) {
                $query->orderByDesc('search_relevance')
                      ->orderByDesc('created_at');
            } else {
                $query->orderBy('created_at', 'desc');
            }
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