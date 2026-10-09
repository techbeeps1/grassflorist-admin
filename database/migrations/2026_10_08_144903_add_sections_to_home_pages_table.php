<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('home_pages', function (Blueprint $table) {
            $table->json('blog_title')->nullable()->after('latest_products_title');
            $table->json('blog_subtitle')->nullable()->after('blog_title');
            $table->json('testimonials_title')->nullable()->after('blog_subtitle');
            $table->json('testimonials_subtitle')->nullable()->after('testimonials_title');
            $table->json('faq_title')->nullable()->after('testimonials_subtitle');
            $table->json('faq_subtitle')->nullable()->after('faq_title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('home_pages', function (Blueprint $table) {
            $table->dropColumn([
                'blog_title',
                'blog_subtitle',
                'testimonials_title',
                'testimonials_subtitle',
                'faq_title',
                'faq_subtitle',
            ]);
        });
    }
};
