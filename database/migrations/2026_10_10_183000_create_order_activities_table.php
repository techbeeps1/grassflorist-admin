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
        // 1. Add order_id to notification_logs if not already present
        if (Schema::hasTable('notification_logs')) {
            Schema::table('notification_logs', function (Blueprint $table) {
                if (!Schema::hasColumn('notification_logs', 'order_id')) {
                    $table->unsignedBigInteger('order_id')->nullable()->after('id')->index();
                }
            });
        }

        // 2. Create order_activities table for tracking order lifecycle and changes
        if (!Schema::hasTable('order_activities')) {
            Schema::create('order_activities', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id')->index();
                $table->string('type', 50)->index(); // 'created', 'paid', 'status_change', 'email', 'sms', 'note', 'refund'
                $table->string('title');
                $table->text('description')->nullable();
                $table->json('properties')->nullable();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('user_name')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();

                $table->foreign('order_id')
                    ->references('id')
                    ->on('orders')
                    ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_activities');

        if (Schema::hasTable('notification_logs') && Schema::hasColumn('notification_logs', 'order_id')) {
            Schema::table('notification_logs', function (Blueprint $table) {
                $table->dropColumn('order_id');
            });
        }
    }
};
