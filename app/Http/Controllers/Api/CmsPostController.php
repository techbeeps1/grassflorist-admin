<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CmsPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CmsPostController extends Controller
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

    public function formatPost(CmsPost $post, string $locale): array
    {
        $img = $post->image;
        $imgUrl = null;
        if ($img) {
            $imgUrl = str_starts_with($img, 'http') ? $img : asset('storage/' . ltrim($img, '/'));
        }

        $banner = $post->banner_image;
        $bannerUrl = null;
        if ($banner) {
            $bannerUrl = str_starts_with($banner, 'http') ? $banner : asset('storage/' . ltrim($banner, '/'));
        }

        return [
            'id' => $post->id,
            'title' => $post->getTranslation('title', $locale) ?: (is_array($post->title) ? ($post->title[$locale] ?? reset($post->title)) : $post->title),
            'slug' => ($locale === 'ar' && filled($post->slug_ar)) ? $post->slug_ar : $post->slug,
            'short_description' => $post->getTranslation('short_description', $locale) ?: (is_array($post->short_description) ? ($post->short_description[$locale] ?? '') : $post->short_description),
            'content' => $post->getTranslation('content', $locale) ?: (is_array($post->content) ? ($post->content[$locale] ?? '') : $post->content),
            'image' => $imgUrl ?: $img,
            'banner_image' => $bannerUrl ?: $banner,
            'author' => $post->author,
            'published_at' => $post->published_at,
            'views_count' => $post->views_count,
            'seo' => [
                'meta_title' => $post->getTranslation('meta_title', $locale) ?: $post->meta_title,
                'meta_description' => $post->getTranslation('meta_description', $locale) ?: $post->meta_description,
                'meta_keywords' => $post->getTranslation('meta_keywords', $locale) ?: $post->meta_keywords,
            ],
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $locale = $this->getLocale($request);
        $posts = CmsPost::where('is_active', true)
            ->orderBy('published_at', 'desc')
            ->get();

        return response()->json($posts->map(fn ($p) => $this->formatPost($p, $locale))->values());
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        return $this->showBySlug($request, $slug);
    }

    public function showBySlug(Request $request, string $slug): JsonResponse
    {
        $locale = $this->getLocale($request);
        $post = CmsPost::where(function ($q) use ($slug) {
            $q->where('slug', $slug)->orWhere('slug_ar', $slug);
        })->where('is_active', true)->firstOrFail();

        return response()->json($this->formatPost($post, $locale));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:cms_posts',
            'content' => 'nullable|string',
            'image' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:255',
            'meta_keywords' => 'nullable|string|max:255',
            'is_active' => 'boolean'
        ]);

        $post = CmsPost::create($validated);
        return response()->json($post, 201);
    }

    public function update(Request $request, string $id)
    {
        $post = CmsPost::findOrFail($id);
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|max:255|unique:cms_posts,slug,'.$post->id,
            'content' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:255',
            'meta_keywords' => 'nullable|string|max:255',
            'is_active' => 'boolean'
        ]);

        $post->update($validated);
        return response()->json($post);
    }

    public function destroy(string $id)
    {
        $post = CmsPost::findOrFail($id);
        $post->delete();
        return response()->json(null, 204);
    }
}