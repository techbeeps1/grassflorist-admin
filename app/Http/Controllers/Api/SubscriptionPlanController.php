<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SubscriptionSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionPlanController extends Controller
{
    /**
     * Get all active subscription deals for landing page (/flowers-subscription-deals)
     */
    public function index(Request $request): JsonResponse
    {
        $locale = $request->query('locale', 'ar');
        $settings = SubscriptionSetting::current();

        $productIds = $settings->subscription_product_ids ?: [];

        // Products explicitly selected in Subscription Settings are included regardless of is_visible
        if (!empty($productIds)) {
            $productIdsInt = array_map('intval', (array)$productIds);
            $products = Product::whereIn('id', $productIdsInt)->get();
            $orderMap = array_flip($productIdsInt);
            $products = $products->sortBy(fn($p) => $orderMap[(int)$p->id] ?? 999)->values();
        } else {
            // Fallback if no products selected yet in Subscription Settings
            $products = Product::where('is_visible', 1)
                ->whereNotNull('image')
                ->limit(3)
                ->get();
        }

        $plans = $products->map(function (Product $product) use ($locale, $settings) {
            return $this->formatProductAsPlanCard($product, $locale, $settings);
        });

        $landingData = [
            'hero' => [
                'title' => [
                    'en' => 'Grass Subscriptions',
                    'ar' => 'اشتراكات غراس للزهور',
                ],
                'subtitle' => [
                    'en' => 'Make any day special with fresh flowers delivered to you or your loved ones regularly.',
                    'ar' => 'اجعل كل يوم مميزاً بزهور طبيعية منعشة تصلك أو لمن تحب بانتظام وبأعلى درجات الفخامة.',
                ],
                'image' => $products->first()?->image_url ?: 'https://api.grassflorist.com/storage/products/g46220flower20delivery20jeddah202-600x600-1.jpg',
            ],
            'perks' => [
                [
                    'id' => 1,
                    'icon' => 'percent',
                    'title' => [
                        'en' => 'Subscribe and save up to 20%',
                        'ar' => 'اشترك ووفر حتى 20%',
                    ],
                ],
                [
                    'id' => 2,
                    'icon' => 'truck',
                    'title' => [
                        'en' => 'Free delivery on every order',
                        'ar' => 'توصيل مجاني لكل باقة',
                    ],
                ],
                [
                    'id' => 3,
                    'icon' => 'gift',
                    'title' => [
                        'en' => 'Get an exclusive free vase on your 1st delivery!',
                        'ar' => 'مزهرية فاخرة مجانية مع التوصيلة الأولى!',
                    ],
                ],
            ],
            'how_it_works' => [
                [
                    'step' => 1,
                    'title' => [
                        'en' => 'Choose your favorite bouquet',
                        'ar' => 'اختر باقة ورودك المفضلة',
                    ],
                    'description' => [
                        'en' => 'Select from our signature bouquets to bring ongoing floral magic to your space.',
                        'ar' => 'اختر الباقة الأنسب لذوقك من باقاتنا الفاخرة لتملأ منزلك أو مكتبك بالجمال والانتعاش.',
                    ],
                ],
                [
                    'step' => 2,
                    'title' => [
                        'en' => 'Choose your frequency & duration',
                        'ar' => 'حدد جدول التكرار والمدة',
                    ],
                    'description' => [
                        'en' => 'Pick delivery schedule (weekly, bi-weekly, or monthly) with exclusive duration discounts.',
                        'ar' => 'حدد مواعيد الاستلام (أسبوعياً، كل أسبوعين، أو شهرياً) مع خصومات كبرى للمدد الأطول.',
                    ],
                ],
                [
                    'step' => 3,
                    'title' => [
                        'en' => 'Let every bouquet bring joy',
                        'ar' => 'استمتع بالبهجة والجمال',
                    ],
                    'description' => [
                        'en' => 'With every scheduled delivery, fresh stems arrive safely with complimentary packaging.',
                        'ar' => 'مع كل باقة، لحظات من البهجة والعبير الأخاذ تصل بأمان وتزيّن يومك.',
                    ],
                ],
            ],
            'plans' => $plans,
        ];

        return response()->json([
            'success' => true,
            'data' => $landingData,
        ]);
    }

    /**
     * Get single subscription plan details for configurator page
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $locale = $request->query('locale', 'ar');
        $settings = SubscriptionSetting::current();

        if (!$settings->is_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'Subscriptions are currently disabled.',
            ], 404);
        }

        $subscriptionProductIds = array_map('intval', (array)($settings->subscription_product_ids ?: []));

        // If no products configured in Subscription Settings, return 404
        if (empty($subscriptionProductIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription plan not found in subscription settings.',
            ], 404);
        }

        $decodedSlug = urldecode($slug);

        // ONLY allow products that are explicitly configured in Subscription Settings.
        // Product is allowed even if is_visible is false (hidden from normal storefront).
        $product = Product::whereIn('id', $subscriptionProductIds)
            ->where(function ($q) use ($slug, $decodedSlug) {
                $q->where('slug', $slug)
                  ->orWhere('slug_ar', $slug)
                  ->orWhere('slug', $decodedSlug)
                  ->orWhere('slug_ar', $decodedSlug);

                if (is_numeric($slug)) {
                    $q->orWhere('id', (int)$slug);
                }
            })
            ->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription plan not found or not configured in subscription settings.',
            ], 404);
        }

        $defaultDiscount = (float)($settings->default_discount_percentage ?? 10.00);
        $durationDiscounts = $settings->duration_discounts ?: [
            '1_month' => 0,
            '3_months' => 10,
            '6_months' => 15,
            '12_months' => 20,
        ];
        $allowedFrequencies = $settings->allowed_frequencies ?: ['weekly', 'biweekly', 'monthly'];

        $originalPrice = (float)$product->price;
        $startingPrice = round($originalPrice * (1 - ($defaultDiscount / 100)), 2);

        $nameEn = $product->getTranslation('name', 'en') ?: $product->name;
        $nameAr = $product->getTranslation('name', 'ar') ?: $product->name;
        $rawDescEn = $product->getTranslation('description', 'en');
        $rawDescAr = $product->getTranslation('description', 'ar');
        $rawSubTitleEn = $product->getTranslation('sub_title', 'en');
        $rawSubTitleAr = $product->getTranslation('sub_title', 'ar');

        // Product Description only contains actual description if present
        $descEn = !empty($rawDescEn) && is_string($rawDescEn) ? trim($rawDescEn) : '';
        $descAr = !empty($rawDescAr) && is_string($rawDescAr) ? trim($rawDescAr) : '';

        // Short description contains sub_title if present, or fallback to description
        $shortDescEn = !empty($rawSubTitleEn) && is_string($rawSubTitleEn) ? trim($rawSubTitleEn) : $descEn;
        $shortDescAr = !empty($rawSubTitleAr) && is_string($rawSubTitleAr) ? trim($rawSubTitleAr) : $descAr;

        // If description is identical to short description, keep description empty
        if ($descEn === $shortDescEn) {
            $descEn = '';
        }
        if ($descAr === $shortDescAr) {
            $descAr = '';
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $product->id,
                'slug' => $product->slug,
                'product_id' => $product->id,
                'name' => [
                    'en' => $nameEn,
                    'ar' => $nameAr,
                ],
                'localized_name' => $locale === 'ar' ? $nameAr : $nameEn,
                'badge' => [
                    'en' => "Save {$defaultDiscount}%",
                    'ar' => "وفر {$defaultDiscount}%",
                ],
                'localized_badge' => $locale === 'ar' ? "وفر {$defaultDiscount}%" : "Save {$defaultDiscount}%",
                'starting_price' => $startingPrice,
                'original_price' => $originalPrice,
                'short_description' => [
                    'en' => $shortDescEn,
                    'ar' => $shortDescAr,
                ],
                'localized_short_description' => $locale === 'ar' ? $shortDescAr : $shortDescEn,
                'description' => [
                    'en' => $descEn,
                    'ar' => $descAr,
                ],
                'localized_description' => $locale === 'ar' ? $descAr : $descEn,
                'image' => $product->image_url,
                'gallery' => $product->gallery_urls ?: ($product->image_url ? [$product->image_url] : []),
                'features' => [
                    $locale === 'ar' ? 'زهور طبيعية طازجة منتقاة بعناية دورياً' : 'Fresh hand-picked blooms on every cycle',
                    $locale === 'ar' ? 'توصيل مجاني داخل جدة في كل دورة' : 'Free delivery inside Jeddah on every cycle',
                    $locale === 'ar' ? 'مزهرية فاخرة مجانية مع التوصيلة الأولى' : 'Free luxury vase on 1st delivery',
                    $locale === 'ar' ? 'إيقاف مؤقت، تخطي أو إلغاء في أي وقت' : 'Pause, skip or cancel at any time with 1-click',
                ],
                'allowed_frequencies' => $allowedFrequencies,
                'duration_discounts' => $durationDiscounts,
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'price' => $originalPrice,
                    'image' => $product->image_url,
                ],
            ],
        ]);
    }

    /**
     * Format Product as Plan card for landing grid
     */
    protected function formatProductAsPlanCard(Product $product, string $locale, SubscriptionSetting $settings): array
    {
        $defaultDiscount = (float)($settings->default_discount_percentage ?? 10.00);
        $originalPrice = (float)$product->price;
        $startingPrice = round($originalPrice * (1 - ($defaultDiscount / 100)), 2);

        $nameEn = $product->getTranslation('name', 'en') ?: $product->name;
        $nameAr = $product->getTranslation('name', 'ar') ?: $product->name;

        $rawDescEn = $product->getTranslation('description', 'en');
        $rawDescAr = $product->getTranslation('description', 'ar');
        $rawSubTitleEn = $product->getTranslation('sub_title', 'en');
        $rawSubTitleAr = $product->getTranslation('sub_title', 'ar');

        $descEn = !empty($rawDescEn) && is_string($rawDescEn) ? trim($rawDescEn) : '';
        $descAr = !empty($rawDescAr) && is_string($rawDescAr) ? trim($rawDescAr) : '';

        $shortDescEn = !empty($rawSubTitleEn) && is_string($rawSubTitleEn) ? trim($rawSubTitleEn) : $descEn;
        $shortDescAr = !empty($rawSubTitleAr) && is_string($rawSubTitleAr) ? trim($rawSubTitleAr) : $descAr;

        if ($descEn === $shortDescEn) {
            $descEn = '';
        }
        if ($descAr === $shortDescAr) {
            $descAr = '';
        }

        return [
            'id' => $product->id,
            'slug' => $product->slug,
            'product_id' => $product->id,
            'name' => [
                'en' => $nameEn,
                'ar' => $nameAr,
            ],
            'localized_name' => $locale === 'ar' ? $nameAr : $nameEn,
            'badge' => [
                'en' => "Save {$defaultDiscount}%",
                'ar' => "وفر {$defaultDiscount}%",
            ],
            'localized_badge' => $locale === 'ar' ? "وفر {$defaultDiscount}%" : "Save {$defaultDiscount}%",
            'starting_price' => $startingPrice,
            'original_price' => $originalPrice,
            'short_description' => [
                'en' => $shortDescEn,
                'ar' => $shortDescAr,
            ],
            'localized_short_description' => $locale === 'ar' ? $shortDescAr : $shortDescEn,
            'description' => [
                'en' => $descEn,
                'ar' => $descAr,
            ],
            'localized_description' => $locale === 'ar' ? $descAr : $descEn,
            'image' => $product->image_url,
            'features' => [
                $locale === 'ar' ? 'زهور طازجة مع كل دورة توصيل' : 'Fresh blooms with every delivery',
                $locale === 'ar' ? 'توصيل مجاني مجدول بانتظام' : 'Free regular scheduled delivery',
                $locale === 'ar' ? 'مزهرية فاخرة مجانية مع أول طلب' : 'Free vase on first delivery',
                $locale === 'ar' ? 'مرونة كاملة في التعديل والإلغاء' : 'Complete flexibility to pause or cancel',
            ],
        ];
    }
}
