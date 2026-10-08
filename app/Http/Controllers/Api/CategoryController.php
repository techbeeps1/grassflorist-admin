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
            $request->routeIs('*ar*') ||
            $request->segment(1) === 'ar' ||
            $request->segment(2) === 'ar' ||
            $request->query('lang') === 'ar' ||
            $request->header('X-Locale') === 'ar'
        ) {
            return 'ar';
        }

        if ($request->has('lang')) {
            $lang = strtolower(substr((string) $request->query('lang'), 0, 2));
            if (in_array($lang, ['en', 'ar'])) {
                return $lang;
            }
        }

        return app()->getLocale() ?: 'en';
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