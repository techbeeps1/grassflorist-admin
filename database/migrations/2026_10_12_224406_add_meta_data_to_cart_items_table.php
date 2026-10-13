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
        if (Schema::hasTable('cart_items') && !Schema::hasColumn('cart_items', 'meta_data')) {
            Schema::table('cart_items', function (Blueprint $table) {
                $table->json('meta_data')->nullable()->after('product_weight');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('cart_items') && Schema::hasColumn('cart_items', 'meta_data')) {
            Schema::table('cart_items', function (Blueprint $table) {
                $table->dropColumn('meta_data');
            });
        }
    }
};
