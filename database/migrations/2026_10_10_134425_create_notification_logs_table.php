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
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 20)->index(); // 'email', 'sms'
            $table->string('event_key', 100)->nullable()->index(); // e.g., 'contact_us', 'order_confirmed'
            $table->string('recipient')->index(); // email or phone
            $table->string('subject_or_sender')->nullable(); // email subject or SMS sender
            $table->longText('content')->nullable(); // body of message or HTML preview
            $table->string('status', 20)->index(); // 'sent', 'failed'
            $table->text('error_message')->nullable(); // detailed error or exception trace
            $table->json('response_payload')->nullable(); // response from mailer or Msegat API
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
