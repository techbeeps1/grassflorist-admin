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
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'invoice_number')) {
                $table->string('invoice_number')->nullable()->after('order_number');
            }
            if (!Schema::hasColumn('orders', 'customer_ip')) {
                $table->string('customer_ip', 60)->nullable()->after('session_id');
            }
            if (!Schema::hasColumn('orders', 'failed_order_reason')) {
                $table->text('failed_order_reason')->nullable()->after('cancellation_reason');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['invoice_number', 'customer_ip', 'failed_order_reason']);
        });
    }
};
