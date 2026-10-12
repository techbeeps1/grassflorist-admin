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
        Schema::create('sms_templates', function (Blueprint $table) {
            $table->id();
            $table->string('event_key')->unique();
            $table->string('name');
            $table->text('message_en');
            $table->text('message_ar');
            $table->string('recipient_type')->default('customer'); // customer, admin, both
            $table->string('notification_phones')->nullable(); // comma-separated admin phone numbers
            $table->text('allowed_shortcodes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_templates');
    }
};
