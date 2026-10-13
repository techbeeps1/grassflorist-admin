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
        // 1. Add subscription columns to products table
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'is_subscription_enabled')) {
                $table->boolean('is_subscription_enabled')->default(false)->after('is_visible');
            }
            if (!Schema::hasColumn('products', 'subscription_discount_percentage')) {
                $table->decimal('subscription_discount_percentage', 5, 2)->default(10.00)->after('is_subscription_enabled');
            }
            if (!Schema::hasColumn('products', 'subscription_frequencies')) {
                $table->json('subscription_frequencies')->nullable()->after('subscription_discount_percentage');
            }
            if (!Schema::hasColumn('products', 'subscription_durations')) {
                $table->json('subscription_durations')->nullable()->after('subscription_frequencies');
            }
            if (!Schema::hasColumn('products', 'subscription_min_duration')) {
                $table->string('subscription_min_duration', 50)->nullable()->default('3_months')->after('subscription_durations');
            }
        });

        // 2. Create subscriptions table
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('subscription_number', 50)->unique()->index();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('quantity')->default(1);

            // Frequency & Duration
            $table->string('frequency', 30)->default('weekly'); // weekly, biweekly, monthly, custom
            $table->integer('frequency_days')->default(7);
            $table->string('duration_type', 30)->default('until_cancelled'); // 3_months, 6_months, 12_months, until_cancelled

            // Lifecycle status
            $table->string('status', 30)->default('active')->index(); // active, paused, cancelled, expired, completed
            $table->date('start_date')->index();
            $table->date('end_date')->nullable();
            $table->date('next_delivery_date')->nullable()->index();
            $table->date('next_billing_date')->nullable()->index();
            $table->dateTime('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->date('pause_start_date')->nullable();
            $table->date('pause_resume_date')->nullable();

            // Delivery schedule details
            $table->foreignId('delivery_slot_id')->nullable()->constrained('delivery_slots')->nullOnDelete();
            $table->string('delivery_time_slot')->nullable();
            $table->json('delivery_address')->nullable();
            $table->text('gift_message')->nullable();

            // Financial & Pricing Snapshot (Strict Server Validated)
            $table->decimal('unit_price', 10, 2);
            $table->decimal('discount_percentage', 5, 2)->default(0.00);
            $table->decimal('discounted_price', 10, 2);
            $table->decimal('delivery_fee', 10, 2)->default(0.00);
            $table->decimal('tax_amount', 10, 2)->default(0.00);
            $table->decimal('total_per_delivery', 10, 2);
            $table->string('currency', 10)->default('SAR');

            // Payment Tokenization
            $table->string('payment_gateway', 50)->default('hyperpay'); // hyperpay, paypal, tamara, tabby
            $table->string('payment_token', 255)->nullable()->index();
            $table->json('payment_token_details')->nullable(); // card brand, last4, expiry
            $table->integer('consecutive_failed_payments')->default(0);
            $table->dateTime('last_payment_failed_at')->nullable();
            $table->text('last_payment_error')->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 3. Create subscription_deliveries table
        Schema::create('subscription_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            
            $table->integer('delivery_number')->default(1);
            $table->date('scheduled_date')->index();
            $table->string('time_slot')->nullable();
            
            // Statuses
            $table->string('delivery_status', 30)->default('scheduled')->index(); // scheduled, skipped, rescheduled, processing, out_for_delivery, delivered, failed, cancelled
            $table->string('payment_status', 30)->default('pending')->index(); // pending, paid, failed, refunded
            
            // Reschedule & Skip tracking
            $table->date('rescheduled_from_date')->nullable();
            $table->string('reschedule_reason')->nullable();
            $table->string('skip_reason')->nullable();
            
            // Financial snapshot for this specific delivery
            $table->decimal('unit_price', 10, 2)->default(0.00);
            $table->decimal('discounted_price', 10, 2)->default(0.00);
            $table->decimal('delivery_fee', 10, 2)->default(0.00);
            $table->decimal('total_amount', 10, 2)->default(0.00);
            $table->string('payment_reference')->nullable();
            $table->json('delivery_address')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });

        // 4. Create subscription_activities table (audit trail)
        Schema::create('subscription_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // Admin user
            $table->string('activity_type', 50)->index(); // created, paused, resumed, skipped, rescheduled, address_changed, cancelled, payment_failed, order_generated
            $table->text('description');
            $table->json('properties')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // 5. Add subscription link to orders table
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'subscription_id')) {
                $table->foreignId('subscription_id')->nullable()->after('user_id')->constrained('subscriptions')->nullOnDelete();
            }
            if (!Schema::hasColumn('orders', 'subscription_delivery_id')) {
                $table->foreignId('subscription_delivery_id')->nullable()->after('subscription_id')->constrained('subscription_deliveries')->nullOnDelete();
            }
            if (!Schema::hasColumn('orders', 'is_recurring_order')) {
                $table->boolean('is_recurring_order')->default(false)->after('subscription_delivery_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'subscription_delivery_id')) {
                $table->dropForeign(['subscription_delivery_id']);
                $table->dropColumn('subscription_delivery_id');
            }
            if (Schema::hasColumn('orders', 'subscription_id')) {
                $table->dropForeign(['subscription_id']);
                $table->dropColumn('subscription_id');
            }
            if (Schema::hasColumn('orders', 'is_recurring_order')) {
                $table->dropColumn('is_recurring_order');
            }
        });

        Schema::dropIfExists('subscription_activities');
        Schema::dropIfExists('subscription_deliveries');
        Schema::dropIfExists('subscriptions');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'is_subscription_enabled',
                'subscription_discount_percentage',
                'subscription_frequencies',
                'subscription_durations',
                'subscription_min_duration',
            ]);
        });
    }
};
