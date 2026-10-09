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
            if (!Schema::hasColumn('home_pages', 'benefits_section')) {
                $table->json('benefits_section')->nullable()->after('banner_image_ar');
            }
            if (!Schema::hasColumn('home_pages', 'events_section')) {
                $table->json('events_section')->nullable()->after('benefits_section');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('home_pages', function (Blueprint $table) {
            $table->dropColumn(['benefits_section', 'events_section']);
        });
    }
};
