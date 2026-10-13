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
        Schema::table('subscription_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('subscription_settings', 'subscription_product_ids')) {
                $table->json('subscription_product_ids')->nullable()->after('customer_notes_enabled');
            }
            if (!Schema::hasColumn('subscription_settings', 'duration_discounts')) {
                $table->json('duration_discounts')->nullable()->after('subscription_product_ids');
            }
        });

        // Seed initial default values with top active products if available
        $topProductIds = \Illuminate\Support\Facades\DB::table('products')
            ->where('is_visible', 1)
            ->whereNotNull('image')
            ->limit(3)
            ->pluck('id')
            ->toArray();

        \Illuminate\Support\Facades\DB::table('subscription_settings')->update([
            'subscription_product_ids' => json_encode($topProductIds ?: [1, 2, 3]),
            'duration_discounts' => json_encode([
                '1_month' => 0,
                '3_months' => 10,
                '6_months' => 15,
                '12_months' => 20,
            ]),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_settings', function (Blueprint $table) {
            if (Schema::hasColumn('subscription_settings', 'subscription_product_ids')) {
                $table->dropColumn('subscription_product_ids');
            }
            if (Schema::hasColumn('subscription_settings', 'duration_discounts')) {
                $table->dropColumn('duration_discounts');
            }
        });
    }
};
