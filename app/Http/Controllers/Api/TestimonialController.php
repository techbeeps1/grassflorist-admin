<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TestimonialController extends Controller
{
    /**
     * Get active testimonials for the storefront carousel.
     * Supports:
     * - Default English: /api/testimonials or /api/testimonials/limit=10
     * - Arabic: /api/ar/testimonials or /api/ar/testimonials/limit=10
     */
    public function index(Request $request, $limit = null): JsonResponse
    {
        // 1. Determine Language (Default 'ar', or 'en' if /en/ prefix or ?lang=en)
        $locale = 'ar';
        if (
            $request->routeIs('*en*') ||
            $request->segment(1) === 'en' ||
            $request->segment(2) === 'en' ||
            $request->query('lang') === 'en' ||
            $request->header('X-Locale') === 'en'
        ) {
            $locale = 'en';
        } elseif (
            $request->routeIs('*ar*') ||
            $request->segment(1) === 'ar' ||
            $request->segment(2) === 'ar' ||
            $request->query('lang') === 'ar' ||
            $request->header('X-Locale') === 'ar'
        ) {
            $locale = 'ar';
        }

        // 2. Determine Limit (Supports /limit=10, /{number}, or ?limit=10)
        $limitValue = 20;
        if (!empty($limit)) {
            if (is_string($limit) && str_starts_with($limit, 'limit=')) {
                $limitValue = (int) str_replace('limit=', '', $limit);
            } elseif (is_numeric($limit)) {
                $limitValue = (int) $limit;
            }
        } elseif ($request->has('limit')) {
            $limitValue = (int) $request->query('limit');
        }

        $limitValue = min(max($limitValue, 1), 50);

        $testimonials = Testimonial::query()
            ->active()
            ->ordered()
            ->limit($limitValue)
            ->get();

        $data = $testimonials->map(function (Testimonial $item) use ($locale) {
            $avatarUrl = null;
            if ($item->avatar) {
                $avatarUrl = filter_var($item->avatar, FILTER_VALIDATE_URL)
                    ? $item->avatar
                    : Storage::disk('public')->url($item->avatar);
            }

            $enName = format_translatable($item->author_name, 'en');
            $arName = format_translatable($item->author_name, 'ar');
            $enCity = format_translatable($item->city, 'en');
            $arCity = format_translatable($item->city, 'ar');
            $enTag = format_translatable($item->occasion_tag, 'en');
            $arTag = format_translatable($item->occasion_tag, 'ar');
            $enContent = format_translatable($item->content, 'en');
            $arContent = format_translatable($item->content, 'ar');

            return [
                'id' => $item->id,
                'author_name' => $locale === 'ar' ? ($arName ?: $enName) : ($enName ?: $arName),
                'city' => $locale === 'ar' ? ($arCity ?: $enCity) : ($enCity ?: $arCity),
                'occasion_tag' => $locale === 'ar' ? ($arTag ?: $enTag) : ($enTag ?: $arTag),
                'content' => $locale === 'ar' ? ($arContent ?: $enContent) : ($enContent ?: $arContent),
                'rating' => (float) $item->rating,
                'avatar' => $avatarUrl,
                'is_verified' => (bool) $item->is_verified,
                'sort_order' => (int) $item->sort_order,
            ];
        });

        return response()->json([
            'success' => true,
            'locale' => $locale,
            'count' => $data->count(),
            'data' => $data,
        ]);
    }
}
