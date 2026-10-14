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
            $table->boolean('cart_upsell_enabled')->default(true)->after('topbar_phone');
            $table->string('cart_upsell_title_en')->nullable()->default('Make it perfect')->after('cart_upsell_enabled');
            $table->string('cart_upsell_title_ar')->nullable()->default('اجعلها مثالية')->after('cart_upsell_title_en');
            $table->json('cart_upsell_category_ids')->nullable()->after('cart_upsell_title_ar');
            $table->integer('cart_upsell_limit_per_category')->default(10)->after('cart_upsell_category_ids');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('global_settings', function (Blueprint $table) {
            $table->dropColumn([
                'cart_upsell_enabled',
                'cart_upsell_title_en',
                'cart_upsell_title_ar',
                'cart_upsell_category_ids',
                'cart_upsell_limit_per_category',
            ]);
        });
    }
};
