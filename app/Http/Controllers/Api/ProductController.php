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
     * Determine active request locale ('en' or 'ar').
     */
    protected function getLocale(Request $request): string
    {
        if (
            $request->routeIs('*ar*') ||
            $request->segment(1) === 'ar' ||
            $request->segment(2) === 'ar' ||
            $request->query('lang') === 'ar' ||
            $request->header('X-Locale') === 'ar'
        ) {
            return 'ar';
        }

        if ($request->has('lang')) {
            $lang = strtolower(substr((string) $request->query('lang'), 0, 2));
            if (in_array($lang, ['en', 'ar'])) {
                return $lang;
            }
        }

        return app()->getLocale() ?: 'en';
    }

    /**
     * Format a product instance to clean, localized storefront JSON (excluding bookstore columns).
     */
    public function formatProduct(Product $product, string $locale = 'en'): array
    {
        $categoryIds = is_array($product->category_id)
            ? $product->category_id
            : (json_decode($product->category_id, true) ?: []);

        $categories = ($product->relationLoaded('categories') && $product->categories->isNotEmpty())
            ? $product->categories
            : Category::whereIn('id', (array) $categoryIds)->get();

        $formattedCategories = $categories->map(function ($cat) use ($locale) {
            $catImg = $cat->cat_image;
            $catImgUrl = null;
            if ($catImg) {
                $catImgUrl = str_starts_with($catImg, 'http') ? $catImg : asset('storage/' . ltrim($catImg, '/'));
            }
            return [
                'id' => $cat->id,
                'name' => $cat->getTranslation('name', $locale) ?: $cat->name,
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
            $products->map(fn ($p) => $this->formatProduct($p, $locale))->values()
        );
    }

    /**
     * Format a compact product card for related_products and bought_together.
     */
    public function formatRelatedProduct(Product $product, string $locale = 'en'): array
    {
        $imagePath = $product->image;
        $imageUrl = null;
        if ($imagePath) {
            $imageUrl = str_starts_with($imagePath, 'http') ? $imagePath : asset('storage/' . ltrim($imagePath, '/'));
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
        ];
    }

    /**
     * Show single product by slug or ID with related items in active locale.
     */
    public function show(Request $request, $slug): JsonResponse
    {
        $locale = $this->getLocale($request);

        $product = Product::visibleToCustomers()
            ->with(['categories'])
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
            'related_products' => $relatedProducts->map(fn ($p) => $this->formatRelatedProduct($p, $locale))->values(),
            'bought_together' => $bought->map(fn ($p) => $this->formatRelatedProduct($p, $locale))->values(),
        ]);
    }

    /**
     * Get products by category slug in active locale.
     */
    public function productsByCategorySlug(Request $request, $slug): JsonResponse
    {
        try {
            $locale = $this->getLocale($request);

            $category = Category::where('slug', $slug)->orWhere('slug_ar', $slug)->first();
            if (! $category) {
                return response()->json([
                    'error' => 'Category not found',
                ], 404);
            }

            $products = Product::visibleToCustomers()
                ->where(function ($query) use ($category) {
                    $query->whereJsonContains('category_id', (int) $category->id)
                          ->orWhereJsonContains('category_id', (string) $category->id);
                })
                ->get();

            $categoryIds = $products->flatMap(function ($product) {
                $ids = is_array($product->category_id)
                    ? $product->category_id
                    : json_decode($product->category_id, true);
                return $ids ?: [];
            })->unique()->values()->toArray();

            $subcategories = Category::whereIn('id', $categoryIds)->get()->map(function ($cat) use ($locale) {
                return [
                    'id' => $cat->id,
                    'name' => $cat->getTranslation('name', $locale) ?: $cat->name,
                    'slug' => ($locale === 'ar' && filled($cat->slug_ar)) ? $cat->slug_ar : $cat->slug,
                ];
            });

            $catImg = $category->cat_image;
            $catImgUrl = null;
            if ($catImg) {
                $catImgUrl = str_starts_with($catImg, 'http') ? $catImg : asset('storage/' . ltrim($catImg, '/'));
            }

            return response()->json([
                'category' => [
                    'id' => $category->id,
                    'name' => $category->getTranslation('name', $locale) ?: $category->name,
                    'slug' => ($locale === 'ar' && filled($category->slug_ar)) ? $category->slug_ar : $category->slug,
                    'description' => $category->getTranslation('description', $locale) ?: $category->description,
                    'image' => $catImgUrl ?: $catImg,
                ],
                'sub_categories' => $subcategories,
                'products' => $products->map(fn ($p) => $this->formatProduct($p, $locale))->values(),
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
