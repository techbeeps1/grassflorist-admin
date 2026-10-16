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

        // Build set of all invisible categories (by id and slug)
        $invisibleCategories = Category::where('is_visible', false)->get(['id', 'slug', 'slug_ar']);
        $invisibleSlugs = [];
        foreach ($invisibleCategories as $invCat) {
            if ($invCat->slug) $invisibleSlugs[strtolower($invCat->slug)] = true;
            if ($invCat->slug_ar) $invisibleSlugs[strtolower($invCat->slug_ar)] = true;
            $invisibleSlugs['category/' . strtolower($invCat->slug)] = true;
            if ($invCat->slug_ar) $invisibleSlugs['category/' . strtolower($invCat->slug_ar)] = true;
            $invisibleSlugs[(string)$invCat->id] = true;
        }

        $isUrlInvisible = function ($url, $id = null) use ($invisibleSlugs) {
            if ($id && isset($invisibleSlugs[strtolower((string)$id)])) {
                return true;
            }
            if (!empty($url)) {
                $cleaned = strtolower(trim($url, '/'));
                if (str_starts_with($cleaned, 'en/')) {
                    $cleaned = substr($cleaned, 3);
                }
                if (isset($invisibleSlugs[$cleaned])) {
                    return true;
                }
                if (str_starts_with($cleaned, 'category/')) {
                    $slugPart = substr($cleaned, 9);
                    if (isset($invisibleSlugs[$slugPart])) {
                        return true;
                    }
                }
            }
            return false;
        };

        // Filter out hidden categories from header menu
        $filteredHeaderItems = [];
        foreach ($headerItems as $item) {
            $urlEn = $item['url_en'] ?? ($item['href']['en'] ?? '');
            $urlAr = $item['url_ar'] ?? ($item['href']['ar'] ?? '');
            $itemId = $item['id'] ?? '';

            if ($isUrlInvisible($urlEn, $itemId) || $isUrlInvisible($urlAr, $itemId)) {
                continue;
            }

            // Filter and canonicalize subcategories
            if (!empty($item['subcategories']) && is_array($item['subcategories'])) {
                // Determine if parent menu item is a category
                $parentSlugEn = null;
                $parentSlugAr = null;
                if (!empty($item['url_en']) && preg_match('#^/en/category/([^/]+)$#', $item['url_en'], $mEn)) {
                    $parentSlugEn = $mEn[1];
                }
                if (!empty($item['url_ar']) && preg_match('#^/category/([^/]+)$#', $item['url_ar'], $mAr)) {
                    $parentSlugAr = $mAr[1];
                }

                $filteredSubs = [];
                foreach ($item['subcategories'] as $sub) {
                    $subUrlEn = $sub['url_en'] ?? ($sub['href']['en'] ?? '');
                    $subUrlAr = $sub['url_ar'] ?? ($sub['href']['ar'] ?? '');
                    $subId = $sub['id'] ?? '';
                    if ($isUrlInvisible($subUrlEn, $subId) || $isUrlInvisible($subUrlAr, $subId)) {
                        continue;
                    }

                    // Canonicalize child category URL under parent category if applicable
                    if ($parentSlugEn && !empty($subUrlEn) && preg_match('#^/en/category/([^/]+)$#', $subUrlEn, $subMatchEn)) {
                        $childSlug = $subMatchEn[1];
                        if ($childSlug !== $parentSlugEn) {
                            $sub['url_en'] = "/en/category/{$parentSlugEn}/{$childSlug}";
                            if (isset($sub['href']['en'])) $sub['href']['en'] = $sub['url_en'];
                        }
                    }
                    if ($parentSlugAr && !empty($subUrlAr) && preg_match('#^/category/([^/]+)$#', $subUrlAr, $subMatchAr)) {
                        $childSlug = $subMatchAr[1];
                        if ($childSlug !== $parentSlugAr) {
                            $sub['url_ar'] = "/category/{$parentSlugAr}/{$childSlug}";
                            if (isset($sub['href']['ar'])) $sub['href']['ar'] = $sub['url_ar'];
                        }
                    }

                    // Filter children
                    if (!empty($sub['children']) && is_array($sub['children'])) {
                        $filteredChildren = [];
                        foreach ($sub['children'] as $child) {
                            $childUrlEn = $child['url_en'] ?? ($child['href']['en'] ?? '');
                            $childUrlAr = $child['url_ar'] ?? ($child['href']['ar'] ?? '');
                            $childId = $child['id'] ?? '';
                            if ($isUrlInvisible($childUrlEn, $childId) || $isUrlInvisible($childUrlAr, $childId)) {
                                continue;
                            }
                            $filteredChildren[] = $child;
                        }
                        $sub['children'] = $filteredChildren;
                    }

                    $filteredSubs[] = $sub;
                }
                $item['subcategories'] = array_values($filteredSubs);
                if (empty($item['subcategories']) && ($item['dropdown_type'] ?? '') !== 'mega') {
                    $item['has_dropdown'] = false;
                }
            }

            $filteredHeaderItems[] = $item;
        }

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
        }, $filteredHeaderItems);

        // Process Footer Column 1 Categories
        $col1Source = $footerItems['column_1']['source'] ?? 'categories';
        $selectedCatIds = [];
        if (!empty($footerItems['column_1']['categories']) && is_array($footerItems['column_1']['categories'])) {
            $selectedCatIds = collect($footerItems['column_1']['categories'])
                ->pluck('category_id')
                ->filter()
                ->map(fn($id) => (int) $id)
                ->values()
                ->toArray();
        }
        if (empty($selectedCatIds) && !empty($footerItems['column_1']['category_ids'])) {
            $selectedCatIds = array_map('intval', (array) $footerItems['column_1']['category_ids']);
        }

        if ($col1Source === 'selected' || (!empty($selectedCatIds) && $col1Source !== 'categories')) {
            if (!empty($selectedCatIds)) {
                $categoriesById = Category::whereIn('id', $selectedCatIds)
                    ->where('is_visible', true)
                    ->get()
                    ->keyBy('id');

                $col1Categories = collect($selectedCatIds)
                    ->map(fn ($id) => $categoriesById[(int)$id] ?? null)
                    ->filter()
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
                    })
                    ->values();

                $footerItems['column_1']['categories'] = $col1Categories;
            } else {
                $footerItems['column_1']['categories'] = [];
            }
        } else {
            // Auto-populate from top visible categories
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
        $navigation = $this->getNavigation(request());
        $navData = $navigation->getData(true);

        if ($menuName === 'header') {
            return response()->json([
                'success' => true,
                'items' => $navData['header'] ?? [],
            ]);
        }

        if ($menuName === 'footer') {
            return response()->json([
                'success' => true,
                'items' => $navData['footer'] ?? [],
                'settings' => $navData['footer_settings'] ?? [],
            ]);
        }

        return $navigation;
    }
}