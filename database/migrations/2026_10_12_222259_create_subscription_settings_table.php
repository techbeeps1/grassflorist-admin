<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('subscription_settings')) {
            Schema::create('subscription_settings', function (Blueprint $table) {
                $table->id();
                $table->boolean('is_enabled')->default(true);
                $table->decimal('default_discount_percentage', 5, 2)->default(10.00);
                $table->json('allowed_frequencies')->nullable();
                $table->string('duration_mode', 50)->default('fixed'); // 'fixed' or 'customer_choice'
                $table->string('fixed_duration', 50)->default('until_cancelled'); // 'until_cancelled', '3_months', '6_months', '12_months'
                $table->json('allowed_durations')->nullable();
                $table->boolean('customer_notes_enabled')->default(true);
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });

            // Insert initial default setting record
            DB::table('subscription_settings')->insert([
                'id' => 1,
                'is_enabled' => true,
                'default_discount_percentage' => 10.00,
                'allowed_frequencies' => json_encode(['weekly', 'biweekly', 'monthly']),
                'duration_mode' => 'fixed',
                'fixed_duration' => 'until_cancelled',
                'allowed_durations' => json_encode(['3_months', '6_months', '12_months', 'until_cancelled']),
                'customer_notes_enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 2. Ensure all products have subscriptions enabled by default
        if (Schema::hasColumn('products', 'is_subscription_enabled')) {
            // Set default value to true on products table
            try {
                Schema::table('products', function (Blueprint $table) {
                    $table->boolean('is_subscription_enabled')->default(true)->change();
                });
            } catch (\Throwable $e) {
                // Some DB engines require doctrine/dbal for change(), fallback safely
            }

            // Enable subscriptions for all existing products right now
            DB::table('products')->update(['is_subscription_enabled' => true]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_settings');
    }
};
