<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('global_settings', function (Blueprint $table) {
            // Multilingual Branding
            if (!Schema::hasColumn('global_settings', 'site_name_en')) {
                $table->string('site_name_en')->nullable()->after('site_name');
            }
            if (!Schema::hasColumn('global_settings', 'site_name_ar')) {
                $table->string('site_name_ar')->nullable()->after('site_name_en');
            }
            if (!Schema::hasColumn('global_settings', 'site_tagline_en')) {
                $table->string('site_tagline_en')->nullable()->after('site_tagline');
            }
            if (!Schema::hasColumn('global_settings', 'site_tagline_ar')) {
                $table->string('site_tagline_ar')->nullable()->after('site_tagline_en');
            }

            // Top Announcement Bar
            if (!Schema::hasColumn('global_settings', 'topbar_enabled')) {
                $table->boolean('topbar_enabled')->default(true)->after('site_favicon');
            }
            if (!Schema::hasColumn('global_settings', 'topbar_text_en')) {
                $table->text('topbar_text_en')->nullable()->after('topbar_enabled');
            }
            if (!Schema::hasColumn('global_settings', 'topbar_text_ar')) {
                $table->text('topbar_text_ar')->nullable()->after('topbar_text_en');
            }
            if (!Schema::hasColumn('global_settings', 'topbar_phone')) {
                $table->string('topbar_phone')->nullable()->after('topbar_text_ar');
            }

            // Footer Multilingual Info & About
            if (!Schema::hasColumn('global_settings', 'footer_about_en')) {
                $table->text('footer_about_en')->nullable()->after('footer_copyright');
            }
            if (!Schema::hasColumn('global_settings', 'footer_about_ar')) {
                $table->text('footer_about_ar')->nullable()->after('footer_about_en');
            }
            if (!Schema::hasColumn('global_settings', 'footer_copyright_en')) {
                $table->string('footer_copyright_en')->nullable()->after('footer_about_ar');
            }
            if (!Schema::hasColumn('global_settings', 'footer_copyright_ar')) {
                $table->string('footer_copyright_ar')->nullable()->after('footer_copyright_en');
            }

            // Multilingual Contact Address
            if (!Schema::hasColumn('global_settings', 'contact_address_en')) {
                $table->text('contact_address_en')->nullable()->after('contact_address');
            }
            if (!Schema::hasColumn('global_settings', 'contact_address_ar')) {
                $table->text('contact_address_ar')->nullable()->after('contact_address_en');
            }

            // Additional Social Media Channels
            if (!Schema::hasColumn('global_settings', 'social_snapchat')) {
                $table->string('social_snapchat')->nullable()->after('social_linkedin');
            }
            if (!Schema::hasColumn('global_settings', 'social_tiktok')) {
                $table->string('social_tiktok')->nullable()->after('social_snapchat');
            }
        });

        // Seed initial values for Grass Florist if empty
        DB::table('global_settings')->where('id', 1)->update([
            'site_name' => 'Grass Florist',
            'site_name_en' => 'Grass Florist',
            'site_name_ar' => 'غراس فلوريست',
            'site_tagline_en' => 'Luxury Floral Atelier & Curated Gifting',
            'site_tagline_ar' => 'متجر الزهور والهدايا الفاخرة',
            'topbar_enabled' => true,
            'topbar_text_en' => 'Express Same-Day Delivery in 2 Hours | Free Delivery on Orders Over 250 SAR',
            'topbar_text_ar' => 'توصيل سريع في نفس اليوم خلال ساعتين | شحن مجاني للطلبات فوق 250 ر.س',
            'topbar_phone' => '+966 55 513 4211',
            'contact_phone' => '+966 55 513 4211',
            'contact_whatsapp' => '+966 55 513 4211',
            'contact_email' => 'info@grassflorist.com',
            'contact_address_en' => '4366 Al Kayyal Street, Al-Rawdah District, Jeddah 23434, Saudi Arabia',
            'contact_address_ar' => '4366 شارع الكيال، حي الروضة، جدة 23434، المملكة العربية السعودية',
            'footer_about_en' => 'Grass Florist is a luxury floral atelier and gifting boutique crafting bespoke bouquets, Belgian chocolates, and living plants with same-day express delivery across Saudi Arabia.',
            'footer_about_ar' => 'غراس فلوريست هي بوتيك واستوديو للزهور والهدايا الفاخرة، تقدم باقات منسقة خصيصاً، وشوكولاتة بلجيكية فاخرة، ونباتات داخلية مع توصيل مبرد وسريع في نفس اليوم في المملكة العربية السعودية.',
            'footer_copyright_en' => 'Copyright© 2026, GRASS Florist, All Rights Reserved.',
            'footer_copyright_ar' => 'جميع الحقوق محفوظة © 2026، غراس فلوريست.',
            'social_instagram' => 'https://instagram.com/grassflorist',
            'social_twitter' => 'https://twitter.com/grassflorist',
            'social_snapchat' => 'https://snapchat.com/add/grassflorist',
            'social_facebook' => 'https://facebook.com/grassflorist',
            'social_tiktok' => 'https://tiktok.com/@grassflorist',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('global_settings', function (Blueprint $table) {
            $table->dropColumn([
                'site_name_en',
                'site_name_ar',
                'site_tagline_en',
                'site_tagline_ar',
                'topbar_enabled',
                'topbar_text_en',
                'topbar_text_ar',
                'topbar_phone',
                'footer_about_en',
                'footer_about_ar',
                'footer_copyright_en',
                'footer_copyright_ar',
                'contact_address_en',
                'contact_address_ar',
                'social_snapchat',
                'social_tiktok',
            ]);
        });
    }
};
