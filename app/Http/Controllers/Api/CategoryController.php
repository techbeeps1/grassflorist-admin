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
        if (
            $request->routeIs('*en*') ||
            $request->segment(1) === 'en' ||
            $request->segment(2) === 'en' ||
            $request->query('locale') === 'en' ||
            $request->query('lang') === 'en' ||
            $request->header('X-Locale') === 'en' ||
            str_starts_with((string) $request->header('Accept-Language', ''), 'en')
        ) {
            return 'en';
        }

        if (
            $request->routeIs('*ar*') ||
            $request->segment(1) === 'ar' ||
            $request->segment(2) === 'ar' ||
            $request->query('locale') === 'ar' ||
            $request->query('lang') === 'ar' ||
            $request->header('X-Locale') === 'ar'
        ) {
            return 'ar';
        }

        return app()->getLocale() ?: 'ar';
    }

    public function formatCategory(Category $category, string $locale = 'en'): array
    {
        $catImg = $category->cat_image;
        $catImgUrl = null;
        if ($catImg) {
            $catImgUrl = str_starts_with($catImg, 'http') ? $catImg : asset('storage/' . ltrim($catImg, '/'));
        }

        $children = [];
        if ($category->relationLoaded('child') && $category->child->isNotEmpty()) {
            $children = $category->child->map(fn ($c) => $this->formatCategory($c, $locale))->values()->toArray();
        }

        return [
            'id' => $category->id,
            'name' => $category->getTranslation('name', $locale) ?: $category->name,
            'slug' => ($locale === 'ar' && filled($category->slug_ar)) ? $category->slug_ar : $category->slug,
            'description' => $category->getTranslation('description', $locale) ?: $category->description,
            'image' => $catImgUrl ?: $catImg,
            'image_path' => $catImg,
            'is_visible' => (bool) $category->is_visible,
            'child' => $children,
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $locale = $this->getLocale($request);

        $categories = Category::with('child')
            ->whereNull('parent_id')
            ->where('is_visible', true)
            ->get();

        return response()->json(
            $categories->map(fn ($cat) => $this->formatCategory($cat, $locale))->values()
        );
    }
}