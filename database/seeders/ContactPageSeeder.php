<?php

namespace Database\Seeders;

use App\Models\ContactPage;
use Illuminate\Database\Seeder;

class ContactPageSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'badge' => [
                'en' => 'ALWAYS AT YOUR SERVICE',
                'ar' => 'نحن في خدمتك',
            ],
            'page_title' => [
                'en' => 'Connect with Our Concierge',
                'ar' => 'تواصل مع خدمة العملاء',
            ],
            'page_subtitle' => [
                'en' => 'We are at your service for bespoke floral requests, event styling, and delivery inquiries.',
                'ar' => 'نحن في خدمتكم لتلبية طلبات الزهور وتنسيق المناسبات والاستفسارات الفاخرة.',
            ],
            'form_title' => [
                'en' => 'Send an Inquiry',
                'ar' => 'أرسل استفسارك',
            ],
            'inquiries_title' => [
                'en' => 'DIRECT INQUIRIES',
                'ar' => 'التواصل المباشر',
            ],
            'phone' => '+966 55 513 4211',
            'whatsapp' => '+966 55 513 4211',
            'email' => 'info@grassflorist.com',
            'working_hours' => [
                'en' => 'Daily 9:00 AM - 11:30 PM AST',
                'ar' => 'يومياً من 9:00 صباحاً حتى 11:30 مساءً',
            ],
            'ateliers_title' => [
                'en' => 'BOUTIQUE ATELIERS',
                'ar' => 'فروعنا وبوتيكاتنا',
            ],
            'city' => [
                'en' => 'Jeddah',
                'ar' => 'جدة',
            ],
            'address' => [
                'en' => '4366 Al Kayyal Street, Al-Rawdah District, Jeddah 23434, Saudi Arabia',
                'ar' => '٤٣٦٦ شارع الكيال، حي الروضة، جدة ٢٣٤٣٤، المملكة العربية السعودية',
            ],
            'notification_email' => 'info@grassflorist.com',
            'email_subject' => 'New Contact Inquiry from Grass Florist Website',
            'con_title' => [
                'en' => 'Connect with Our Concierge',
                'ar' => 'تواصل مع خدمة العملاء',
            ],
            'con_address' => [
                'en' => '4366 Al Kayyal Street, Al-Rawdah District, Jeddah 23434, Saudi Arabia',
                'ar' => '٤٣٦٦ شارع الكيال، حي الروضة، جدة ٢٣٤٣٤، المملكة العربية السعودية',
            ],
            'con_phone' => '+966 55 513 4211',
            'con_email' => 'info@grassflorist.com',
            'meta_tag_title' => [
                'en' => 'Contact Us | Grass Florist Luxury Flowers',
                'ar' => 'اتصل بنا | غراس فلوريست للزهور الفاخرة',
            ],
            'meta_tag_description' => [
                'en' => 'Connect with our concierge for bespoke floral requests, event styling, and refrigerated delivery inquiries.',
                'ar' => 'تواصل مع خدمة عملاء غراس فلوريست للطلبات المخصصة وتنسيق المناسبات والتوصيل الفوري.',
            ],
        ];

        $page = ContactPage::first();
        if ($page) {
            $page->update($data);
        } else {
            ContactPage::create($data);
        }
    }
}
