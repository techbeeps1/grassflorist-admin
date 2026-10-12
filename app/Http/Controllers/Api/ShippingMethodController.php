<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShippingMethod;
use App\Models\ShippingSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShippingMethodController extends Controller
{
    /**
     * Get active shipping methods with multilingual support.
     */
    public function index(Request $request): JsonResponse
    {
        $locale = $request->header('X-Locale') ?: (app()->getLocale() ?: 'ar');

        $methods = ShippingMethod::where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->get()
            ->map(function (ShippingMethod $method) use ($locale) {
                $nameEn = format_translatable($method->name, 'en') ?: 'Delivery Inside Jeddah';
                $nameAr = format_translatable($method->name, 'ar') ?: 'التوصيل داخل جدة';

                $timeEn = format_translatable($method->delivery_time, 'en') ?: null;
                $timeAr = format_translatable($method->delivery_time, 'ar') ?: null;

                $descEn = format_translatable($method->description, 'en');
                $descAr = format_translatable($method->description, 'ar');

                return [
                    'id' => $method->id,
                    'code' => $method->code,
                    'name' => $locale === 'ar' ? $nameAr : $nameEn,
                    'name_en' => $nameEn,
                    'name_ar' => $nameAr,
                    'delivery_time' => $locale === 'ar' ? $timeAr : $timeEn,
                    'delivery_time_en' => $timeEn,
                    'delivery_time_ar' => $timeAr,
                    'description' => $locale === 'ar' ? ($descAr ?: $descEn) : ($descEn ?: $descAr),
                    'description_en' => $descEn,
                    'description_ar' => $descAr,
                    'city' => $method->city ?: 'Jeddah',
                    'price' => (float)$method->price,
                    'is_free_shipping_eligible' => (bool)$method->is_free_shipping_eligible,
                    'min_order_for_free_shipping' => $method->min_order_for_free_shipping ? (float)$method->min_order_for_free_shipping : null,
                    'is_active' => (bool)$method->is_active,
                ];
            });

        // Global shipping settings fallback
        $shippingSetting = ShippingSetting::current();

        return response()->json([
            'success' => true,
            'default_city' => 'Jeddah',
            'free_shipping_threshold' => (float)($shippingSetting->min_order_for_free_shipping ?? 250.00),
            'default_shipping_fee' => (float)($methods->first()['price'] ?? 30.00),
            'data' => $methods,
        ]);
    }
}
