<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Menu;

class NavigationMenuSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Header Navigation Menu
        Menu::updateOrCreate(
            ['slug' => 'header'],
            [
                'name' => 'Header Navigation',
                'location' => 'header',
                'is_active' => true,
                'items' => [
                    [
                        'id' => 'home',
                        'name_en' => 'HOME',
                        'name_ar' => 'الرئيسية',
                        'url_en' => '/en',
                        'url_ar' => '/',
                        'has_dropdown' => false,
                        'dropdown_type' => 'simple',
                    ],
                    [
                        'id' => 'all-flowers',
                        'name_en' => 'ALL FLOWERS',
                        'name_ar' => 'جميع الزهور',
                        'url_en' => '/en/category/all-flowers',
                        'url_ar' => '/category/جميع-الزهور',
                        'has_dropdown' => false,
                        'dropdown_type' => 'simple',
                    ],
                    [
                        'id' => 'occasions',
                        'name_en' => 'OCCASIONS',
                        'name_ar' => 'المناسبات',
                        'url_en' => '/en/category/occasions',
                        'url_ar' => '/category/المناسبات',
                        'has_dropdown' => true,
                        'dropdown_type' => 'mega',
                        'showcase_type' => 'category_random_product',
                        'featured_category_id' => 18, // All Occasions
                        'badge_text_en' => 'SAME-DAY DELIVERY',
                        'badge_text_ar' => 'توصيل في نفس اليوم',
                        'cta_text_en' => 'Explore Curated Flowers',
                        'cta_text_ar' => 'تصفح التشكيلة الكاملة',
                        'subcategories' => [
                            ['id' => 'for-mother', 'name_en' => 'For Mother', 'name_ar' => 'للأم', 'url_en' => '/en/category/for-mother', 'url_ar' => '/category/للأم', 'icon' => 'heart'],
                            ['id' => 'birthday', 'name_en' => 'Birthday', 'name_ar' => 'عيد ميلاد', 'url_en' => '/en/category/birthday', 'url_ar' => '/category/عيد-ميلاد', 'icon' => 'cake'],
                            ['id' => 'for-father', 'name_en' => 'For Father', 'name_ar' => 'للآب', 'url_en' => '/en/category/for-father', 'url_ar' => '/category/للآب', 'icon' => 'shield'],
                            ['id' => 'for-her', 'name_en' => 'For Her', 'name_ar' => 'للمرأة', 'url_en' => '/en/category/for-her', 'url_ar' => '/category/للمرأة', 'icon' => 'sparkles'],
                            ['id' => 'for-him', 'name_en' => 'For Him', 'name_ar' => 'للرجل', 'url_en' => '/en/category/for-him', 'url_ar' => '/category/للرجل', 'icon' => 'gift'],
                            ['id' => 'love', 'name_en' => 'Love', 'name_ar' => 'حب', 'url_en' => '/en/category/love', 'url_ar' => '/category/حب', 'icon' => 'heart'],
                            ['id' => 'get-well', 'name_en' => 'Get well', 'name_ar' => 'تمني بالشفاء', 'url_en' => '/en/category/get-well', 'url_ar' => '/category/تمني-بالشفاء', 'icon' => 'sun'],
                            ['id' => 'graduation', 'name_en' => 'Graduation', 'name_ar' => 'تخرج', 'url_en' => '/en/category/graduation', 'url_ar' => '/category/تخرج', 'icon' => 'graduation-cap'],
                            ['id' => 'hand-bouquet', 'name_en' => 'Hand Bouquet', 'name_ar' => 'هاند بوكيه', 'url_en' => '/en/category/hand-bouquet', 'url_ar' => '/category/هاند-بوكيه', 'icon' => 'flower'],
                            ['id' => 'i-am-sorry', 'name_en' => 'I am Sorry', 'name_ar' => 'اعتذار', 'url_en' => '/en/category/i-am-sorry', 'url_ar' => '/category/اعتذار', 'icon' => 'flower'],
                            ['id' => 'new-baby', 'name_en' => 'New Baby', 'name_ar' => 'مولود جديد', 'url_en' => '/en/category/new-baby', 'url_ar' => '/category/مولود-جديد', 'icon' => 'baby'],
                            ['id' => 'new-job', 'name_en' => 'New job and promotion', 'name_ar' => 'وظيفة وترقية', 'url_en' => '/en/category/new-job', 'url_ar' => '/category/وظيفة-وترقية', 'icon' => 'briefcase'],
                        ],
                    ],
                    [
                        'id' => 'luxury-bouquets',
                        'name_en' => 'LUXURY BOUQUETS',
                        'name_ar' => 'باقات فاخرة',
                        'url_en' => '/en/category/luxury-bouquets',
                        'url_ar' => '/category/باقات-فاخرة',
                        'has_dropdown' => false,
                        'dropdown_type' => 'simple',
                    ],
                    [
                        'id' => 'fruits-bouquet',
                        'name_en' => 'FRUITS BOUQUET',
                        'name_ar' => 'باقات الفواكه',
                        'url_en' => '/en/category/fruits-bouquet',
                        'url_ar' => '/category/باقات-الفواكه',
                        'has_dropdown' => false,
                        'dropdown_type' => 'simple',
                    ],
                    [
                        'id' => 'hand-bouquet',
                        'name_en' => 'HAND BOUQUET',
                        'name_ar' => 'هاند بوكيه',
                        'url_en' => '/en/category/hand-bouquet',
                        'url_ar' => '/category/هاند-بوكيه',
                        'has_dropdown' => false,
                        'dropdown_type' => 'simple',
                    ],
                    [
                        'id' => 'cake-chocolate',
                        'name_en' => 'CAKE & CHOCOLATE',
                        'name_ar' => 'كيك وشوكولاته',
                        'url_en' => '/en/category/cake-chocolate',
                        'url_ar' => '/category/كيك-وشوكولاته',
                        'has_dropdown' => true,
                        'dropdown_type' => 'simple',
                        'subcategories' => [
                            ['id' => 'chocolate', 'name_en' => 'Chocolate', 'name_ar' => 'شوكولاته', 'url_en' => '/en/category/chocolate', 'url_ar' => '/category/شوكولاته'],
                            ['id' => 'cake', 'name_en' => 'Cake', 'name_ar' => 'كيك', 'url_en' => '/en/category/cake', 'url_ar' => '/category/كيك'],
                            ['id' => 'chocolate-bouquet', 'name_en' => 'Chocolate Bouquet', 'name_ar' => 'بوكيه شوكولاته', 'url_en' => '/en/category/chocolate-bouquet', 'url_ar' => '/category/بوكيه-شوكولاته'],
                        ],
                    ],
                    [
                        'id' => 'balloons',
                        'name_en' => 'BALLOONS',
                        'name_ar' => 'بالونات',
                        'url_en' => '/en/category/balloons',
                        'url_ar' => '/category/بالونات',
                        'has_dropdown' => true,
                        'dropdown_type' => 'simple',
                        'subcategories' => [
                            ['id' => 'latex-balloons', 'name_en' => 'Latex Balloons', 'name_ar' => 'بالونات مطاطية', 'url_en' => '/en/category/latex-balloons', 'url_ar' => '/category/بالونات-مطاطية'],
                            [
                                'id' => 'letters-balloons',
                                'name_en' => 'Letters Balloons',
                                'name_ar' => 'بالونات الحروف',
                                'url_en' => '/en/category/letters-balloons',
                                'url_ar' => '/category/بالونات-الحروف',
                                'children' => [
                                    ['id' => 'golden-letters', 'name_en' => 'Golden Letters', 'name_ar' => 'أحرف ذهبية', 'url_en' => '/en/category/golden-letters', 'url_ar' => '/category/أحرف-ذهبية'],
                                    ['id' => 'silver-letters', 'name_en' => 'Silver Letters', 'name_ar' => 'أحرف فضية', 'url_en' => '/en/category/silver-letters', 'url_ar' => '/category/أحرف-فضية'],
                                ],
                            ],
                            [
                                'id' => 'numbers-balloons',
                                'name_en' => 'Numbers Balloons',
                                'name_ar' => 'بالونات الأرقام',
                                'url_en' => '/en/category/numbers-balloons',
                                'url_ar' => '/category/بالونات-الأرقام',
                                'children' => [
                                    ['id' => 'golden-numbers', 'name_en' => 'Golden Numbers', 'name_ar' => 'أرقام ذهبية', 'url_en' => '/en/category/golden-numbers', 'url_ar' => '/category/الأرقام-الذهبية'],
                                    ['id' => 'silver-numbers', 'name_en' => 'Silver Numbers', 'name_ar' => 'أرقام فضية', 'url_en' => '/en/category/silver-numbers', 'url_ar' => '/category/أرقام-فضية'],
                                ],
                            ],
                        ],
                    ],
                    [
                        'id' => 'about-grass',
                        'name_en' => 'ABOUT GRASS',
                        'name_ar' => 'عن غراس',
                        'url_en' => '/en/about',
                        'url_ar' => '/عن-غراس',
                        'has_dropdown' => true,
                        'dropdown_type' => 'simple',
                        'subcategories' => [
                            ['id' => 'about-us', 'name_en' => 'About Us', 'name_ar' => 'من نحن', 'url_en' => '/en/about', 'url_ar' => '/عن-غراس'],
                            ['id' => 'event-booking', 'name_en' => 'Event Booking', 'name_ar' => 'حجز وتنظيم مناسبة', 'url_en' => '/en/event-booking', 'url_ar' => '/حجز-مناسبة'],
                            ['id' => 'partner-with-us', 'name_en' => 'Partner With Us', 'name_ar' => 'انضم كشريك معنا', 'url_en' => '/en/partner-with-us', 'url_ar' => '/شارك-معنا'],
                            ['id' => 'delivery-privacy', 'name_en' => 'Delivery & Privacy Policy', 'name_ar' => 'سياسة التوصيل والخصوصية', 'url_en' => '/en/privacy-policy', 'url_ar' => '/privacy-policy'],
                            ['id' => 'refund-returns', 'name_en' => 'Refund and Returns Policy', 'name_ar' => 'سياسة الاسترجاع والاستبدال', 'url_en' => '/en/return-policy', 'url_ar' => '/return-policy'],
                            ['id' => 'contact-us', 'name_en' => 'Contact Us', 'name_ar' => 'اتصل بنا', 'url_en' => '/en/contact', 'url_ar' => '/اتصل-بنا'],
                            ['id' => 'blog', 'name_en' => 'Blog', 'name_ar' => 'المدونة', 'url_en' => '/en/blog', 'url_ar' => '/المدونة'],
                        ],
                    ],
                ],
            ]
        );

        // 2. Footer Navigation Menu
        Menu::updateOrCreate(
            ['slug' => 'footer'],
            [
                'name' => 'Footer Navigation',
                'location' => 'footer',
                'is_active' => true,
                'items' => [
                    'column_1' => [
                        'title_en' => 'CATEGORIES',
                        'title_ar' => 'التصنيفات',
                        'source' => 'categories', // auto populates top categories or custom links
                    ],
                    'column_2' => [
                        'title_en' => 'QUICK LINKS',
                        'title_ar' => 'روابط سريعة',
                        'links' => [
                            ['id' => 'all-products', 'name_en' => 'All Products', 'name_ar' => 'جميع المنتجات', 'url_en' => '/en/products', 'url_ar' => '/products'],
                            ['id' => 'about-us', 'name_en' => 'About Us', 'name_ar' => 'من نحن', 'url_en' => '/en/about', 'url_ar' => '/عن-غراس'],
                            ['id' => 'event-booking', 'name_en' => 'Event & Wedding Booking', 'name_ar' => 'حجز وتنظيم مناسبة', 'url_en' => '/en/event-booking', 'url_ar' => '/حجز-مناسبة'],
                            ['id' => 'partner-with-us', 'name_en' => 'Partner With Us', 'name_ar' => 'انضم كشريك معنا', 'url_en' => '/en/partner-with-us', 'url_ar' => '/شارك-معنا'],
                            ['id' => 'blog', 'name_en' => 'Blog', 'name_ar' => 'المدونة', 'url_en' => '/en/blog', 'url_ar' => '/المدونة'],
                            ['id' => 'contact-us', 'name_en' => 'Contact Us', 'name_ar' => 'اتصل بنا', 'url_en' => '/en/contact', 'url_ar' => '/اتصل-بنا'],
                            ['id' => 'faq', 'name_en' => 'FAQs', 'name_ar' => 'الأسئلة الشائعة', 'url_en' => '/en/faq', 'url_ar' => '/الأسئلة-الشائعة'],
                            ['id' => 'wishlist', 'name_en' => 'Wishlist', 'name_ar' => 'المفضلة', 'url_en' => '/en/wishlist', 'url_ar' => '/المفضلة'],
                        ],
                    ],
                    'column_3' => [
                        'title_en' => 'POLICIES',
                        'title_ar' => 'السياسات',
                        'links' => [
                            ['id' => 'privacy', 'name_en' => 'Privacy Policy', 'name_ar' => 'سياسة الخصوصية', 'url_en' => '/en/privacy-policy', 'url_ar' => '/privacy-policy'],
                            ['id' => 'terms', 'name_en' => 'Terms & Conditions', 'name_ar' => 'الشروط والأحكام', 'url_en' => '/en/terms-conditions', 'url_ar' => '/terms-conditions'],
                            ['id' => 'shipping', 'name_en' => 'Shipping & Delivery Policy', 'name_ar' => 'سياسة الشحن والتوصيل', 'url_en' => '/en/shipping-policy', 'url_ar' => '/shipping-policy'],
                            ['id' => 'returns', 'name_en' => 'Refund & Exchange Policy', 'name_ar' => 'سياسة الاسترجاع والاستبدال', 'url_en' => '/en/return-policy', 'url_ar' => '/return-policy'],
                        ],
                    ],
                ],
                'settings' => [
                    'delivery_city_en' => 'Jeddah',
                    'delivery_city_ar' => 'جدة',
                    'delivery_badge_en' => 'Delivery to',
                    'delivery_badge_ar' => 'التوصيل إلى',
                ],
            ]
        );
    }
}
