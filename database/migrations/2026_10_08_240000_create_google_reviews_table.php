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
        if (!Schema::hasTable('google_reviews')) {
            Schema::create('google_reviews', function (Blueprint $table) {
                $table->id();
                $table->string('google_review_id')->nullable()->unique();
                $table->string('author_name');
                $table->string('author_photo_url')->nullable();
                $table->unsignedTinyInteger('rating')->default(5);
                $table->text('comment')->nullable();
                $table->text('comment_ar')->nullable();
                $table->string('relative_time_description')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->boolean('is_visible')->default(true);
                $table->boolean('is_featured')->default(false);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('global_settings')) {
            Schema::table('global_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('global_settings', 'google_business_url')) {
                    $table->string('google_business_url')->nullable()->default('https://share.google/z7BTvYwJ4iPqWjQTK');
                }
                if (!Schema::hasColumn('global_settings', 'google_place_id')) {
                    $table->string('google_place_id')->nullable();
                }
                if (!Schema::hasColumn('global_settings', 'google_places_api_key')) {
                    $table->string('google_places_api_key')->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('google_reviews');

        if (Schema::hasTable('global_settings')) {
            Schema::table('global_settings', function (Blueprint $table) {
                $table->dropColumn(['google_business_url', 'google_place_id', 'google_places_api_key']);
            });
        }
    }
};
