<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('orders', 'order_key')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('order_key', 64)->nullable()->index()->after('order_number');
            });
        }

        // Generate WooCommerce-style order_key for all existing orders
        $orders = DB::table('orders')
            ->whereNull('order_key')
            ->orWhere('order_key', '')
            ->get(['id']);

        foreach ($orders as $ord) {
            DB::table('orders')
                ->where('id', $ord->id)
                ->update([
                    'order_key' => 'wc_order_' . Str::random(20),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('orders', 'order_key')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('order_key');
            });
        }
    }
};
