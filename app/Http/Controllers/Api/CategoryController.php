<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    protected function getLocale(Request $request): string
    {
        // 1. Explicit URL route or segment check
        if ($request->routeIs('*en*') || $request->segment(1) === 'en' || $request->segment(2) === 'en') {
            return 'en';
        }
        if ($request->routeIs('*ar*') || $request->segment(1) === 'ar' || $request->segment(2) === 'ar') {
            return 'ar';
        }

        // 2. Explicit query parameter (?lang=... or ?locale=...)
        if ($request->query('locale') === 'en' || $request->query('lang') === 'en') {
            return 'en';
        }
        if ($request->query('locale') === 'ar' || $request->query('lang') === 'ar') {
            return 'ar';
        }

        // 3. Explicit custom header X-Locale
        if ($request->header('X-Locale') === 'en') {
            return 'en';
        }
        if ($request->header('X-Locale') === 'ar') {
            return 'ar';
        }

        // 4. Accept-Language header fallback
        $acceptLang = (string) $request->header('Accept-Language', '');
        if (str_starts_with($acceptLang, 'en')) {
            return 'en';
        }
        if (str_starts_with($acceptLang, 'ar')) {
            return 'ar';
        }

        // Default for Grass Florist Saudi Arabia is Arabic
        return app()->getLocale() ?: 'ar';
    }

    public function formatCategory(Category $category, string $locale = 'ar'): array
    {
        $catImg = $category->cat_image;
        $catImgUrl = null;
        if ($catImg) {
            $catImgUrl = str_starts_with($catImg, 'http') ? $catImg : asset('storage/' . ltrim($catImg, '/'));
        }

        $children = [];
        if ($category->relationLoaded('child') && $category->child->isNotEmpty()) {
            $children = $category->child
                ->filter(fn ($c) => (bool) $c->is_visible)
                ->map(fn ($c) => $this->formatCategory($c, $locale))
                ->values()
                ->toArray();
        }

        $nameAr = $category->getTranslation('name', 'ar');
        if (empty($nameAr) && is_array($category->name)) {
            $nameAr = $category->name['ar'] ?? '';
        }
        if (empty($nameAr)) {
            $nameAr = is_string($category->name) ? $category->name : '';
        }

        $nameEn = $category->getTranslation('name', 'en');
        if (empty($nameEn) && is_array($category->name)) {
            $nameEn = $category->name['en'] ?? '';
        }
        if (empty($nameEn)) {
            $nameEn = is_string($category->name) ? $category->name : '';
        }

        // Fallback for categories where database only stored one language
        if (empty($nameAr)) $nameAr = $nameEn;
        if (empty($nameEn)) $nameEn = $nameAr;

        $descAr = $category->getTranslation('description', 'ar') ?: (is_array($category->description) ? ($category->description['ar'] ?? '') : (is_string($category->description) ? $category->description : ''));
        $descEn = $category->getTranslation('description', 'en') ?: (is_array($category->description) ? ($category->description['en'] ?? '') : (is_string($category->description) ? $category->description : ''));

        $cName = $locale === 'ar' ? ($nameAr ?: $nameEn) : ($nameEn ?: $nameAr);
        $cSlug = ($locale === 'ar' && filled($category->slug_ar)) ? $category->slug_ar : $category->slug;
        $cDesc = $locale === 'ar' ? ($descAr ?: $descEn) : ($descEn ?: $descAr);

        return [
            'id' => $category->id,
            'name' => $cName,
            'slug' => $cSlug,
            'slug_ar' => $category->slug_ar ?: $category->slug,
            'slug_en' => $category->slug,
            'description' => $cDesc,
            'image' => $catImgUrl ?: $catImg,
            'image_path' => $catImg,
            'is_visible' => (bool) $category->is_visible,
            'child' => $children,
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $locale = $this->getLocale($request);

        $categories = Category::with(['child' => function ($q) {
            $q->where('is_visible', true);
        }])
            ->whereNull('parent_id')
            ->where('is_visible', true)
            ->get();

        return response()->json(
            $categories->map(fn ($cat) => $this->formatCategory($cat, $locale))->values()
        );
    }
}