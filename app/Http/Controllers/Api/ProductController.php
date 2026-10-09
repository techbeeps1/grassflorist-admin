<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Determine active request locale ('ar' by default, or 'en' if /en/ prefix or query).
     */
    protected function getLocale(Request $request): string
    {
        // 1. Explicit English check
        if (
            $request->routeIs('*en*') ||
            $request->segment(1) === 'en' ||
            $request->segment(2) === 'en' ||
            $request->query('locale') === 'en' ||
            $request->query('lang') === 'en' ||
            $request->header('X-Locale') === 'en' ||
            str_starts_with((string) $request->header('Accept-Language', ''), 'en')
        ) {
            return 'en';
        }

        // 2. Explicit Arabic check
        if (
            $request->routeIs('*ar*') ||
            $request->segment(1) === 'ar' ||
            $request->segment(2) === 'ar' ||
            $request->query('locale') === 'ar' ||
            $request->query('lang') === 'ar' ||
            $request->header('X-Locale') === 'ar'
        ) {
            return 'ar';
        }

        // 3. Default for Grass Florist is Arabic ('ar')
        return app()->getLocale() ?: 'ar';
    }

    /**
     * Format a compact product card for category listings, related_products, and bought_together.
     * Contains only card essentials: id, name, slug, price, mrp, in_stock, is_visible, image, image_path, gallery.
     */
    public function formatCardProduct(Product $product, string $locale = 'ar'): array
    {
        $imagePath = $product->image;
        $imageUrl = null;
        if ($imagePath) {
            $imageUrl = str_starts_with($imagePath, 'http') ? $imagePath : asset('storage/' . ltrim($imagePath, '/'));
        }

        $galleryUrls = [];
        foreach ((array) ($product->gallery ?? []) as $g) {
            if (empty($g)) continue;
            $galleryUrls[] = str_starts_with($g, 'http') ? $g : asset('storage/' . ltrim($g, '/'));
        }

        $price = (float) $product->price;
        $mrp = (float) ($product->mrp ?: $product->price);
        $quantity = (int) $product->quantity;

        $name = $product->getTranslation('name', $locale);
        if (empty($name) && is_array($product->name)) {
            $name = $product->name[$locale] ?? reset($product->name);
        }
        if (empty($name)) {
            $name = $product->name;
        }

        $slug = ($locale === 'ar' && filled($product->slug_ar)) ? $product->slug_ar : $product->slug;

        $categoryIds = is_array($product->category_id)
            ? $product->category_id
            : (json_decode($product->category_id, true) ?: []);

        // Fast in-memory cache for all categories to prevent N+1 queries
        static $categoriesCache = null;
        if ($categoriesCache === null) {
            $categoriesCache = Category::all()->keyBy('id');
        }

        $formattedCategories = [];
        $categorySlugs = [];
        foreach ((array) $categoryIds as $cid) {
            $cidInt = (int) $cid;
            if (isset($categoriesCache[$cidInt])) {
                $c = $categoriesCache[$cidInt];
                $cName = $c->getTranslation('name', $locale) ?: (is_array($c->name) ? ($c->name[$locale] ?? reset($c->name)) : $c->name);
                $cSlug = ($locale === 'ar' && filled($c->slug_ar)) ? $c->slug_ar : $c->slug;
                $formattedCategories[] = [
                    'id' => $c->id,
                    'name' => $cName,
                    'slug' => $cSlug,
                    'slug_en' => $c->slug,
                    'slug_ar' => $c->slug_ar,
                ];
                if (!empty($c->slug)) $categorySlugs[] = $c->slug;
                if (!empty($c->slug_ar)) $categorySlugs[] = $c->slug_ar;
            }
        }

        return [
            'id' => $product->id,
            'name' => $name,
            'slug' => $slug,
            'price' => $price,
            'mrp' => $mrp,
            'in_stock' => $quantity > 0,
            'is_visible' => (bool) $product->is_visible,
            'image' => $imageUrl ?: $imagePath,
            'image_path' => $imagePath,
            'gallery' => $galleryUrls,
            'category_id' => $categoryIds,
            'category_ids' => array_values(array_map('intval', (array) $categoryIds)),
            'category_slugs' => array_values(array_unique($categorySlugs)),
            'categories' => $formattedCategories,
        ];
    }

    /**
     * Format a product instance to clean, detailed storefront JSON (excluding bookstore columns).
     */
    public function formatProduct(Product $product, string $locale = 'ar'): array
    {
        $categoryIds = is_array($product->category_id)
            ? $product->category_id
            : (json_decode($product->category_id, true) ?: []);

        $categories = Category::whereIn('id', (array) $categoryIds)->get();

        $formattedCategories = $categories->map(function ($cat) use ($locale) {
            $catImg = $cat->cat_image;
            $catImgUrl = null;
            if ($catImg) {
                $catImgUrl = str_starts_with($catImg, 'http') ? $catImg : asset('storage/' . ltrim($catImg, '/'));
            }
            return [
                'id' => $cat->id,
                'name' => $cat->getTranslation('name', $locale) ?: (is_array($cat->name) ? ($cat->name[$locale] ?? reset($cat->name)) : $cat->name),
                'slug' => ($locale === 'ar' && filled($cat->slug_ar)) ? $cat->slug_ar : $cat->slug,
                'image' => $catImgUrl ?: $catImg,
            ];
        })->values()->toArray();

        // Featured Image
        $imagePath = $product->image;
        $imageUrl = null;
        if ($imagePath) {
            $imageUrl = str_starts_with($imagePath, 'http') ? $imagePath : asset('storage/' . ltrim($imagePath, '/'));
        }

        // Gallery Images
        $galleryUrls = [];
        foreach ((array) ($product->gallery ?? []) as $g) {
            if (empty($g)) continue;
            $galleryUrls[] = str_starts_with($g, 'http') ? $g : asset('storage/' . ltrim($g, '/'));
        }

        $price = (float) $product->price;
        $mrp = (float) ($product->mrp ?: $product->price);
        $quantity = (int) $product->quantity;

        // Localized fields
        $name = $product->getTranslation('name', $locale);
        if (empty($name) && is_array($product->name)) {
            $name = $product->name[$locale] ?? reset($product->name);
        }
        if (empty($name)) {
            $name = $product->name;
        }

        $slug = ($locale === 'ar' && filled($product->slug_ar)) ? $product->slug_ar : $product->slug;

        $subTitle = $product->getTranslation('sub_title', $locale);
        if (empty($subTitle) && is_array($product->sub_title)) {
            $subTitle = $product->sub_title[$locale] ?? '';
        }
        if (empty($subTitle)) {
            $subTitle = is_string($product->sub_title) ? $product->sub_title : '';
        }

        $description = $product->getTranslation('description', $locale);
        if (empty($description) && is_array($product->description)) {
            $description = $product->description[$locale] ?? '';
        }
        if (empty($description)) {
            $description = is_string($product->description) ? $product->description : '';
        }

        return [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $name,
            'slug' => $slug,
            'sub_title' => $subTitle,
            'description' => $description,
            'price' => $price,
            'mrp' => $mrp,
            'quantity' => $quantity,
            'in_stock' => $quantity > 0,
            'is_visible' => (bool) $product->is_visible,
            'image' => $imageUrl ?: $imagePath,
            'image_path' => $imagePath,
            'gallery' => $galleryUrls,
            'categories' => $formattedCategories,
            'meta' => [
                'type' => $product->type ?: 'simple',
                'weight' => $product->meta_data['weight'] ?? null,
                'dimensions' => $product->meta_data['dimensions'] ?? null,
            ],
            'created_at' => $product->created_at?->toIso8601String(),
            'updated_at' => $product->updated_at?->toIso8601String(),
        ];
    }

    /**
     * List all customer-visible products in active locale.
     */
    public function index(Request $request): JsonResponse
    {
        $locale = $this->getLocale($request);
        $products = Product::visibleToCustomers()->get();

        return response()->json(
            $products->map(fn ($p) => $this->formatCardProduct($p, $locale))->values()
        );
    }

    /**
     * Show single product by slug or ID with related items in active locale.
     */
    public function show(Request $request, $slug): JsonResponse
    {
        $locale = $this->getLocale($request);
        $decodedSlug = urldecode($slug);

        $product = Product::visibleToCustomers()
            ->where(function ($q) use ($slug, $decodedSlug) {
                $q->where('slug', $slug)
                  ->orWhere('slug_ar', $slug)
                  ->orWhere('slug', $decodedSlug)
                  ->orWhere('slug_ar', $decodedSlug)
                  ->orWhere('sku', $slug)
                  ->orWhere('id', $slug);
            })
            ->first();

        if (! $product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        $relatedProducts = Product::visibleToCustomers()
            ->where('id', '!=', $product->id)
            ->where(function ($query) use ($product) {
                $categoryIds = is_array($product->category_id)
                    ? $product->category_id
                    : json_decode($product->category_id, true);

                if (! empty($categoryIds)) {
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
            'product' => $this->formatProduct($product, $locale),
            'related_products' => $relatedProducts->map(fn ($p) => $this->formatCardProduct($p, $locale))->values(),
            'bought_together' => $bought->map(fn ($p) => $this->formatCardProduct($p, $locale))->values(),
        ]);
    }

    /**
     * Get products by category slug in active locale.
     */
    public function productsByCategorySlug(Request $request, $slug): JsonResponse
    {
        try {
            $locale = $this->getLocale($request);
            $decodedSlug = urldecode($slug);

            $category = Category::where('slug', $slug)
                ->orWhere('slug_ar', $slug)
                ->orWhere('slug', $decodedSlug)
                ->orWhere('slug_ar', $decodedSlug)
                ->first();

            // Alias fallback (e.g. occasions <-> ocassions)
            if (! $category) {
                if ($slug === 'occasions' || $decodedSlug === 'occasions') {
                    $category = Category::where('slug', 'ocassions')->first();
                } elseif ($slug === 'ocassions' || $decodedSlug === 'ocassions') {
                    $category = Category::where('slug', 'occasions')->first();
                }
            }

            if (! $category) {
                return response()->json([
                    'error' => 'Category not found',
                ], 404);
            }

            $catIds = [$category->id];
            if ($category->child()->exists()) {
                $catIds = array_merge($catIds, $category->child()->pluck('id')->toArray());
            }

            $products = Product::visibleToCustomers()
                ->where(function ($query) use ($catIds) {
                    foreach ($catIds as $cid) {
                        $query->orWhereJsonContains('category_id', (int) $cid)
                              ->orWhereJsonContains('category_id', (string) $cid);
                    }
                })
                ->get();

            // Direct child subcategories of this category
            $directChildren = $category->child()->where('is_visible', true)->get();

            // If this category itself is a subcategory (has parent), get siblings too
            if ($directChildren->isEmpty() && $category->parent_id) {
                $directChildren = Category::where('parent_id', $category->parent_id)
                    ->where('is_visible', true)
                    ->get();
            }

            // Fallback: If category has no child records, check product categoryIds
            if ($directChildren->isEmpty()) {
                $categoryIds = $products->flatMap(function ($product) {
                    $ids = is_array($product->category_id)
                        ? $product->category_id
                        : json_decode($product->category_id, true);
                    return $ids ?: [];
                })->filter(fn ($id) => (int) $id !== (int) $category->id)->unique()->values()->toArray();

                if (! empty($categoryIds)) {
                    $directChildren = Category::whereIn('id', $categoryIds)->where('is_visible', true)->get();
                }
            }

            $formattedSubcategories = $directChildren->map(function ($cat) use ($locale) {
                $subImg = $cat->cat_image;
                $subImgUrl = null;
                if ($subImg) {
                    $subImgUrl = str_starts_with($subImg, 'http') ? $subImg : asset('storage/' . ltrim($subImg, '/'));
                }
                return [
                    'id' => $cat->id,
                    'name' => $cat->getTranslation('name', $locale) ?: (is_array($cat->name) ? ($cat->name[$locale] ?? reset($cat->name)) : $cat->name),
                    'slug' => ($locale === 'ar' && filled($cat->slug_ar)) ? $cat->slug_ar : $cat->slug,
                    'description' => $cat->getTranslation('description', $locale) ?: (is_array($cat->description) ? ($cat->description[$locale] ?? '') : $cat->description),
                    'image' => $subImgUrl ?: $subImg,
                    'image_path' => $subImg,
                    'is_visible' => (bool) $cat->is_visible,
                ];
            })->values();

            $catImg = $category->cat_image;
            $catImgUrl = null;
            if ($catImg) {
                $catImgUrl = str_starts_with($catImg, 'http') ? $catImg : asset('storage/' . ltrim($catImg, '/'));
            }

            return response()->json([
                'category' => [
                    'id' => $category->id,
                    'name' => $category->getTranslation('name', $locale) ?: (is_array($category->name) ? ($category->name[$locale] ?? reset($category->name)) : $category->name),
                    'slug' => ($locale === 'ar' && filled($category->slug_ar)) ? $category->slug_ar : $category->slug,
                    'description' => $category->getTranslation('description', $locale) ?: (is_array($category->description) ? ($category->description[$locale] ?? '') : $category->description),
                    'image' => $catImgUrl ?: $catImg,
                    'child' => $formattedSubcategories,
                    'subcategories' => $formattedSubcategories,
                ],
                'sub_categories' => $formattedSubcategories,
                'subcategories' => $formattedSubcategories,
                'products' => $products->map(fn ($p) => $this->formatCardProduct($p, $locale))->values(),
                'seo' => [
                    'meta_title' => $category->getTranslation('meta_tag_title', $locale) ?: $category->meta_tag_title,
                    'meta_description' => $category->getTranslation('meta_tag_description', $locale) ?: $category->meta_tag_description,
                    'meta_keywords' => $category->getTranslation('meta_tag_keywords', $locale) ?: $category->meta_tag_keywords,
                    'image' => $catImgUrl ?: $catImg,
                ],
            ]);
        } catch (\Exception $e) {
            logger()->error('Products by category error:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
