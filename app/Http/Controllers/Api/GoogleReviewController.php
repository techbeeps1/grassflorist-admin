<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GoogleReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoogleReviewController extends Controller
{
    public const DEFAULT_BUSINESS_URL = 'https://share.google/z7BTvYwJ4iPqWjQTK';

    public function index(Request $request): JsonResponse
    {
        $limit = max(1, min(50, (int) $request->input('limit', 10)));
        $random = $request->boolean('random', false);

        $query = GoogleReview::query()->where('is_visible', true);

        if ($random) {
            $query->inRandomOrder();
        } else {
            $query->orderBy('sort_order', 'asc')->orderBy('id', 'desc');
        }

        $reviews = $query->take($limit)->get();

        $totalCount = GoogleReview::where('is_visible', true)->count();
        $avgRating = GoogleReview::where('is_visible', true)->avg('rating') ?: 5.0;

        $setting = \App\Models\GlobalSetting::current();
        $googleBusinessUrl = $setting->google_business_url ?: self::DEFAULT_BUSINESS_URL;
        $writeReviewUrl = $setting->google_write_review_url ?: $googleBusinessUrl;

        return response()->json([
            'success' => true,
            'data' => $reviews->map(function ($rev) {
                return [
                    'id' => $rev->id,
                    'author_name' => $rev->author_name,
                    'author_photo_url' => $rev->avatar_url,
                    'rating' => $rev->rating,
                    'comment' => $rev->comment,
                    'language' => $rev->language ?: 'ar',
                    'relative_time_description' => $rev->relative_time_description,
                    'published_at' => $rev->published_at ? $rev->published_at->format('Y-m-d') : null,
                    'is_featured' => (bool) $rev->is_featured,
                    'source' => 'google',
                ];
            }),
            'meta' => [
                'total_reviews' => $totalCount,
                'average_rating' => round((float) $avgRating, 1),
                'google_business_url' => $googleBusinessUrl,
                'write_review_url' => $writeReviewUrl,
            ],
        ]);
    }
}
