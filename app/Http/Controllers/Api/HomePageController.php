<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HomePage;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomePageController extends Controller
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

    private function parseIds($raw): array
    {
        if (empty($raw)) {
            return [];
        }
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            } else {
                $raw = explode(',', $raw);
            }
        }
        if (!is_array($raw)) {
            return [];
        }
        return array_values(array_filter(array_map('intval', $raw), fn ($id) => $id > 0));
    }

    private function getTranslated($value, string $locale): ?string
    {
        if (empty($value)) return null;
        if (is_array($value)) {
            return $value[$locale] ?? (reset($value) ?: null);
        }
        return (string) $value;
    }

    private function formatCategory(Category $cat, string $locale): array
    {
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
    }

    private function formatSliders($sliders): array
    {
        if (empty($sliders) || !is_array($sliders)) {
            return [];
        }

        return array_values(array_map(function ($item) {
            if (is_array($item)) {
                foreach (['slider_image', 'mslider_image', 'image'] as $k) {
                    if (!empty($item[$k])) {
                        $item[$k] = str_starts_with($item[$k], 'http')
                            ? $item[$k]
                            : asset('storage/' . ltrim($item[$k], '/'));
                    }
                }
            }
            return $item;
        }, $sliders));
    }

    public function index(Request $request): JsonResponse
    {
        $locale = $this->getLocale($request);
        $homePage = HomePage::first();

        if (!$homePage) {
            return response()->json(['message' => 'Home page not configured'], 404);
        }

        // 1. Popular Categories
        $popularIds = $this->parseIds($homePage->popular_category);
        $popularCategories = !empty($popularIds)
            ? Category::whereIn('id', $popularIds)
                ->orderByRaw('FIELD(id, ' . implode(',', $popularIds) . ')')
                ->get()
                ->map(fn ($cat) => $this->formatCategory($cat, $locale))
                ->values()
            : [];

        // 2. Best Seller Products (clean card schema)
        $bestSellerIds = $this->parseIds($homePage->best_sellers);
        $productController = app(ProductController::class);
        $bestSellers = !empty($bestSellerIds)
            ? Product::visibleToCustomers()
                ->whereIn('id', $bestSellerIds)
                ->orderByRaw('FIELD(id, ' . implode(',', $bestSellerIds) . ')')
                ->get()
                ->map(fn ($p) => $productController->formatCardProduct($p, $locale))
                ->values()
            : [];

        // 3. Featured Categories / Mock Test Categories
        $mockIds = $this->parseIds($homePage->mock_test_category);
        $mockCategories = !empty($mockIds)
            ? Category::whereIn('id', $mockIds)
                ->orderByRaw('FIELD(id, ' . implode(',', $mockIds) . ')')
                ->get()
                ->map(fn ($cat) => $this->formatCategory($cat, $locale))
                ->values()
            : [];

        // 4. Hobby / Occasion Categories
        $hobbyIds = $this->parseIds($homePage->hobby_category);
        $hobbyCategories = !empty($hobbyIds)
            ? Category::whereIn('id', $hobbyIds)
                ->orderByRaw('FIELD(id, ' . implode(',', $hobbyIds) . ')')
                ->get()
                ->map(fn ($cat) => $this->formatCategory($cat, $locale))
                ->values()
            : [];

        // Banner images with full URLs
        $bannerImages = [];
        if (!empty($homePage->banner_images) && is_array($homePage->banner_images)) {
            foreach ($homePage->banner_images as $img) {
                if (empty($img)) continue;
                $bannerImages[] = str_starts_with($img, 'http') ? $img : asset('storage/' . ltrim($img, '/'));
            }
        }

        $bestSellersTitle = $homePage->getTranslation('best_sellers_title', $locale)
            ?: ($homePage->best_sellers_title ?: ($locale === 'ar' ? 'الأكثر مبيعاً' : 'Best Sellers'));
        if (is_array($bestSellersTitle)) {
            $bestSellersTitle = $bestSellersTitle[$locale] ?? reset($bestSellersTitle);
        }

        $popularTitle = $homePage->getTranslation('popular_title', $locale)
            ?: ($homePage->popular_title ?: ($locale === 'ar' ? 'التصنيفات الشائعة' : 'Popular Categories'));
        if (is_array($popularTitle)) {
            $popularTitle = $popularTitle[$locale] ?? reset($popularTitle);
        }

        $popularSubtitle = $homePage->getTranslation('popular_subtitle', $locale) ?: $homePage->popular_subtitle;
        if (is_array($popularSubtitle)) {
            $popularSubtitle = $popularSubtitle[$locale] ?? '';
        }

        return response()->json([
            'slider_section' => $this->formatSliders($homePage->slider_section),
            'mobile_slider_section' => $this->formatSliders($homePage->mslider_section),
            'popular_section' => [
                'popular_title' => $popularTitle,
                'popular_subtitle' => $popularSubtitle ?: '',
                'popular_category' => $popularCategories,
            ],
            'best_sellers_section' => [
                'title' => $bestSellersTitle,
                'subtitle' => $this->getTranslated($homePage->getTranslation('best_sellers_subtitle', $locale) ?: $homePage->best_sellers_subtitle, $locale) ?: '',
                'products' => $bestSellers,
            ],
            'featured_categories' => $mockCategories,
            'occasions' => $hobbyCategories,
            'banner' => [
                'banner_button_url' => $homePage->banner_button_url,
                'images' => $bannerImages,
            ],
            'category_section' => [
                'cat_sec_title' => $this->getTranslated($homePage->getTranslation('cat_sec_title', $locale) ?: $homePage->cat_sec_title, $locale),
                'cat_sec_description' => $this->getTranslated($homePage->getTranslation('cat_sec_description', $locale) ?: $homePage->cat_sec_description, $locale),
                'category_sections' => $homePage->category_sections,
            ],
            'seo' => [
                'meta_title' => $this->getTranslated($homePage->getTranslation('meta_tag_title', $locale) ?: $homePage->meta_tag_title, $locale),
                'meta_description' => $this->getTranslated($homePage->getTranslation('meta_tag_description', $locale) ?: $homePage->meta_tag_description, $locale),
                'meta_keywords' => $this->getTranslated($homePage->getTranslation('meta_tag_keywords', $locale) ?: $homePage->meta_tag_keywords, $locale),
            ],
        ]);
    }
}