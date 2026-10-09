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

    private function formatSliders($sliders, string $locale = 'ar'): array
    {
        if (empty($sliders) || !is_array($sliders)) {
            return [];
        }

        return array_values(array_map(function ($item) use ($locale) {
            if (!is_array($item)) {
                return $item;
            }

            // Convert relative storage paths to absolute URLs
            foreach ([
                'slider_image',
                'slider_image_en',
                'slider_image_ar',
                'mslider_image',
                'mslider_image_en',
                'mslider_image_ar',
                'image',
            ] as $k) {
                if (!empty($item[$k])) {
                    $item[$k] = str_starts_with($item[$k], 'http')
                        ? $item[$k]
                        : asset('storage/' . ltrim($item[$k], '/'));
                }
            }

            // Determine active desktop image for the given locale
            $activeDesktopImg = ($locale === 'ar')
                ? ($item['slider_image_ar'] ?? ($item['slider_image_en'] ?? ($item['slider_image'] ?? null)))
                : ($item['slider_image_en'] ?? ($item['slider_image_ar'] ?? ($item['slider_image'] ?? null)));

            // Determine active mobile image for the given locale
            $activeMobileImg = ($locale === 'ar')
                ? ($item['mslider_image_ar'] ?? ($item['mslider_image_en'] ?? ($item['mslider_image'] ?? null)))
                : ($item['mslider_image_en'] ?? ($item['mslider_image_ar'] ?? ($item['mslider_image'] ?? null)));

            $item['image'] = $activeDesktopImg ?: ($activeMobileImg ?: ($item['image'] ?? null));
            $item['desktop_image'] = $activeDesktopImg ?: $item['image'];
            $item['mobile_image'] = $activeMobileImg ?: $item['image'];
            $item['link'] = $item['slider_url'] ?? ($item['mslider_url'] ?? null);

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

        $productController = app(ProductController::class);

        // 1. Popular Categories
        $popularIds = $this->parseIds($homePage->popular_category);
        $popularCategories = !empty($popularIds)
            ? Category::whereIn('id', $popularIds)
                ->orderByRaw('FIELD(id, ' . implode(',', $popularIds) . ')')
                ->get()
                ->map(fn ($cat) => $this->formatCategory($cat, $locale))
                ->values()
            : [];

        // 2. Best Seller Products
        $bestSellerIds = $this->parseIds($homePage->best_sellers);
        $bestSellers = !empty($bestSellerIds)
            ? Product::visibleToCustomers()
                ->whereIn('id', $bestSellerIds)
                ->orderByRaw('FIELD(id, ' . implode(',', $bestSellerIds) . ')')
                ->get()
                ->map(fn ($p) => $productController->formatCardProduct($p, $locale))
                ->values()
            : [];

        // 3. Category Product Sections (Carousels) Repeater
        $rawCategorySections = $homePage->category_sections;
        if (is_string($rawCategorySections)) {
            $rawCategorySections = json_decode($rawCategorySections, true) ?: [];
        }
        $formattedCategorySections = [];
        if (!empty($rawCategorySections) && is_array($rawCategorySections)) {
            foreach ($rawCategorySections as $sec) {
                if (empty($sec) || !is_array($sec)) continue;
                $catId = (int) ($sec['category_id'] ?? 0);
                if ($catId <= 0) continue;

                $category = Category::find($catId);
                if (!$category) continue;

                $catIds = [$catId];
                if ($category->child()->exists()) {
                    $catIds = array_merge($catIds, $category->child()->pluck('id')->toArray());
                }

                $limit = (int) ($sec['limit'] ?? 8);
                $limit = min(max($limit, 4), 24);

                $prods = Product::visibleToCustomers()
                    ->where(function ($query) use ($catIds) {
                        foreach ($catIds as $cid) {
                            $query->orWhereJsonContains('category_id', (int) $cid)
                                  ->orWhereJsonContains('category_id', (string) $cid);
                        }
                    })
                    ->orderBy('id', 'desc')
                    ->limit($limit)
                    ->get();

                $enTitle = is_array($sec['title'] ?? null) ? ($sec['title']['en'] ?? '') : ($sec['title'] ?? '');
                $arTitle = is_array($sec['title'] ?? null) ? ($sec['title']['ar'] ?? '') : ($sec['title'] ?? '');
                if (empty($enTitle)) $enTitle = $category->getTranslation('name', 'en') ?: $category->slug;
                if (empty($arTitle)) $arTitle = $category->getTranslation('name', 'ar') ?: $category->slug;

                $enSub = is_array($sec['subtitle'] ?? null) ? ($sec['subtitle']['en'] ?? '') : ($sec['subtitle'] ?? '');
                $arSub = is_array($sec['subtitle'] ?? null) ? ($sec['subtitle']['ar'] ?? '') : ($sec['subtitle'] ?? '');

                $slugEn = $category->slug ?: $category->slug_ar;
                $slugAr = $category->slug_ar ?: $category->slug;

                $formattedCategorySections[] = [
                    'id' => (string) ($category->slug ?: "cat-{$catId}"),
                    'category_id' => $catId,
                    'title' => [
                        'en' => $enTitle,
                        'ar' => $arTitle,
                    ],
                    'subtitle' => [
                        'en' => $enSub,
                        'ar' => $arSub,
                    ],
                    'categorySlug' => $locale === 'ar' ? $slugAr : $slugEn,
                    'viewAllUrl' => [
                        'ar' => '/category/' . $slugAr,
                        'en' => '/en/category/' . $slugEn,
                    ],
                    'products' => $prods->map(fn ($p) => $productController->formatCardProduct($p, $locale))->values(),
                ];
            }
        }

        // 4. Floral Inspiration & Care Guides (Blog Section)
        $blogPosts = \App\Models\CmsPost::where('is_active', true)
            ->orderBy('published_at', 'desc')
            ->limit(3)
            ->get();
        $cmsPostController = app(\App\Http\Controllers\Api\CmsPostController::class);
        $formattedBlogPosts = $blogPosts->map(fn ($p) => $cmsPostController->formatPost($p, $locale))->values();

        $blogTitle = $this->getTranslated($homePage->getTranslation('blog_title', $locale) ?: $homePage->blog_title, $locale)
            ?: ($locale === 'ar' ? 'إلهام وأسرار العناية بالزهور' : 'Floral Inspiration & Care Guides');
        $blogSubtitle = $this->getTranslated($homePage->getTranslation('blog_subtitle', $locale) ?: $homePage->blog_subtitle, $locale)
            ?: ($locale === 'ar' ? 'مقالات حصرية من خبراء تنسيق الزهور لإرشادك في اختيار الهدية المثالية والحفاظ على نضارتها.' : 'Curated articles from master florists to guide your gifting choices and prolong bloom life.');

        // 5. Stories from Our Clients (Testimonials Section)
        $testimonials = \App\Models\Testimonial::active()->ordered()->limit(10)->get();
        $formattedTestimonials = $testimonials->map(function ($item) use ($locale) {
            $avatarUrl = null;
            if ($item->avatar) {
                $avatarUrl = filter_var($item->avatar, FILTER_VALIDATE_URL)
                    ? $item->avatar
                    : asset('storage/' . ltrim($item->avatar, '/'));
            }
            return [
                'id' => (string) $item->id,
                'name' => [
                    'en' => format_translatable($item->author_name, 'en'),
                    'ar' => format_translatable($item->author_name, 'ar'),
                ],
                'city' => [
                    'en' => format_translatable($item->city, 'en'),
                    'ar' => format_translatable($item->city, 'ar'),
                ],
                'occasion' => [
                    'en' => format_translatable($item->occasion_tag, 'en'),
                    'ar' => format_translatable($item->occasion_tag, 'ar'),
                ],
                'comment' => [
                    'en' => format_translatable($item->content, 'en'),
                    'ar' => format_translatable($item->content, 'ar'),
                ],
                'rating' => (float) $item->rating,
                'avatar' => $avatarUrl ?: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80',
                'verified' => (bool) $item->is_verified,
            ];
        })->values();

        $testimonialsTitle = $this->getTranslated($homePage->getTranslation('testimonials_title', $locale) ?: $homePage->testimonials_title, $locale)
            ?: ($locale === 'ar' ? 'تجارب عملائنا المميزين' : 'Stories from Our Clients');
        $testimonialsSubtitle = $this->getTranslated($homePage->getTranslation('testimonials_subtitle', $locale) ?: $homePage->testimonials_subtitle, $locale)
            ?: ($locale === 'ar' ? 'آراء وتقييمات حقيقية من عملائنا الكرام الذين شاركونا أجمل لحظاتهم' : 'Real feedback from those who trusted us with their special moments');

        // 6. Frequently Asked Questions (FAQs Section - top 5 on homepage)
        $faqs = \App\Models\Faq::active()->ordered()->limit(5)->get();
        $formattedFaqs = $faqs->map(function ($item) use ($locale) {
            return [
                'id' => (string) $item->id,
                'category' => format_translatable($item->category, $locale),
                'question' => [
                    'en' => format_translatable($item->question, 'en'),
                    'ar' => format_translatable($item->question, 'ar'),
                ],
                'answer' => [
                    'en' => format_translatable($item->answer, 'en'),
                    'ar' => format_translatable($item->answer, 'ar'),
                ],
            ];
        })->values();

        $faqTitle = $this->getTranslated($homePage->getTranslation('faq_title', $locale) ?: $homePage->faq_title, $locale)
            ?: ($locale === 'ar' ? 'الأسئلة الأكثر شيوعاً' : 'Frequently Asked Questions');
        $faqSubtitle = $this->getTranslated($homePage->getTranslation('faq_subtitle', $locale) ?: $homePage->faq_subtitle, $locale)
            ?: ($locale === 'ar' ? 'إجابات وافية وشاملة عن أكثر الأسئلة شيوعاً حول باقاتنا وخدمات التوصيل.' : 'Find quick answers to common questions about our fresh flowers, delivery, and services.');

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

        $bannerImgEn = $homePage->banner_image_en ? (str_starts_with($homePage->banner_image_en, 'http') ? $homePage->banner_image_en : asset('storage/' . ltrim($homePage->banner_image_en, '/'))) : null;
        $bannerImgAr = $homePage->banner_image_ar ? (str_starts_with($homePage->banner_image_ar, 'http') ? $homePage->banner_image_ar : asset('storage/' . ltrim($homePage->banner_image_ar, '/'))) : null;
        $activeBannerImg = ($locale === 'ar') ? ($bannerImgAr ?: $bannerImgEn) : ($bannerImgEn ?: $bannerImgAr);

        $bannerTitle = $this->getTranslated($homePage->getTranslation('banner_title', $locale) ?: $homePage->banner_title, $locale)
            ?: ($locale === 'ar' ? "توصيل في نفس اليوم\nزهور وهدايا فاخرة" : "Same Day Delivery\nFlowers & Gifts");

        $bannerButtonTitle = $this->getTranslated($homePage->getTranslation('banner_button_title', $locale) ?: $homePage->banner_button_title, $locale)
            ?: ($locale === 'ar' ? 'تسوق زهور اليوم نفسه' : 'Shop Same Day Flowers');

        $bannerButtonUrl = $homePage->banner_button_url ?: ($locale === 'ar' ? '/category/جميع-الزهور' : '/en/category/all-flowers');

        // Benefits Section
        $rawBenefits = $homePage->benefits_section ?: [];
        if (is_string($rawBenefits)) {
            $rawBenefits = json_decode($rawBenefits, true) ?: [];
        }
        $formattedBenefits = array_values(array_map(function ($item) use ($locale) {
            $title = is_array($item['title'] ?? null) ? ($item['title'][$locale] ?? ($item['title']['en'] ?? '')) : ($item['title'] ?? '');
            $desc = is_array($item['description'] ?? null) ? ($item['description'][$locale] ?? ($item['description']['en'] ?? '')) : ($item['description'] ?? '');
            return [
                'id' => $item['id'] ?? uniqid('b_'),
                'icon' => $item['icon'] ?? 'sprout',
                'title' => $title,
                'description' => $desc,
            ];
        }, $rawBenefits));

        // Events Showcase Section
        $rawEvents = $homePage->events_section ?: [];
        if (is_string($rawEvents)) {
            $rawEvents = json_decode($rawEvents, true) ?: [];
        }

        $eventBadge = is_array($rawEvents['badge'] ?? null) ? ($rawEvents['badge'][$locale] ?? ($rawEvents['badge']['en'] ?? '')) : ($rawEvents['badge'] ?? ($locale === 'ar' ? 'المناسبات' : 'EVENTS'));
        $eventTitleMain = is_array($rawEvents['title_main'] ?? null) ? ($rawEvents['title_main'][$locale] ?? ($rawEvents['title_main']['en'] ?? '')) : ($rawEvents['title_main'] ?? ($locale === 'ar' ? 'أضف لمسة من السحر لمناسباتك مع' : 'Blossom your events with our'));
        $eventTitleHighlight = is_array($rawEvents['title_highlight'] ?? null) ? ($rawEvents['title_highlight'][$locale] ?? ($rawEvents['title_highlight']['en'] ?? '')) : ($rawEvents['title_highlight'] ?? ($locale === 'ar' ? 'لمستنا الاحترافية!' : 'expert touch!'));
        $eventDesc = is_array($rawEvents['description'] ?? null) ? ($rawEvents['description'][$locale] ?? ($rawEvents['description']['en'] ?? '')) : ($rawEvents['description'] ?? '');
        $eventBtnText = is_array($rawEvents['button_text'] ?? null) ? ($rawEvents['button_text'][$locale] ?? ($rawEvents['button_text']['en'] ?? '')) : ($rawEvents['button_text'] ?? ($locale === 'ar' ? 'احجز الآن' : 'BOOK NOW'));
        $eventBtnUrl = $rawEvents['button_url'] ?? ($locale === 'ar' ? '/حجز-مناسبة' : '/en/event-booking');

        $tag1 = is_array($rawEvents['tag1'] ?? null) ? ($rawEvents['tag1'][$locale] ?? ($rawEvents['tag1']['en'] ?? '')) : ($rawEvents['tag1'] ?? ($locale === 'ar' ? 'حفلات الزفاف' : 'Weddings'));
        $tag2 = is_array($rawEvents['tag2'] ?? null) ? ($rawEvents['tag2'][$locale] ?? ($rawEvents['tag2']['en'] ?? '')) : ($rawEvents['tag2'] ?? ($locale === 'ar' ? 'فعاليات الشركات' : 'Corporate Events'));
        $tag3 = is_array($rawEvents['tag3'] ?? null) ? ($rawEvents['tag3'][$locale] ?? ($rawEvents['tag3']['en'] ?? '')) : ($rawEvents['tag3'] ?? ($locale === 'ar' ? 'مناسبات خاصة' : 'Special Occasions'));

        $eventSlides = [];
        if (!empty($rawEvents['slides']) && is_array($rawEvents['slides'])) {
            foreach ($rawEvents['slides'] as $s) {
                $slideImg = $s['image'] ?? null;
                if ($slideImg) {
                    $fullImg = str_starts_with($slideImg, 'http') ? $slideImg : asset('storage/' . ltrim($slideImg, '/'));
                } else {
                    $fullImg = null;
                }
                $slideTitle = is_array($s['title'] ?? null) ? ($s['title'][$locale] ?? ($s['title']['en'] ?? '')) : ($s['title'] ?? '');
                $slideTag = is_array($s['service_tag'] ?? null) ? ($s['service_tag'][$locale] ?? ($s['service_tag']['en'] ?? '')) : ($s['service_tag'] ?? ($locale === 'ar' ? 'باقة المناسبات' : 'EVENT SERVICE'));
                $slideUrl = $s['url'] ?? $eventBtnUrl;

                $eventSlides[] = [
                    'image' => $fullImg,
                    'title' => $slideTitle,
                    'service_tag' => $slideTag,
                    'url' => $slideUrl,
                ];
            }
        }

        $formattedEvents = [
            'badge' => $eventBadge,
            'title_main' => $eventTitleMain,
            'title_highlight' => $eventTitleHighlight,
            'description' => $eventDesc,
            'button_text' => $eventBtnText,
            'button_url' => $eventBtnUrl,
            'tags' => [
                ['icon' => 'flower', 'label' => $tag1],
                ['icon' => 'calendar', 'label' => $tag2],
                ['icon' => 'star', 'label' => $tag3],
            ],
            'slides' => $eventSlides,
        ];

        return response()->json([
            'slider_section' => $this->formatSliders($homePage->slider_section, $locale),
            'mobile_slider_section' => $this->formatSliders($homePage->mslider_section, $locale),
            'popular_section' => [
                'popular_title' => $popularTitle,
                'popular_subtitle' => $popularSubtitle ?: '',
                'popular_category' => $popularCategories,
            ],
            'category_sections' => $formattedCategorySections,
            'editorial_banner' => [
                'title' => $bannerTitle,
                'button_text' => $bannerButtonTitle,
                'button_url' => $bannerButtonUrl,
                'image' => $activeBannerImg ?: '/editorial-woman-bouquet.webp',
            ],
            'benefits_section' => $formattedBenefits,
            'events_section' => $formattedEvents,
            'best_sellers_section' => [
                'title' => $bestSellersTitle,
                'subtitle' => $this->getTranslated($homePage->getTranslation('best_sellers_subtitle', $locale) ?: $homePage->best_sellers_subtitle, $locale) ?: '',
                'products' => $bestSellers,
            ],
            'blog_section' => [
                'title' => $blogTitle,
                'subtitle' => $blogSubtitle,
                'posts' => $formattedBlogPosts,
            ],
            'testimonials_section' => [
                'title' => $testimonialsTitle,
                'subtitle' => $testimonialsSubtitle,
                'testimonials' => $formattedTestimonials,
            ],
            'faq_section' => [
                'title' => $faqTitle,
                'subtitle' => $faqSubtitle,
                'faqs' => $formattedFaqs,
            ],
            'banner' => [
                'banner_button_url' => $homePage->banner_button_url,
                'banner_button_title' => $this->getTranslated($homePage->getTranslation('banner_button_title', $locale) ?: $homePage->banner_button_title, $locale),
                'banner_description' => $this->getTranslated($homePage->getTranslation('banner_description', $locale) ?: $homePage->banner_description, $locale),
                'images' => $bannerImages,
            ],
            'seo' => [
                'meta_title' => $this->getTranslated($homePage->getTranslation('meta_tag_title', $locale) ?: $homePage->meta_tag_title, $locale),
                'meta_description' => $this->getTranslated($homePage->getTranslation('meta_tag_description', $locale) ?: $homePage->meta_tag_description, $locale),
                'meta_keywords' => $this->getTranslated($homePage->getTranslation('meta_tag_keywords', $locale) ?: $homePage->meta_tag_keywords, $locale),
            ],
        ]);
    }
}