<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    /**
     * Get active FAQs for the storefront accordion.
     * Supports:
     * - Default English: /api/faqs
     * - Arabic: /api/ar/faqs
     */
    public function index(Request $request): JsonResponse
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

        $category = $request->query('category');

        $query = Faq::query()
            ->active()
            ->ordered();

        if ($category) {
            $query->where(function ($q) use ($category) {
                $q->where('category->en', 'like', "%{$category}%")
                  ->orWhere('category->ar', 'like', "%{$category}%");
            });
        }

        $faqs = $query->get();

        $data = $faqs->map(function (Faq $item) use ($locale) {
            $enCat = format_translatable($item->category, 'en');
            $arCat = format_translatable($item->category, 'ar');
            $enQ = format_translatable($item->question, 'en');
            $arQ = format_translatable($item->question, 'ar');
            $enA = format_translatable($item->answer, 'en');
            $arA = format_translatable($item->answer, 'ar');

            return [
                'id' => $item->id,
                'category' => $locale === 'ar' ? ($arCat ?: $enCat) : ($enCat ?: $arCat),
                'question' => $locale === 'ar' ? ($arQ ?: $enQ) : ($enQ ?: $arQ),
                'answer' => $locale === 'ar' ? ($arA ?: $enA) : ($enA ?: $arA),
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
