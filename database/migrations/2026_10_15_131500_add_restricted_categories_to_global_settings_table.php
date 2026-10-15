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
        Schema::table('global_settings', function (Blueprint $table) {
            $table->boolean('restricted_categories_enabled')->default(true)->after('cart_upsell_limit_per_category');
            $table->json('restricted_category_ids')->nullable()->after('restricted_categories_enabled');
            $table->json('qualifying_category_ids')->nullable()->after('restricted_category_ids');
            $table->text('restricted_categories_message_en')->nullable()->after('qualifying_category_ids');
            $table->text('restricted_categories_message_ar')->nullable()->after('restricted_categories_message_en');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('global_settings', function (Blueprint $table) {
            $table->dropColumn([
                'restricted_categories_enabled',
                'restricted_category_ids',
                'qualifying_category_ids',
                'restricted_categories_message_en',
                'restricted_categories_message_ar',
            ]);
        });
    }
};
