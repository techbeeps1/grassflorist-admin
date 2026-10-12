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
        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique()->index();
            $table->string('locale', 10)->default('ar');
            $table->string('coupon_code', 50)->nullable();
            $table->string('status', 20)->default('active'); // active, unsubscribed
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
    }
};
