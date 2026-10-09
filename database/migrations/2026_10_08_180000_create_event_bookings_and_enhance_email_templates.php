<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add notification_emails to email_templates
        if (Schema::hasTable('email_templates')) {
            Schema::table('email_templates', function (Blueprint $table) {
                if (!Schema::hasColumn('email_templates', 'notification_emails')) {
                    $table->text('notification_emails')->nullable()->after('recipient_type');
                }
            });
        }

        // 2. Create event_bookings table
        if (!Schema::hasTable('event_bookings')) {
            Schema::create('event_bookings', function (Blueprint $table) {
                $table->id();
                $table->string('first_name');
                $table->string('last_name')->nullable();
                $table->string('phone');
                $table->string('email');
                $table->string('event_type');
                $table->string('location');
                $table->string('event_date')->nullable();
                $table->string('guests')->nullable();
                $table->text('message')->nullable();
                $table->string('locale')->default('en');
                $table->string('status')->default('new');
                $table->string('ip_address')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('email_templates') && Schema::hasColumn('email_templates', 'notification_emails')) {
            Schema::table('email_templates', function (Blueprint $table) {
                $table->dropColumn('notification_emails');
            });
        }

        Schema::dropIfExists('event_bookings');
    }
};
