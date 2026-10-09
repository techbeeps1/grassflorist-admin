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
        // 1. Add dynamic storefront fields and email settings to contact_pages
        Schema::table('contact_pages', function (Blueprint $table) {
            if (!Schema::hasColumn('contact_pages', 'badge')) {
                $table->json('badge')->nullable();
            }
            if (!Schema::hasColumn('contact_pages', 'page_title')) {
                $table->json('page_title')->nullable();
            }
            if (!Schema::hasColumn('contact_pages', 'page_subtitle')) {
                $table->json('page_subtitle')->nullable();
            }
            if (!Schema::hasColumn('contact_pages', 'form_title')) {
                $table->json('form_title')->nullable();
            }
            if (!Schema::hasColumn('contact_pages', 'inquiries_title')) {
                $table->json('inquiries_title')->nullable();
            }
            if (!Schema::hasColumn('contact_pages', 'phone')) {
                $table->string('phone')->nullable();
            }
            if (!Schema::hasColumn('contact_pages', 'whatsapp')) {
                $table->string('whatsapp')->nullable();
            }
            if (!Schema::hasColumn('contact_pages', 'email')) {
                $table->string('email')->nullable();
            }
            if (!Schema::hasColumn('contact_pages', 'working_hours')) {
                $table->json('working_hours')->nullable();
            }
            if (!Schema::hasColumn('contact_pages', 'ateliers_title')) {
                $table->json('ateliers_title')->nullable();
            }
            if (!Schema::hasColumn('contact_pages', 'city')) {
                $table->json('city')->nullable();
            }
            if (!Schema::hasColumn('contact_pages', 'address')) {
                $table->json('address')->nullable();
            }
            if (!Schema::hasColumn('contact_pages', 'notification_email')) {
                $table->string('notification_email')->nullable();
            }
            if (!Schema::hasColumn('contact_pages', 'email_subject')) {
                $table->string('email_subject')->nullable();
            }
        });

        // 2. Create contact_inquiries table to store all form submissions
        if (!Schema::hasTable('contact_inquiries')) {
            Schema::create('contact_inquiries', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email');
                $table->string('phone')->nullable();
                $table->string('subject')->nullable();
                $table->text('message');
                $table->string('ip_address')->nullable();
                $table->string('status')->default('new'); // new, read, replied
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_inquiries');

        Schema::table('contact_pages', function (Blueprint $table) {
            $table->dropColumn([
                'badge',
                'page_title',
                'page_subtitle',
                'form_title',
                'inquiries_title',
                'phone',
                'whatsapp',
                'email',
                'working_hours',
                'ateliers_title',
                'city',
                'address',
                'notification_email',
                'email_subject',
            ]);
        });
    }
};
