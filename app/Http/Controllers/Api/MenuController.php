<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    /**
     * Get complete dynamic navigation (header & footer) with bilingual support
     * and dynamic random product showcase for mega menus.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getNavigation(Request $request)
    {
        $headerMenu = Menu::where('slug', 'header')->first();
        $footerMenu = Menu::where('slug', 'footer')->first();

        $headerItems = $headerMenu ? ($headerMenu->items ?? []) : [];
        $footerItems = $footerMenu ? ($footerMenu->items ?? []) : [];
        $footerSettings = $footerMenu ? ($footerMenu->settings ?? []) : [];

        // Enrich header mega menu items with dynamic category products if requested
        $processedHeaderItems = array_map(function ($item) {
            $dropdownType = $item['dropdown_type'] ?? 'simple';
            $showcaseType = $item['showcase_type'] ?? 'none';

            if (($item['has_dropdown'] ?? false) && $dropdownType === 'mega') {
                if ($showcaseType === 'category_random_product' && !empty($item['featured_category_id'])) {
                    $catId = (int)$item['featured_category_id'];
                    $category = Category::find($catId);

                    // Fetch random active product from selected category
                    $product = Product::where('is_visible', true)
                        ->where(function ($q) use ($catId) {
                            $q->whereJsonContains('category_id', $catId)
                              ->orWhereJsonContains('category_id', (string)$catId);
                        })
                        ->inRandomOrder()
                        ->first();

                    if ($product) {
                        $imageUrl = $product->image;
                        if ($imageUrl && !str_starts_with($imageUrl, 'http')) {
                            $imageUrl = url('storage/' . ltrim($imageUrl, '/'));
                        }

                        $item['featured_product'] = [
                            'id' => $product->id,
                            'name' => [
                                'en' => is_array($product->name) ? ($product->name['en'] ?? '') : $product->name,
                                'ar' => is_array($product->name) ? ($product->name['ar'] ?? $product->name['en'] ?? '') : $product->name,
                            ],
                            'price' => (float)$product->price,
                            'image' => $imageUrl ?: 'https://images.unsplash.com/photo-1526047932273-341f2a7631f9?auto=format&fit=crop&w=800&q=80',
                            'slug' => [
                                'en' => $product->slug,
                                'ar' => $product->slug_ar ?: $product->slug,
                            ],
                            'category_name' => [
                                'en' => $category && is_array($category->name) ? ($category->name['en'] ?? '') : '',
                                'ar' => $category && is_array($category->name) ? ($category->name['ar'] ?? '') : '',
                            ],
                            'badge' => [
                                'en' => $item['badge_text_en'] ?? 'SAME-DAY DELIVERY',
                                'ar' => $item['badge_text_ar'] ?? 'توصيل في نفس اليوم',
                            ],
                            'cta' => [
                                'en' => $item['cta_text_en'] ?? 'Explore Curated Flowers',
                                'ar' => $item['cta_text_ar'] ?? 'تصفح التشكيلة الكاملة',
                            ],
                        ];
                    }
                } elseif ($showcaseType === 'custom_card') {
                    $item['featured_card'] = [
                        'title' => [
                            'en' => $item['card_title_en'] ?? '',
                            'ar' => $item['card_title_ar'] ?? '',
                        ],
                        'desc' => [
                            'en' => $item['card_desc_en'] ?? '',
                            'ar' => $item['card_desc_ar'] ?? '',
                        ],
                        'image' => $item['card_image'] ?? 'https://images.unsplash.com/photo-1526047932273-341f2a7631f9?auto=format&fit=crop&w=800&q=80',
                        'tag' => [
                            'en' => $item['card_tag_en'] ?? '',
                            'ar' => $item['card_tag_ar'] ?? '',
                        ],
                        'link' => [
                            'en' => $item['card_link_en'] ?? '',
                            'ar' => $item['card_link_ar'] ?? '',
                        ],
                    ];
                }
            }

            return $item;
        }, $headerItems);

        // Process Footer Column 1 Categories if set to auto 'categories'
        if (isset($footerItems['column_1']['source']) && $footerItems['column_1']['source'] === 'categories') {
            $topCategories = Category::where('is_visible', true)
                ->whereNull('parent_id')
                ->take(8)
                ->get()
                ->map(function ($cat) {
                    $en = is_array($cat->name) ? ($cat->name['en'] ?? '') : $cat->name;
                    $ar = is_array($cat->name) ? ($cat->name['ar'] ?? '') : $cat->name;
                    $urlEn = '/en/category/' . $cat->slug;
                    $urlAr = '/category/' . ($cat->slug_ar ?: $cat->slug);
                    return [
                        'id' => (string)$cat->id,
                        'name_en' => $en,
                        'name_ar' => $ar,
                        'url_en' => $urlEn,
                        'url_ar' => $urlAr,
                        'name' => ['en' => $en, 'ar' => $ar],
                        'href' => ['en' => $urlEn, 'ar' => $urlAr],
                    ];
                });
            $footerItems['column_1']['categories'] = $topCategories;
        }

        // Format column 1 title
        if (isset($footerItems['column_1'])) {
            $footerItems['column_1']['title'] = [
                'en' => $footerItems['column_1']['title_en'] ?? 'CATEGORIES',
                'ar' => $footerItems['column_1']['title_ar'] ?? 'التصنيفات',
            ];
        }

        // Format column 2 links & title
        if (isset($footerItems['column_2'])) {
            $footerItems['column_2']['title'] = [
                'en' => $footerItems['column_2']['title_en'] ?? 'QUICK LINKS',
                'ar' => $footerItems['column_2']['title_ar'] ?? 'روابط سريعة',
            ];
            if (isset($footerItems['column_2']['links']) && is_array($footerItems['column_2']['links'])) {
                $footerItems['column_2']['links'] = array_map(function ($link) {
                    $en = $link['name_en'] ?? '';
                    $ar = $link['name_ar'] ?? '';
                    $urlEn = $link['url_en'] ?? '';
                    $urlAr = $link['url_ar'] ?? '';
                    $link['name'] = ['en' => $en, 'ar' => $ar];
                    $link['href'] = ['en' => $urlEn, 'ar' => $urlAr];
                    return $link;
                }, $footerItems['column_2']['links']);
            }
        }

        // Format column 3 links & title
        if (isset($footerItems['column_3'])) {
            $footerItems['column_3']['title'] = [
                'en' => $footerItems['column_3']['title_en'] ?? 'POLICIES',
                'ar' => $footerItems['column_3']['title_ar'] ?? 'السياسات',
            ];
            if (isset($footerItems['column_3']['links']) && is_array($footerItems['column_3']['links'])) {
                $footerItems['column_3']['links'] = array_map(function ($link) {
                    $en = $link['name_en'] ?? '';
                    $ar = $link['name_ar'] ?? '';
                    $urlEn = $link['url_en'] ?? '';
                    $urlAr = $link['url_ar'] ?? '';
                    $link['name'] = ['en' => $en, 'ar' => $ar];
                    $link['href'] = ['en' => $urlEn, 'ar' => $urlAr];
                    return $link;
                }, $footerItems['column_3']['links']);
            }
        }

        // Format settings
        $footerSettings['delivery_city'] = [
            'en' => $footerSettings['delivery_city_en'] ?? 'Jeddah',
            'ar' => $footerSettings['delivery_city_ar'] ?? 'جدة',
        ];
        $footerSettings['delivery_badge'] = [
            'en' => $footerSettings['delivery_badge_en'] ?? 'Delivery to',
            'ar' => $footerSettings['delivery_badge_ar'] ?? 'التوصيل إلى',
        ];
        $footerItems['settings'] = $footerSettings;

        return response()->json([
            'success' => true,
            'header' => $processedHeaderItems,
            'footer' => $footerItems,
            'footer_settings' => $footerSettings,
        ]);
    }

    /**
     * Get menu items by menu name (compatible with legacy endpoint)
     *
     * @param string $menuName
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMenuItems($menuName)
    {
        if ($menuName === 'header') {
            $menu = Menu::where('slug', 'header')->first();
            return response()->json([
                'success' => true,
                'items' => $menu ? ($menu->items ?? []) : [],
            ]);
        }

        if ($menuName === 'footer') {
            $menu = Menu::where('slug', 'footer')->first();
            return response()->json([
                'success' => true,
                'items' => $menu ? ($menu->items ?? []) : [],
                'settings' => $menu ? ($menu->settings ?? []) : [],
            ]);
        }

        return $this->getNavigation(request());
    }
}