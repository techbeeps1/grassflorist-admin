<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('partner_inquiries')) {
            Schema::create('partner_inquiries', function (Blueprint $table) {
                $table->id();
                $table->string('country');
                $table->string('city');
                $table->string('company_name');
                $table->string('website')->nullable();
                $table->string('category');
                $table->string('social_media')->nullable();
                $table->string('first_name');
                $table->string('last_name')->nullable();
                $table->string('contact_role')->nullable();
                $table->string('email');
                $table->string('country_code')->default('+966');
                $table->string('phone');
                $table->string('company_profile_file')->nullable();
                $table->string('product_list_file')->nullable();
                $table->string('locale')->default('en');
                $table->string('status')->default('new'); // new, under_review, contacted, approved, declined
                $table->text('admin_notes')->nullable();
                $table->string('ip_address')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_inquiries');
    }
};
