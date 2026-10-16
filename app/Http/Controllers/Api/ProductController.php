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
        // 1. Explicit URL check
        if ($request->routeIs('*en*') || $request->segment(1) === 'en' || $request->segment(2) === 'en') {
            return 'en';
        }
        if ($request->routeIs('*ar*') || $request->segment(1) === 'ar' || $request->segment(2) === 'ar') {
            return 'ar';
        }

        // 2. Explicit query parameter (?locale=... or ?lang=...)
        if ($request->query('locale') === 'en' || $request->query('lang') === 'en') {
            return 'en';
        }
        if ($request->query('locale') === 'ar' || $request->query('lang') === 'ar') {
            return 'ar';
        }

        // 3. Explicit custom header X-Locale
        if ($request->header('X-Locale') === 'en') {
            return 'en';
        }
        if ($request->header('X-Locale') === 'ar') {
            return 'ar';
        }

        // 4. Accept-Language header fallback
        $acceptLang = (string) $request->header('Accept-Language', '');
        if (str_starts_with($acceptLang, 'en')) {
            return 'en';
        }
        if (str_starts_with($acceptLang, 'ar')) {
            return 'ar';
        }

        // 5. Default for Grass Florist is Arabic ('ar')
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
            $imageUrl = str_starts_with($imagePath, 'http') ? $imagePath : storage_media_url($imagePath);
        }

        $galleryUrls = [];
        foreach ((array) ($product->gallery ?? []) as $g) {
            if (empty($g)) continue;
            $galleryUrls[] = str_starts_with($g, 'http') ? $g : storage_media_url($g);
        }

        $price = (float) $product->price;
        $mrp = (float) ($product->mrp ?: $product->price);
        $quantity = (int) $product->quantity;

        $nameAr = $product->getTranslation('name', 'ar') ?: (is_array($product->name) ? ($product->name['ar'] ?? '') : (is_string($product->name) ? $product->name : ''));
        $nameEn = $product->getTranslation('name', 'en') ?: (is_array($product->name) ? ($product->name['en'] ?? '') : (is_string($product->name) ? $product->name : ''));
        if (empty($nameAr)) $nameAr = $nameEn;
        if (empty($nameEn)) $nameEn = $nameAr;
        $name = $locale === 'ar' ? ($nameAr ?: $nameEn) : ($nameEn ?: $nameAr);

        $slug = ($locale === 'ar' && filled($product->slug_ar)) ? $product->slug_ar : $product->slug;

        $categoryIds = is_array($product->category_id)
            ? $product->category_id
            : (json_decode($product->category_id, true) ?: []);

        // Fast in-memory cache for all visible categories to prevent N+1 queries
        static $categoriesCache = null;
        if ($categoriesCache === null) {
            $categoriesCache = Category::where('is_visible', true)->get()->keyBy('id');
        }

        $formattedCategories = [];
        $categorySlugs = [];
        foreach ((array) $categoryIds as $cid) {
            $cidInt = (int) $cid;
            if (isset($categoriesCache[$cidInt])) {
                $c = $categoriesCache[$cidInt];
                $cNameAr = $c->getTranslation('name', 'ar') ?: (is_array($c->name) ? ($c->name['ar'] ?? '') : (is_string($c->name) ? $c->name : ''));
                $cNameEn = $c->getTranslation('name', 'en') ?: (is_array($c->name) ? ($c->name['en'] ?? '') : (is_string($c->name) ? $c->name : ''));
                if (empty($cNameAr)) $cNameAr = $cNameEn;
                if (empty($cNameEn)) $cNameEn = $cNameAr;
                $cName = $locale === 'ar' ? ($cNameAr ?: $cNameEn) : ($cNameEn ?: $cNameAr);
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
            'slug_ar' => $product->slug_ar ?: $product->slug,
            'slug_en' => $product->slug,
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
            'is_subscription_enabled' => (bool) $product->is_subscription_enabled,
            'subscription_discount_percentage' => (float) ($product->subscription_discount_percentage ?? 10.00),
            'subscription_frequencies' => $product->subscription_frequencies ?? ['weekly', 'biweekly', 'monthly'],
            'subscription_durations' => $product->subscription_durations ?? ['3_months', '6_months', '12_months', 'until_cancelled'],
            'subscription_min_duration' => $product->subscription_min_duration ?? '3_months',
            'attributes' => $this->formatProductAttributes($product, $locale),
        ];
    }

    /**
     * Format attributes assigned to a product into clean structured JSON.
     */
    public function formatProductAttributes(Product $product, string $locale = 'ar'): array
    {
        if (!$product->relationLoaded('attributeValues')) {
            $product->load(['attributeValues.attribute']);
        }

        $formatted = [];
        $grouped = $product->attributeValues->groupBy('attribute_id');
        foreach ($grouped as $vals) {
            $first = $vals->first();
            $attr = $first?->attribute;
            if (!$attr) continue;
            $attrName = $attr->getTranslation('name', $locale) ?: (is_array($attr->name) ? ($attr->name[$locale] ?? reset($attr->name)) : $attr->name);
            $formatted[] = [
                'id' => $attr->id,
                'name' => $attrName,
                'name_en' => $attr->getTranslation('name', 'en'),
                'name_ar' => $attr->getTranslation('name', 'ar'),
                'slug' => $attr->slug,
                'type' => $attr->type,
                'values' => $vals->map(function ($v) use ($locale) {
                    $vName = $v->getTranslation('value', $locale) ?: (is_array($v->value) ? ($v->value[$locale] ?? reset($v->value)) : $v->value);
                    return [
                        'id' => $v->id,
                        'name' => $vName,
                        'name_en' => $v->getTranslation('value', 'en'),
                        'name_ar' => $v->getTranslation('value', 'ar'),
                        'slug' => $v->slug,
                        'color_code' => $v->color_code,
                    ];
                })->values()->toArray(),
            ];
        }
        return $formatted;
    }


    /**
     * Format a product instance to clean, detailed storefront JSON (excluding bookstore columns).
     */
    public function formatProduct(Product $product, string $locale = 'ar'): array
    {
        $categoryIds = is_array($product->category_id)
            ? $product->category_id
            : (json_decode($product->category_id, true) ?: []);

        $categories = Category::where('is_visible', true)->whereIn('id', (array) $categoryIds)->get();

        $formattedCategories = $categories->map(function ($cat) use ($locale) {
            $catImg = $cat->cat_image;
            $catImgUrl = null;
            if ($catImg) {
                $catImgUrl = str_starts_with($catImg, 'http') ? $catImg : asset('storage/' . ltrim($catImg, '/'));
            }
            $cNameAr = $cat->getTranslation('name', 'ar') ?: (is_array($cat->name) ? ($cat->name['ar'] ?? '') : (is_string($cat->name) ? $cat->name : ''));
            $cNameEn = $cat->getTranslation('name', 'en') ?: (is_array($cat->name) ? ($cat->name['en'] ?? '') : (is_string($cat->name) ? $cat->name : ''));
            if (empty($cNameAr)) $cNameAr = $cNameEn;
            if (empty($cNameEn)) $cNameEn = $cNameAr;
            return [
                'id' => $cat->id,
                'name' => $locale === 'ar' ? ($cNameAr ?: $cNameEn) : ($cNameEn ?: $cNameAr),
                'slug' => ($locale === 'ar' && filled($cat->slug_ar)) ? $cat->slug_ar : $cat->slug,
                'slug_ar' => $cat->slug_ar ?: $cat->slug,
                'slug_en' => $cat->slug,
                'image' => $catImgUrl ?: $catImg,
            ];
        })->values()->toArray();

        // Featured Image
        $imagePath = $product->image;
        $imageUrl = null;
        if ($imagePath) {
            $imageUrl = str_starts_with($imagePath, 'http') ? $imagePath : storage_media_url($imagePath);
        }

        // Gallery Images
        $galleryUrls = [];
        foreach ((array) ($product->gallery ?? []) as $g) {
            if (empty($g)) continue;
            $galleryUrls[] = str_starts_with($g, 'http') ? $g : storage_media_url($g);
        }

        $price = (float) $product->price;
        $mrp = (float) ($product->mrp ?: $product->price);
        $quantity = (int) $product->quantity;

        // Localized fields according to URL prefix locale
        $nameAr = $product->getTranslation('name', 'ar') ?: (is_array($product->name) ? ($product->name['ar'] ?? '') : (is_string($product->name) ? $product->name : ''));
        $nameEn = $product->getTranslation('name', 'en') ?: (is_array($product->name) ? ($product->name['en'] ?? '') : (is_string($product->name) ? $product->name : ''));
        if (empty($nameAr)) $nameAr = $nameEn;
        if (empty($nameEn)) $nameEn = $nameAr;
        $name = $locale === 'ar' ? ($nameAr ?: $nameEn) : ($nameEn ?: $nameAr);

        $slug = ($locale === 'ar' && filled($product->slug_ar)) ? $product->slug_ar : $product->slug;

        $subTitleAr = $product->getTranslation('sub_title', 'ar') ?: (is_array($product->sub_title) ? ($product->sub_title['ar'] ?? '') : (is_string($product->sub_title) ? $product->sub_title : ''));
        $subTitleEn = $product->getTranslation('sub_title', 'en') ?: (is_array($product->sub_title) ? ($product->sub_title['en'] ?? '') : (is_string($product->sub_title) ? $product->sub_title : ''));
        $subTitle = $locale === 'ar' ? ($subTitleAr ?: $subTitleEn) : ($subTitleEn ?: $subTitleAr);

        $descriptionAr = $product->getTranslation('description', 'ar') ?: (is_array($product->description) ? ($product->description['ar'] ?? '') : (is_string($product->description) ? $product->description : ''));
        $descriptionEn = $product->getTranslation('description', 'en') ?: (is_array($product->description) ? ($product->description['en'] ?? '') : (is_string($product->description) ? $product->description : ''));
        $description = $locale === 'ar' ? ($descriptionAr ?: $descriptionEn) : ($descriptionEn ?: $descriptionAr);

        return [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $name,
            'slug' => $slug,
            'slug_ar' => $product->slug_ar ?: $product->slug,
            'slug_en' => $product->slug,
            'sub_title' => $subTitle,
            'description' => $description,
            'price' => $price,
            'mrp' => $mrp,
            'quantity' => $quantity,
            'in_stock' => $quantity > 0,
            'is_visible' => (bool) $product->is_visible,
            'is_subscription_enabled' => (bool) $product->is_subscription_enabled,
            'subscription_discount_percentage' => (float) ($product->subscription_discount_percentage ?? 10.00),
            'subscription_frequencies' => $product->subscription_frequencies ?? ['weekly', 'biweekly', 'monthly'],
            'subscription_durations' => $product->subscription_durations ?? ['3_months', '6_months', '12_months', 'until_cancelled'],
            'subscription_min_duration' => $product->subscription_min_duration ?? '3_months',
            'image' => $imageUrl ?: $imagePath,
            'image_path' => $imagePath,
            'gallery' => $galleryUrls,
            'categories' => $formattedCategories,
            'meta' => [
                'type' => $product->type ?: 'simple',
                'weight' => $product->meta_data['weight'] ?? null,
                'dimensions' => $product->meta_data['dimensions'] ?? null,
            ],
            'attributes' => $this->formatProductAttributes($product, $locale),
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
        $products = Product::visibleToCustomers()->with(['attributeValues.attribute'])->get();

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

        $isNumeric = ctype_digit((string) $slug) || ctype_digit((string) $decodedSlug);

        $product = Product::visibleToCustomers()
            ->where(function ($q) use ($slug, $decodedSlug, $isNumeric) {
                $q->where('slug', $slug)
                  ->orWhere('slug_ar', $slug)
                  ->orWhere('slug', $decodedSlug)
                  ->orWhere('slug_ar', $decodedSlug)
                  ->orWhere('sku', $slug);

                if ($isNumeric) {
                    $q->orWhere('id', (int) $slug);
                }
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

            $category = Category::where(function ($q) use ($slug, $decodedSlug) {
                    $q->where('slug', $slug)
                        ->orWhere('slug_ar', $slug)
                        ->orWhere('slug', $decodedSlug)
                        ->orWhere('slug_ar', $decodedSlug);
                })
                ->first();

            // Alias fallback (e.g. occasions <-> ocassions)
            if (! $category) {
                if ($slug === 'occasions' || $decodedSlug === 'occasions') {
                    $category = Category::where('slug', 'ocassions')->first();
                } elseif ($slug === 'ocassions' || $decodedSlug === 'ocassions') {
                    $category = Category::where('slug', 'occasions')->first();
                }
            }

            if (! $category || ! $category->is_visible) {
                return response()->json([
                    'error' => 'Category not found',
                ], 404);
            }

            // Also check if its parent is invisible
            if ($category->parent_id) {
                $parent = Category::find($category->parent_id);
                if ($parent && ! $parent->is_visible) {
                    return response()->json([
                        'error' => 'Category not found',
                    ], 404);
                }
            }

            $catIds = [$category->id];
            if ($category->child()->exists()) {
                $visibleChildIds = $category->child()->where('is_visible', true)->pluck('id')->toArray();
                $catIds = array_merge($catIds, $visibleChildIds);
            }

            $products = Product::visibleToCustomers()
                ->with(['attributeValues.attribute'])
                ->where(function ($query) use ($catIds) {
                    foreach ($catIds as $cid) {
                        $query->orWhereJsonContains('category_id', (int) $cid)
                              ->orWhereJsonContains('category_id', (string) $cid);
                    }
                })
                ->get();

            // Direct child subcategories of this category (only actual children)
            $directChildren = $category->child()->where('is_visible', true)->get();

            // Aggregate available filter attributes with live product counts
            $attrMap = [];
            foreach ($products as $p) {
                foreach ($p->attributeValues as $av) {
                    $attr = $av->attribute;
                    if (!$attr || !$attr->is_filterable) continue;

                    if (!isset($attrMap[$attr->id])) {
                        $attrName = $attr->getTranslation('name', $locale) ?: (is_array($attr->name) ? ($attr->name[$locale] ?? reset($attr->name)) : $attr->name);
                        $attrMap[$attr->id] = [
                            'id' => $attr->id,
                            'name' => $attrName,
                            'name_en' => $attr->getTranslation('name', 'en'),
                            'name_ar' => $attr->getTranslation('name', 'ar'),
                            'slug' => $attr->slug,
                            'type' => $attr->type,
                            'sort_order' => $attr->sort_order,
                            'values' => [],
                        ];
                    }

                    if (!isset($attrMap[$attr->id]['values'][$av->id])) {
                        $vName = $av->getTranslation('value', $locale) ?: (is_array($av->value) ? ($av->value[$locale] ?? reset($av->value)) : $av->value);
                        $attrMap[$attr->id]['values'][$av->id] = [
                            'id' => $av->id,
                            'name' => $vName,
                            'name_en' => $av->getTranslation('value', 'en'),
                            'name_ar' => $av->getTranslation('value', 'ar'),
                            'slug' => $av->slug,
                            'color_code' => $av->color_code,
                            'sort_order' => $av->sort_order,
                            'count' => 0,
                        ];
                    }

                    $attrMap[$attr->id]['values'][$av->id]['count']++;
                }
            }

            $availableFilterAttributes = collect($attrMap)->sortBy('sort_order')->map(function ($a) {
                $a['values'] = collect($a['values'])->sortBy('sort_order')->values()->toArray();
                return $a;
            })->values()->toArray();


            // Parent category if this is a subcategory (child category)
            $parentCategory = null;
            if ($category->parent_id) {
                $parent = Category::find($category->parent_id);
                if ($parent && $parent->is_visible) {
                    $pImg = $parent->cat_image;
                    $pImgUrl = null;
                    if ($pImg) {
                        $pImgUrl = str_starts_with($pImg, 'http') ? $pImg : asset('storage/' . ltrim($pImg, '/'));
                    }
                    $parentCategory = [
                        'id' => $parent->id,
                        'name' => $parent->getTranslation('name', $locale) ?: (is_array($parent->name) ? ($parent->name[$locale] ?? reset($parent->name)) : $parent->name),
                        'name_en' => $parent->getTranslation('name', 'en') ?: (is_array($parent->name) ? ($parent->name['en'] ?? '') : $parent->name),
                        'name_ar' => $parent->getTranslation('name', 'ar') ?: (is_array($parent->name) ? ($parent->name['ar'] ?? '') : $parent->name),
                        'slug' => ($locale === 'ar' && filled($parent->slug_ar)) ? $parent->slug_ar : $parent->slug,
                        'slug_en' => $parent->slug,
                        'slug_ar' => $parent->slug_ar ?: $parent->slug,
                        'image' => $pImgUrl ?: $pImg,
                    ];
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
                    'parent_id' => $category->parent_id,
                    'is_child' => (bool) $category->parent_id,
                    'parent' => $parentCategory,
                    'name' => $category->getTranslation('name', $locale) ?: (is_array($category->name) ? ($category->name[$locale] ?? reset($category->name)) : $category->name),
                    'name_en' => $category->getTranslation('name', 'en') ?: (is_array($category->name) ? ($category->name['en'] ?? '') : $category->name),
                    'name_ar' => $category->getTranslation('name', 'ar') ?: (is_array($category->name) ? ($category->name['ar'] ?? '') : $category->name),
                    'slug' => ($locale === 'ar' && filled($category->slug_ar)) ? $category->slug_ar : $category->slug,
                    'slug_en' => $category->slug,
                    'slug_ar' => $category->slug_ar ?: $category->slug,
                    'description' => $category->getTranslation('description', $locale) ?: (is_array($category->description) ? ($category->description[$locale] ?? '') : $category->description),
                    'image' => $catImgUrl ?: $catImg,
                    'child' => $formattedSubcategories,
                    'subcategories' => $formattedSubcategories,
                ],
                'parent' => $parentCategory,
                'sub_categories' => $formattedSubcategories,
                'subcategories' => $formattedSubcategories,
                'filters' => [
                    'attributes' => $availableFilterAttributes,
                ],
                'attributes' => $availableFilterAttributes,
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
