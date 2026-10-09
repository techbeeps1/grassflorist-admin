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

        $defaultImg = '/images/blog-placeholder.svg';
        $coverImg = $imgUrl ?: ($bannerUrl ?: $defaultImg);

        $catNameEn = 'Floral Guides';
        $catNameAr = 'أدلة الزهور';
        $catSlug = 'floral-guides';
        if ($post->category) {
            $catNameEn = format_translatable($post->category->name, 'en') ?: 'Floral Guides';
            $catNameAr = format_translatable($post->category->name, 'ar') ?: 'أدلة الزهور';
            $catSlug = $post->category->slug ?: 'floral-guides';
        }

        $titleEn = format_translatable($post->title, 'en') ?: $post->slug;
        $titleAr = format_translatable($post->title, 'ar') ?: (format_translatable($post->title, 'en') ?: $post->slug);

        $shortDescEn = format_translatable($post->short_description, 'en') ?: '';
        $shortDescAr = format_translatable($post->short_description, 'ar') ?: '';

        $contentEn = format_translatable($post->content, 'en') ?: '';
        $contentAr = format_translatable($post->content, 'ar') ?: '';

        $slugEn = $post->slug;
        $slugAr = $post->slug_ar ?: $post->slug;

        $authorName = $post->author ?: 'Grass Florist Atelier';

        // Calculate read time roughly
        $wordCount = str_word_count(strip_tags($contentEn ?: $contentAr));
        $readMinutes = max(2, (int) ceil($wordCount / 160));

        return [
            'id' => (string) $post->id,
            'slug' => [
                'en' => $slugEn,
                'ar' => $slugAr,
            ],
            'title' => [
                'en' => $titleEn,
                'ar' => $titleAr,
            ],
            'excerpt' => [
                'en' => $shortDescEn,
                'ar' => $shortDescAr,
            ],
            'content' => [
                'en' => $contentEn,
                'ar' => $contentAr,
            ],
            'coverImage' => $coverImg,
            'image' => $coverImg,
            'author' => [
                'name' => [
                    'en' => $authorName,
                    'ar' => $authorName,
                ],
                'role' => [
                    'en' => 'Master Floral Designer',
                    'ar' => 'كبير مصممي الزهور',
                ],
                'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80',
            ],
            'category' => [
                'en' => $catNameEn,
                'ar' => $catNameAr,
            ],
            'categorySlug' => $catSlug,
            'publishedAt' => $post->published_at ? $post->published_at->toISOString() : ($post->created_at ? $post->created_at->toISOString() : now()->toISOString()),
            'readTime' => $readMinutes,
            'tags' => ['flowers', 'floral-care', 'luxury-gifting'],
            'seoTitle' => [
                'en' => format_translatable($post->meta_title, 'en') ?: "{$titleEn} | Grass Florist",
                'ar' => format_translatable($post->meta_title, 'ar') ?: "{$titleAr} | غراس فلوريست",
            ],
            'seoDescription' => [
                'en' => format_translatable($post->meta_description, 'en') ?: $shortDescEn,
                'ar' => format_translatable($post->meta_description, 'ar') ?: $shortDescAr,
            ],
            'views_count' => (int) $post->views_count,
            'short_description' => $locale === 'ar' ? $shortDescAr : $shortDescEn,
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $locale = $this->getLocale($request);
        $posts = CmsPost::where('is_active', true)
            ->with('category')
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
        $post = CmsPost::where('is_active', true)
            ->with('category')
            ->where(function ($q) use ($slug) {
                $q->where('slug', $slug)
                  ->orWhere('slug_ar', $slug)
                  ->orWhere('id', $slug);
            })->first();

        if (! $post) {
            return response()->json(['message' => 'Post not found'], 404);
        }

        $post->increment('views_count');

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