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
                'branding' => [
                    'site_name' => $setting->site_name ?: 'Grass Florist',
                    'site_tagline' => $setting->site_tagline,
                    'site_logo' => $setting->site_logo_url,
                    'site_logo_dark' => $setting->site_logo_dark_url,
                    'site_favicon' => $setting->site_favicon_url,
                    'footer_copyright' => $setting->footer_copyright,
                ],
                'scripts' => [
                    'gtm' => [
                        'head_code' => $setting->gtm_head_code,
                        'body_code' => $setting->gtm_body_code,
                    ],
                    'google_analytics' => [
                        'measurement_id' => $setting->ga_measurement_id,
                        'script_code' => $setting->ga_script_code,
                    ],
                    'meta_pixel' => [
                        'pixel_id' => $setting->meta_pixel_id,
                        'pixel_code' => $setting->meta_pixel_code,
                    ],
                    'custom_head_scripts' => $setting->custom_head_scripts,
                    'custom_footer_scripts' => $setting->custom_footer_scripts,
                ],
                'contact' => [
                    'email' => $setting->contact_email,
                    'phone' => $setting->contact_phone,
                    'whatsapp' => $setting->contact_whatsapp,
                    'address' => $setting->contact_address,
                    'business_hours' => $setting->business_hours,
                ],
                'social' => [
                    'facebook' => $setting->social_facebook,
                    'instagram' => $setting->social_instagram,
                    'twitter' => $setting->social_twitter,
                    'youtube' => $setting->social_youtube,
                    'linkedin' => $setting->social_linkedin,
                ],
            ],
        ]);
    }
}
