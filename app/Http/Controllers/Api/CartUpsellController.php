<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\GlobalSetting;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartUpsellController extends Controller
{
    /**
     * Determine request locale.
     */
    protected function getLocale(Request $request): string
    {
        if ($request->routeIs('*en*') || $request->segment(1) === 'en' || $request->segment(2) === 'en') {
            return 'en';
        }
        if ($request->routeIs('*ar*') || $request->segment(1) === 'ar' || $request->segment(2) === 'ar') {
            return 'ar';
        }
        if ($request->query('locale') === 'en' || $request->query('lang') === 'en' || $request->header('X-Locale') === 'en') {
            return 'en';
        }
        return 'ar';
    }

    /**
     * Return configured "Make it perfect" categories and their in-stock products.
     */
    public function index(Request $request): JsonResponse
    {
        $locale = $this->getLocale($request);
        $setting = GlobalSetting::current();

        $isEnabled = (bool) ($setting->cart_upsell_enabled ?? true);
        if (!$isEnabled) {
            return response()->json([
                'success' => true,
                'enabled' => false,
                'title' => '',
                'categories' => [],
            ]);
        }

        $titleEn = $setting->cart_upsell_title_en ?: 'Make it perfect';
        $titleAr = $setting->cart_upsell_title_ar ?: 'اجعلها مثالية';
        $title = $locale === 'ar' ? $titleAr : $titleEn;

        $categoryIds = (array) ($setting->cart_upsell_category_ids ?? [30, 29, 39, 43, 46]);
        $limitPerCategory = max(1, min(24, (int) ($setting->cart_upsell_limit_per_category ?? 10)));

        // Retrieve categories preserving the admin's selected ordering
        $categories = Category::whereIn('id', $categoryIds)->get()->keyBy('id');

        $resultCategories = [];
        $recommendedProducts = collect();

        foreach ($categoryIds as $catId) {
            $catIdInt = (int) $catId;
            if (!isset($categories[$catIdInt])) {
                continue;
            }

            $cat = $categories[$catIdInt];

            // Resolve localized category name
            $catNameEn = is_array($cat->name) ? ($cat->name['en'] ?? '') : (is_string($cat->name) ? $cat->name : '');
            $catNameAr = is_array($cat->name) ? ($cat->name['ar'] ?? '') : '';
            if (empty($catNameEn)) $catNameEn = $catNameAr;
            if (empty($catNameAr)) $catNameAr = $catNameEn;
            $catName = $locale === 'ar' ? ($catNameAr ?: $catNameEn) : ($catNameEn ?: $catNameAr);

            // Fetch visible products with positive stock
            $products = Product::visibleToCustomers()
                ->where(function ($query) use ($catIdInt) {
                    $query->orWhereJsonContains('category_id', $catIdInt)
                          ->orWhereJsonContains('category_id', (string) $catIdInt)
                          ->orWhere('category_id', $catIdInt);
                })
                ->where('quantity', '>', 0)
                ->latest('id')
                ->limit($limitPerCategory)
                ->get();

            if ($products->isEmpty()) {
                // If strictly positive stock is empty, fetch visible active products as fallback
                $products = Product::visibleToCustomers()
                    ->where(function ($query) use ($catIdInt) {
                        $query->orWhereJsonContains('category_id', $catIdInt)
                              ->orWhereJsonContains('category_id', (string) $catIdInt)
                              ->orWhere('category_id', $catIdInt);
                    })
                    ->latest('id')
                    ->limit($limitPerCategory)
                    ->get();
            }

            $formattedProducts = $products->map(function ($p) use ($cat, $catNameEn, $catNameAr) {
                return $this->formatUpsellProduct($p, $cat, $catNameEn, $catNameAr);
            })->values()->toArray();

            if (!empty($formattedProducts)) {
                $resultCategories[] = [
                    'id' => (string) $cat->id,
                    'name' => $catName,
                    'name_en' => $catNameEn,
                    'name_ar' => $catNameAr,
                    'slug' => ($locale === 'ar' && filled($cat->slug_ar)) ? $cat->slug_ar : $cat->slug,
                    'products' => $formattedProducts,
                ];

                // Gather top items for "Recommended" tab
                foreach (array_slice($formattedProducts, 0, 3) as $recP) {
                    $recommendedProducts->push($recP);
                }
            }
        }

        // Deduplicate recommended products
        $uniqueRecommended = $recommendedProducts->unique('id')->values()->take($limitPerCategory)->toArray();

        // Prepend "Recommended" tab if products exist
        if (!empty($uniqueRecommended)) {
            array_unshift($resultCategories, [
                'id' => 'recommended',
                'name' => $locale === 'ar' ? 'الموصى بها' : 'Recommended',
                'name_en' => 'Recommended',
                'name_ar' => 'الموصى بها',
                'slug' => 'recommended',
                'products' => $uniqueRecommended,
            ]);
        }

        return response()->json([
            'success' => true,
            'enabled' => true,
            'title' => $title,
            'title_en' => $titleEn,
            'title_ar' => $titleAr,
            'categories' => $resultCategories,
        ]);
    }

    /**
     * Format a product into standard frontend Product structure.
     */
    protected function formatUpsellProduct(Product $p, Category $cat, string $catNameEn, string $catNameAr): array
    {
        $nameAr = $p->getTranslation('name', 'ar') ?: (is_array($p->name) ? ($p->name['ar'] ?? '') : (is_string($p->name) ? $p->name : ''));
        $nameEn = $p->getTranslation('name', 'en') ?: (is_array($p->name) ? ($p->name['en'] ?? '') : (is_string($p->name) ? $p->name : ''));
        if (empty($nameAr)) $nameAr = $nameEn;
        if (empty($nameEn)) $nameEn = $nameAr;

        $slugAr = $p->slug_ar ?: $p->slug;
        $slugEn = $p->slug;

        $imagePath = $p->image;
        $imageUrl = $imagePath;
        if ($imagePath && !str_starts_with($imagePath, 'http')) {
            $imageUrl = storage_media_url($imagePath);
        }

        $galleryUrls = [];
        foreach ((array) ($p->gallery ?? []) as $g) {
            if (empty($g)) continue;
            $galleryUrls[] = str_starts_with($g, 'http') ? $g : storage_media_url($g);
        }

        $price = (float) $p->price;
        $mrp = (float) ($p->mrp ?: $p->price);
        $stock = (int) ($p->quantity ?? 10);

        return [
            'id' => (string) $p->id,
            'name' => [
                'en' => $nameEn,
                'ar' => $nameAr,
            ],
            'slug' => [
                'en' => $slugEn,
                'ar' => $slugAr,
            ],
            'price' => $price,
            'originalPrice' => $mrp > $price ? $mrp : null,
            'currency' => 'SAR',
            'thumbnail' => $imageUrl ?: '/placeholder.jpg',
            'images' => !empty($galleryUrls) ? $galleryUrls : [$imageUrl ?: '/placeholder.jpg'],
            'category' => [
                'en' => $catNameEn,
                'ar' => $catNameAr,
            ],
            'categorySlug' => $cat->slug,
            'stock' => $stock,
            'sku' => (string) ($p->sku ?: $p->id),
            'availability' => $stock > 0 ? 'in_stock' : 'out_of_stock',
            'is_subscription_enabled' => (bool) $p->is_subscription_enabled,
        ];
    }
}
