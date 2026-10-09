<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GlobalSetting;
use Illuminate\Http\JsonResponse;

class GlobalSettingController extends Controller
{
    public function index(): JsonResponse
    {
        $setting = GlobalSetting::current();

        return response()->json([
            'success' => true,
            'data' => [
                'currency' => [
                    'default' => 'SAR',
                    'sar_to_usd_rate' => (float)($setting->sar_to_usd_rate ?: 0.2667),
                    'usd_to_sar_rate' => round(1 / ((float)($setting->sar_to_usd_rate ?: 0.2667)), 4),
                    'last_fetch_at' => $setting->last_currency_rate_fetch_at,
                    'supported' => [
                        [
                            'code' => 'SAR',
                            'name_en' => 'Saudi riyal (SAR)',
                            'name_ar' => 'ريال سعودي (SAR)',
                            'symbol' => 'SAR',
                            'symbol_native' => 'ر.س',
                            'rate' => 1.0,
                        ],
                        [
                            'code' => 'USD',
                            'name_en' => 'United States (US) dollar (USD)',
                            'name_ar' => 'دولار أمريكي (USD)',
                            'symbol' => '$',
                            'symbol_native' => '$',
                            'rate' => (float)($setting->sar_to_usd_rate ?: 0.2667),
                        ],
                    ],
                ],
                'tax' => [
                    'vat_percentage' => (float)($setting->vat_percentage ?? 15.00),
                    'vat_registration_number' => $setting->vat_registration_number,
                ],
                'branding' => [
                    'site_name' => [
                        'en' => $setting->site_name_en ?: $setting->site_name ?: 'Grass Florist',
                        'ar' => $setting->site_name_ar ?: $setting->site_name ?: 'غراس فلوريست',
                    ],
                    'site_tagline' => [
                        'en' => $setting->site_tagline_en ?: $setting->site_tagline ?: 'Luxury Floral Atelier & Curated Gifting',
                        'ar' => $setting->site_tagline_ar ?: 'متجر الزهور والهدايا الفاخرة',
                    ],
                    'site_logo' => $setting->site_logo_url ?: '/grass-logo.jpg',
                    'site_logo_dark' => $setting->site_logo_dark_url ?: '/grass-logo.jpg',
                    'site_favicon' => $setting->site_favicon_url ?: '/favicon.ico',
                ],
                'topbar' => [
                    'enabled' => (bool)$setting->topbar_enabled,
                    'text' => [
                        'en' => $setting->topbar_text_en ?: 'Express Same-Day Delivery in 2 Hours | Free Delivery on Orders Over 250 SAR',
                        'ar' => $setting->topbar_text_ar ?: 'توصيل سريع في نفس اليوم خلال ساعتين | شحن مجاني للطلبات فوق 250 ر.س',
                    ],
                    'phone' => $setting->contact_phone ?: '+966 55 513 4211',
                ],
                'footer' => [
                    'about' => [
                        'en' => $setting->footer_about_en ?: 'Grass Florist is a luxury floral atelier and gifting boutique crafting bespoke bouquets, Belgian chocolates, and living plants with same-day express delivery across Saudi Arabia.',
                        'ar' => $setting->footer_about_ar ?: 'غراس فلوريست هي بوتيك واستوديو للزهور والهدايا الفاخرة، تقدم باقات منسقة خصيصاً، وشوكولاتة بلجيكية فاخرة، ونباتات داخلية مع توصيل مبرد وسريع في نفس اليوم في المملكة العربية السعودية.',
                    ],
                    'copyright' => [
                        'en' => $setting->footer_copyright_en ?: $setting->footer_copyright ?: 'Copyright© 2026, GRASS Florist, All Rights Reserved.',
                        'ar' => $setting->footer_copyright_ar ?: 'جميع الحقوق محفوظة © 2026، غراس فلوريست.',
                    ],
                ],
                'contact' => [
                    'email' => $setting->contact_email ?: 'info@grassflorist.com',
                    'phone' => $setting->contact_phone ?: '+966 55 513 4211',
                    'whatsapp' => $setting->contact_whatsapp ?: '+966 55 513 4211',
                    'address' => [
                        'en' => $setting->contact_address_en ?: $setting->contact_address ?: '4366 Al Kayyal Street, Al-Rawdah District, Jeddah 23434, Saudi Arabia',
                        'ar' => $setting->contact_address_ar ?: '4366 شارع الكيال، حي الروضة، جدة 23434، المملكة العربية السعودية',
                    ],
                    'business_hours' => $setting->business_hours ?: 'Daily 9:00 AM – 11:30 PM AST',
                ],
                'social' => [
                    'facebook' => $setting->social_facebook ?: 'https://facebook.com/grassflorist',
                    'instagram' => $setting->social_instagram ?: 'https://instagram.com/grassflorist',
                    'twitter' => $setting->social_twitter ?: 'https://twitter.com/grassflorist',
                    'whatsapp' => $setting->contact_whatsapp ? ('https://wa.me/' . preg_replace('/[^0-9]/', '', $setting->contact_whatsapp)) : 'https://wa.me/966555134211',
                    'snapchat' => $setting->social_snapchat ?: 'https://snapchat.com/add/grassflorist',
                    'tiktok' => $setting->social_tiktok ?: 'https://tiktok.com/@grassflorist',
                    'youtube' => $setting->social_youtube,
                    'linkedin' => $setting->social_linkedin,
                ],
                'scripts' => [
                    'gtm' => [
                        'id' => $setting->google_tag_manager_id,
                        'head_code' => $setting->gtm_head_code,
                        'body_code' => $setting->gtm_body_code,
                    ],
                    'google_analytics' => [
                        'measurement_id' => $setting->ga_measurement_id ?: $setting->google_analytics_id,
                        'script_code' => $setting->ga_script_code,
                    ],
                    'meta_pixel' => [
                        'pixel_id' => $setting->meta_pixel_id,
                        'pixel_code' => $setting->meta_pixel_code,
                    ],
                    'tiktok_pixel' => [
                        'pixel_id' => $setting->tiktok_pixel_id,
                    ],
                    'snapchat_pixel' => [
                        'pixel_id' => $setting->snapchat_pixel_id,
                    ],
                    'custom_head_scripts' => $setting->custom_head_scripts,
                    'custom_footer_scripts' => $setting->custom_footer_scripts,
                ],
                'seo' => [
                    'sitemap' => [
                        'enabled' => $setting->sitemap_enabled !== false,
                        'include_products' => $setting->sitemap_include_products !== false,
                        'include_categories' => $setting->sitemap_include_categories !== false,
                        'include_static_pages' => $setting->sitemap_include_static_pages !== false,
                        'include_blog' => $setting->sitemap_include_blog !== false,
                        'excluded_paths' => $setting->sitemap_excluded_paths
                            ? array_values(array_filter(array_map('trim', explode("\n", str_replace("\r", "", $setting->sitemap_excluded_paths)))))
                            : [],
                        'excluded_paths_raw' => $setting->sitemap_excluded_paths,
                    ],
                    'robots' => [
                        'custom_content' => $setting->robots_custom_content,
                    ],
                    'llms' => [
                        'enabled' => $setting->llms_enabled !== false,
                        'custom_content' => $setting->llms_custom_content,
                    ],
                ],
            ],
        ]);
    }

    public function fetchCurrencyRate(): JsonResponse
    {
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(6)->get('https://open.er-api.com/v6/latest/SAR');
            if ($response->successful()) {
                $data = $response->json();
                $usdRate = $data['rates']['USD'] ?? null;
                if ($usdRate && is_numeric($usdRate)) {
                    $setting = GlobalSetting::current();
                    $setting->update([
                        'sar_to_usd_rate' => round($usdRate, 4),
                        'last_currency_rate_fetch_at' => now(),
                    ]);
                    \Illuminate\Support\Facades\Cache::forget('global_site_settings');

                    return response()->json([
                        'success' => true,
                        'message' => 'Currency conversion rate updated successfully',
                        'sar_to_usd_rate' => (float)$setting->sar_to_usd_rate,
                        'usd_to_sar_rate' => round(1 / (float)$setting->sar_to_usd_rate, 4),
                        'fetched_at' => now()->toDateTimeString(),
                    ]);
                }
            }
            throw new \Exception('Failed to retrieve USD rate from exchange API');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch live currency rate: ' . $e->getMessage(),
            ], 500);
        }
    }
}
