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
            if (!Schema::hasColumn('home_pages', 'banner_title')) {
                $table->json('banner_title')->nullable()->after('banner_description');
            }
            if (!Schema::hasColumn('home_pages', 'banner_image_en')) {
                $table->string('banner_image_en')->nullable()->after('banner_images');
            }
            if (!Schema::hasColumn('home_pages', 'banner_image_ar')) {
                $table->string('banner_image_ar')->nullable()->after('banner_image_en');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('home_pages', function (Blueprint $table) {
            $table->dropColumn(['banner_title', 'banner_image_en', 'banner_image_ar']);
        });
    }
};
