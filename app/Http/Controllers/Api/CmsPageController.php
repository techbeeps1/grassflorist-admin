<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CmsPageController extends Controller
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

    public function formatPage(CmsPage $page, string $locale): array
    {
        $banners = [];
        if (!empty($page->banner_images) && is_array($page->banner_images)) {
            foreach ($page->banner_images as $img) {
                if (empty($img)) continue;
                $banners[] = str_starts_with($img, 'http') ? $img : asset('storage/' . ltrim($img, '/'));
            }
        }

        return [
            'id' => $page->id,
            'title' => $page->getTranslation('title', $locale) ?: (is_array($page->title) ? ($page->title[$locale] ?? reset($page->title)) : $page->title),
            'slug' => ($locale === 'ar' && filled($page->slug_ar)) ? $page->slug_ar : $page->slug,
            'short_description' => $page->getTranslation('short_description', $locale) ?: (is_array($page->short_description) ? ($page->short_description[$locale] ?? '') : $page->short_description),
            'content' => $page->getTranslation('content', $locale) ?: (is_array($page->content) ? ($page->content[$locale] ?? '') : $page->content),
            'banner_images' => $banners,
            'seo' => [
                'meta_title' => $page->getTranslation('meta_title', $locale) ?: $page->meta_title,
                'meta_description' => $page->getTranslation('meta_description', $locale) ?: $page->meta_description,
                'meta_keywords' => $page->getTranslation('meta_keywords', $locale) ?: $page->meta_keywords,
            ],
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $locale = $this->getLocale($request);
        $pages = CmsPage::where('is_active', true)->get();

        return response()->json($pages->map(fn ($p) => $this->formatPage($p, $locale))->values());
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        return $this->showBySlug($request, $slug);
    }

    public function showBySlug(Request $request, string $slug): JsonResponse
    {
        $locale = $this->getLocale($request);
        $page = CmsPage::where(function ($q) use ($slug) {
            $q->where('slug', $slug)->orWhere('slug_ar', $slug);
        })->where('is_active', true)->firstOrFail();

        return response()->json($this->formatPage($page, $locale));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:cms_pages',
            'content' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:255',
            'meta_keywords' => 'nullable|string|max:255',
            'is_active' => 'boolean'
        ]);

        $page = CmsPage::create($validated);
        return response()->json($page, 201);
    }

    public function update(Request $request, string $id)
    {
        $page = CmsPage::findOrFail($id);
        $validated = $request->validate([
            'title' => 'sometimes|required',
            'slug' => 'sometimes|required|string|max:255',
            'slug_ar' => 'nullable|string|max:255',
            'content' => 'nullable',
            'meta_title' => 'nullable',
            'meta_description' => 'nullable',
            'meta_keywords' => 'nullable',
            'is_active' => 'boolean'
        ]);

        $page->update($validated);
        return response()->json($page);
    }

    public function destroy(string $id)
    {
        $page = CmsPage::findOrFail($id);
        $page->delete();
        return response()->json(null, 204);
    }
}