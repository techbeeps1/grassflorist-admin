<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactPageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $contactPage = ContactPage::first();

        if (!$contactPage) {
            return response()->json(['message' => 'Contact page not configured'], 404);
        }

        $locale = 'ar';
        if (
            $request->routeIs('*en*') ||
            $request->segment(1) === 'en' ||
            $request->segment(2) === 'en' ||
            $request->query('lang') === 'en' ||
            $request->header('X-Locale') === 'en'
        ) {
            $locale = 'en';
        }

        $badgeEn = format_translatable($contactPage->badge, 'en') ?: 'ALWAYS AT YOUR SERVICE';
        $badgeAr = format_translatable($contactPage->badge, 'ar') ?: 'نحن في خدمتك';

        $titleEn = format_translatable($contactPage->page_title, 'en') ?: (format_translatable($contactPage->con_title, 'en') ?: 'Connect with Our Concierge');
        $titleAr = format_translatable($contactPage->page_title, 'ar') ?: (format_translatable($contactPage->con_title, 'ar') ?: 'تواصل مع خدمة العملاء');

        $subtitleEn = format_translatable($contactPage->page_subtitle, 'en') ?: 'We are at your service for bespoke floral requests, event styling, and delivery inquiries.';
        $subtitleAr = format_translatable($contactPage->page_subtitle, 'ar') ?: 'نحن في خدمتكم لتلبية طلبات الزهور وتنسيق المناسبات والاستفسارات الفاخرة.';

        $formTitleEn = format_translatable($contactPage->form_title, 'en') ?: 'Send an Inquiry';
        $formTitleAr = format_translatable($contactPage->form_title, 'ar') ?: 'أرسل استفسارك';

        $inqTitleEn = format_translatable($contactPage->inquiries_title, 'en') ?: 'DIRECT INQUIRIES';
        $inqTitleAr = format_translatable($contactPage->inquiries_title, 'ar') ?: 'التواصل المباشر';

        $hoursEn = format_translatable($contactPage->working_hours, 'en') ?: 'Daily 9:00 AM - 11:30 PM AST';
        $hoursAr = format_translatable($contactPage->working_hours, 'ar') ?: 'يومياً من 9:00 صباحاً حتى 11:30 مساءً';

        $ateliersTitleEn = format_translatable($contactPage->ateliers_title, 'en') ?: 'BOUTIQUE ATELIERS';
        $ateliersTitleAr = format_translatable($contactPage->ateliers_title, 'ar') ?: 'فروعنا وبوتيكاتنا';

        $cityEn = format_translatable($contactPage->city, 'en') ?: 'Jeddah';
        $cityAr = format_translatable($contactPage->city, 'ar') ?: 'جدة';

        $addressEn = format_translatable($contactPage->address, 'en') ?: (strip_tags(format_translatable($contactPage->con_address, 'en') ?: '4366 Al Kayyal Street, Al-Rawdah District, Jeddah 23434, Saudi Arabia'));
        $addressAr = format_translatable($contactPage->address, 'ar') ?: (strip_tags(format_translatable($contactPage->con_address, 'ar') ?: '٤٣٦٦ شارع الكيال، حي الروضة، جدة ٢٣٤٣٤، المملكة العربية السعودية'));

        $phone = $contactPage->phone ?: ($contactPage->con_phone ?: '+966 55 513 4211');
        $whatsapp = $contactPage->whatsapp ?: ($contactPage->con_phone ?: '+966 55 513 4211');
        $email = $contactPage->email ?: ($contactPage->con_email ?: 'info@grassflorist.com');

        return response()->json([
            'success' => true,
            'locale' => $locale,
            'badge' => $locale === 'ar' ? $badgeAr : $badgeEn,
            'title' => $locale === 'ar' ? $titleAr : $titleEn,
            'subtitle' => $locale === 'ar' ? $subtitleAr : $subtitleEn,
            'form_title' => $locale === 'ar' ? $formTitleAr : $formTitleEn,
            'inquiries_title' => $locale === 'ar' ? $inqTitleAr : $inqTitleEn,
            'phone' => $phone,
            'whatsapp' => $whatsapp,
            'email' => $email,
            'working_hours' => $locale === 'ar' ? $hoursAr : $hoursEn,
            'ateliers_title' => $locale === 'ar' ? $ateliersTitleAr : $ateliersTitleEn,
            'city' => $locale === 'ar' ? $cityAr : $cityEn,
            'address' => $locale === 'ar' ? $addressAr : $addressEn,
            'map_link' => $contactPage->con_map,
            'translations' => [
                'badge' => ['en' => $badgeEn, 'ar' => $badgeAr],
                'title' => ['en' => $titleEn, 'ar' => $titleAr],
                'subtitle' => ['en' => $subtitleEn, 'ar' => $subtitleAr],
                'form_title' => ['en' => $formTitleEn, 'ar' => $formTitleAr],
                'inquiries_title' => ['en' => $inqTitleEn, 'ar' => $inqTitleAr],
                'working_hours' => ['en' => $hoursEn, 'ar' => $hoursAr],
                'ateliers_title' => ['en' => $ateliersTitleEn, 'ar' => $ateliersTitleAr],
                'city' => ['en' => $cityEn, 'ar' => $cityAr],
                'address' => ['en' => $addressEn, 'ar' => $addressAr],
            ],
            'seo' => [
                'meta_title' => format_translatable($contactPage->meta_tag_title, $locale),
                'meta_description' => format_translatable($contactPage->meta_tag_description, $locale),
                'meta_keywords' => format_translatable($contactPage->meta_tag_keywords, $locale),
            ],
            // Backward compatibility
            'contact_detials' => $contactPage,
        ]);
    }
}
