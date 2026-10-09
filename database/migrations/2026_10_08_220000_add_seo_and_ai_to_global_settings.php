<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('global_settings', function (Blueprint $table) {
            // Sitemap Management
            $table->boolean('sitemap_enabled')->default(true)->after('social_tiktok');
            $table->boolean('sitemap_include_products')->default(true)->after('sitemap_enabled');
            $table->boolean('sitemap_include_categories')->default(true)->after('sitemap_include_products');
            $table->boolean('sitemap_include_static_pages')->default(true)->after('sitemap_include_categories');
            $table->boolean('sitemap_include_blog')->default(true)->after('sitemap_include_static_pages');
            $table->text('sitemap_excluded_paths')->nullable()->after('sitemap_include_blog');

            // Robots.txt custom override
            $table->text('robots_custom_content')->nullable()->after('sitemap_excluded_paths');

            // LLMs.txt & AI Discovery
            $table->boolean('llms_enabled')->default(true)->after('robots_custom_content');
            $table->longText('llms_content_en')->nullable()->after('llms_enabled');
            $table->longText('llms_content_ar')->nullable()->after('llms_content_en');
        });
    }

    public function down(): void
    {
        Schema::table('global_settings', function (Blueprint $table) {
            $table->dropColumn([
                'sitemap_enabled',
                'sitemap_include_products',
                'sitemap_include_categories',
                'sitemap_include_static_pages',
                'sitemap_include_blog',
                'sitemap_excluded_paths',
                'robots_custom_content',
                'llms_enabled',
                'llms_content_en',
                'llms_content_ar',
            ]);
        });
    }
};
