<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Production;
use App\Models\CmsPost;
use App\Models\CmsCategory;
use App\Models\CmsPage;


class SitemapController extends Controller
{
    public function index()
    {
        return response()->json([
            'pages' => $this->sitemapItems(CmsPage::where('is_active', 1)),
            'products' => $this->sitemapItems(Product::visibleToCustomers(), '/products'),
            'product_categories' => $this->sitemapItems(Category::where('is_visible', true), '/category'),
            'posts' => $this->sitemapItems(CmsPost::where('is_active', 1), '/blog'),
            'post_categories' => $this->sitemapItems(CmsCategory::class, '/blog/category'),
            'publications' => $this->sitemapItems(Production::where('is_visible', 1), '/publisher'),
        ]);
    }

    private function sitemapItems($source, $prefix = '')
    {
        $query = is_string($source) ? $source::query() : $source;

        return $query->select('slug', 'updated_at')
            ->get()
            ->map(fn ($item) => [
                'url' => $item->slug,
                'updated_at' => $item->updated_at->format('Y-m-d H:i P'),
            ]);
    }
}