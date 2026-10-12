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
        Schema::table('global_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('global_settings', 'email_logo')) {
                $table->string('email_logo')->nullable()->after('site_favicon');
            }
            if (!Schema::hasColumn('global_settings', 'email_header_bg_color')) {
                $table->string('email_header_bg_color', 30)->nullable()->default('#2D3F33');
            }
            if (!Schema::hasColumn('global_settings', 'email_body_bg_color')) {
                $table->string('email_body_bg_color', 30)->nullable()->default('#FAF8F5');
            }
            if (!Schema::hasColumn('global_settings', 'email_card_bg_color')) {
                $table->string('email_card_bg_color', 30)->nullable()->default('#FAF8F5');
            }
            if (!Schema::hasColumn('global_settings', 'email_accent_color')) {
                $table->string('email_accent_color', 30)->nullable()->default('#8fae2a');
            }
            if (!Schema::hasColumn('global_settings', 'email_title_en')) {
                $table->string('email_title_en', 150)->nullable()->default('Grass Florist');
            }
            if (!Schema::hasColumn('global_settings', 'email_title_ar')) {
                $table->string('email_title_ar', 150)->nullable()->default('غراس فلوريست');
            }
            if (!Schema::hasColumn('global_settings', 'email_subtitle_en')) {
                $table->string('email_subtitle_en', 255)->nullable()->default('Luxury Floral Atelier & Curated Gifting');
            }
            if (!Schema::hasColumn('global_settings', 'email_subtitle_ar')) {
                $table->string('email_subtitle_ar', 255)->nullable()->default('متجر الزهور والهدايا الفاخرة');
            }
            if (!Schema::hasColumn('global_settings', 'email_footer_name_en')) {
                $table->string('email_footer_name_en', 200)->nullable()->default('Grass Florist Atelier — Saudi Arabia');
            }
            if (!Schema::hasColumn('global_settings', 'email_footer_name_ar')) {
                $table->string('email_footer_name_ar', 200)->nullable()->default('مؤسسة غراس السعودية للزهور والهدايا');
            }
            if (!Schema::hasColumn('global_settings', 'email_footer_address_en')) {
                $table->string('email_footer_address_en', 255)->nullable()->default('Jeddah, Kingdom of Saudi Arabia');
            }
            if (!Schema::hasColumn('global_settings', 'email_footer_address_ar')) {
                $table->string('email_footer_address_ar', 255)->nullable()->default('جدة، المملكة العربية السعودية');
            }
            if (!Schema::hasColumn('global_settings', 'email_footer_phone')) {
                $table->string('email_footer_phone', 100)->nullable()->default('+966 50 000 0000');
            }
            if (!Schema::hasColumn('global_settings', 'email_footer_email')) {
                $table->string('email_footer_email', 150)->nullable()->default('info@grassflorist.com');
            }
            if (!Schema::hasColumn('global_settings', 'email_footer_copyright_en')) {
                $table->string('email_footer_copyright_en', 255)->nullable();
            }
            if (!Schema::hasColumn('global_settings', 'email_footer_copyright_ar')) {
                $table->string('email_footer_copyright_ar', 255)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('global_settings', function (Blueprint $table) {
            $table->dropColumn([
                'email_logo',
                'email_header_bg_color',
                'email_body_bg_color',
                'email_card_bg_color',
                'email_accent_color',
                'email_title_en',
                'email_title_ar',
                'email_subtitle_en',
                'email_subtitle_ar',
                'email_footer_name_en',
                'email_footer_name_ar',
                'email_footer_address_en',
                'email_footer_address_ar',
                'email_footer_phone',
                'email_footer_email',
                'email_footer_copyright_en',
                'email_footer_copyright_ar',
            ]);
        });
    }
};
