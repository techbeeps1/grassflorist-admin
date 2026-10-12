<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 0. Ensure id is AUTO_INCREMENT
        try {
            DB::statement('ALTER TABLE `shipping_methods` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        } catch (\Throwable $e) {}

        // 1. Modify columns in shipping_methods table for JSON translations and city
        Schema::table('shipping_methods', function (Blueprint $table) {
            // Modify text lengths for multi-lang JSON storage
            if (Schema::hasColumn('shipping_methods', 'name')) {
                $table->text('name')->change();
            }
            if (Schema::hasColumn('shipping_methods', 'delivery_time')) {
                $table->text('delivery_time')->nullable()->change();
            }
            if (Schema::hasColumn('shipping_methods', 'description')) {
                $table->text('description')->nullable()->change();
            }
            if (!Schema::hasColumn('shipping_methods', 'city')) {
                $table->string('city', 100)->nullable()->default('Jeddah')->after('description');
            }
            if (!Schema::hasColumn('shipping_methods', 'min_order_for_free_shipping')) {
                $table->decimal('min_order_for_free_shipping', 10, 2)->nullable()->after('is_free_shipping_eligible');
            }
        });

        // 2. Seed / Update the Inside Jeddah Delivery method with SAR 30
        DB::table('shipping_methods')->updateOrInsert(
            ['code' => 'inside_jeddah'],
            [
                'name' => json_encode([
                    'en' => 'Delivery Inside Jeddah',
                    'ar' => 'التوصيل داخل جدة',
                ], JSON_UNESCAPED_UNICODE),
                'delivery_time' => json_encode([
                    'en' => 'Same-Day Delivery (2 - 4 Hours)',
                    'ar' => 'توصيل خلال نفس اليوم (2 - 4 ساعات)',
                ], JSON_UNESCAPED_UNICODE),
                'description' => json_encode([
                    'en' => 'Refrigerated luxury floral delivery across all districts inside Jeddah.',
                    'ar' => 'توصيل مبرد وسريع لكافة أحياء ومناطق مدينة جدة.',
                ], JSON_UNESCAPED_UNICODE),
                'city' => 'Jeddah',
                'price' => 30.00,
                'calculation_type' => 'flat_rate',
                'is_free_shipping_eligible' => true,
                'min_order_for_free_shipping' => 250.00,
                'is_cod_allowed' => true,
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // 3. Update existing standard code if present to also point to Jeddah 30 SAR
        if (DB::table('shipping_methods')->where('code', 'standard')->exists()) {
            DB::table('shipping_methods')->where('code', 'standard')->update([
                'name' => json_encode([
                    'en' => 'Delivery Inside Jeddah',
                    'ar' => 'التوصيل داخل جدة',
                ], JSON_UNESCAPED_UNICODE),
                'delivery_time' => json_encode([
                    'en' => 'Same-Day Delivery (2 - 4 Hours)',
                    'ar' => 'توصيل خلال نفس اليوم (2 - 4 ساعات)',
                ], JSON_UNESCAPED_UNICODE),
                'price' => 30.00,
                'city' => 'Jeddah',
                'calculation_type' => 'flat_rate',
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Safe down
    }
};
