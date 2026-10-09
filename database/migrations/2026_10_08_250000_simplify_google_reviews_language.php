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
        Schema::table('google_reviews', function (Blueprint $table) {
            if (!Schema::hasColumn('google_reviews', 'language')) {
                $table->string('language', 10)->default('ar')->after('comment');
            }
            if (Schema::hasColumn('google_reviews', 'comment_ar')) {
                $table->dropColumn('comment_ar');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('google_reviews', function (Blueprint $table) {
            if (!Schema::hasColumn('google_reviews', 'comment_ar')) {
                $table->text('comment_ar')->nullable()->after('comment');
            }
            if (Schema::hasColumn('google_reviews', 'language')) {
                $table->dropColumn('language');
            }
        });
    }
};
