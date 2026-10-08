<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\Testimonial;
use Illuminate\Database\Seeder;

class TestimonialAndFaqSeeder extends Seeder
{
    /**
     * Seed initial Testimonials and FAQs matching the storefront UI design.
     */
    public function run(): void
    {
        // 1. Testimonials
        $testimonials = [
            [
                'author_name' => [
                    'en' => 'Sara Al-Dossary',
                    'ar' => 'سارة الدوسري',
                ],
                'city' => [
                    'en' => 'Riyadh',
                    'ar' => 'الرياض',
                ],
                'occasion_tag' => [
                    'en' => 'Wedding Anniversary',
                    'ar' => 'ذكرى زواج',
                ],
                'content' => [
                    'en' => 'Ordered the Royal Crimson roses for my anniversary. Delivered in a chilled vehicle in under two hours. The handwritten card calligraphy and presentation were beyond exquisite.',
                    'ar' => 'طلبت باقة ورد رويال كريمسون بمناسبة ذكرى زواجي. تم التوصيل في مركبة مبردة خلال أقل من ساعتين. الخط اليدوي على بطاقة الإهداء والتقديم كان في غاية الفخامة.',
                ],
                'rating' => 5.0,
                'avatar' => null,
                'is_verified' => true,
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'author_name' => [
                    'en' => 'Abdullah Al-Subaie',
                    'ar' => 'عبد الله السبيعي',
                ],
                'city' => [
                    'en' => 'Jeddah',
                    'ar' => 'جدة',
                ],
                'occasion_tag' => [
                    'en' => 'Birthday',
                    'ar' => 'عيد ميلاد',
                ],
                'content' => [
                    'en' => 'Undoubtedly the premier online floral experience in Saudi Arabia. The Belgian chocolates were decadent and the roses stayed vibrant for over eight days at home.',
                    'ar' => 'بلا شك أفضل تجربة لطلب الورود أونلاين في المملكة العربية السعودية. الشوكولاتة البلجيكية كانت لذيذة للغاية والورد ظل نضراً لأكثر من ثمانية أيام في المنزل.',
                ],
                'rating' => 5.0,
                'avatar' => null,
                'is_verified' => true,
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'author_name' => [
                    'en' => 'Reem Al-Qahtani',
                    'ar' => 'ريم القحطاني',
                ],
                'city' => [
                    'en' => 'Khobar',
                    'ar' => 'الخبر',
                ],
                'occasion_tag' => [
                    'en' => 'Clinic Opening & Congratulations',
                    'ar' => 'افتتاح عيادة وتهنئة',
                ],
                'content' => [
                    'en' => "Sent the White Orchid for my friend's clinic opening. It was the centerpiece of admiration. Thank you Grass Florist for your unmatched sophistication.",
                    'ar' => 'أرسلت أوركيد بيضاء لافتتاح عيادة صديقتي. كانت محط إعجاب الجميع. شكراً غراس فلوريست على الرقي والتميز اللامحدود.',
                ],
                'rating' => 5.0,
                'avatar' => null,
                'is_verified' => true,
                'sort_order' => 3,
                'is_active' => true,
            ],
        ];

        foreach ($testimonials as $data) {
            Testimonial::updateOrCreate(
                ['author_name->en' => $data['author_name']['en']],
                $data
            );
        }

        // 2. FAQs
        $faqs = [
            [
                'category' => [
                    'en' => 'Ordering & Delivery',
                    'ar' => 'الطلب والتوصيل',
                ],
                'question' => [
                    'en' => 'How long does it take to deliver the order?',
                    'ar' => 'كم يستغرق توصيل الطلب؟',
                ],
                'answer' => [
                    'en' => 'We offer a fast, same-day delivery service within two to three hours of order confirmation; you can also schedule your order by selecting a specific date and time that suits you or the recipient.',
                    'ar' => 'نقدم خدمة توصيل سريعة في نفس اليوم خلال ساعتين إلى ثلاث ساعات من تأكيد الطلب؛ كما يمكنك جدولة طلبك باختيار التاريخ والوقت المناسب لك أو للمستلم.',
                ],
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'category' => [
                    'en' => 'Ordering & Delivery',
                    'ar' => 'الطلب والتوصيل',
                ],
                'question' => [
                    'en' => 'Can I send a surprise gift if I do not have the recipient full address?',
                    'ar' => 'هل يمكنني إرسال هدية مفاجئة إذا لم يكن لدي عنوان المستلم بالكامل؟',
                ],
                'answer' => [
                    'en' => "Yes, simply provide the recipient's phone number during checkout, and our delivery team will coordinate with them discreetly to obtain their preferred delivery location.",
                    'ar' => 'نعم، كل ما عليك هو تزويدنا برقم هاتف المستلم أثناء إتمام الطلب، وسيقوم فريق التوصيل بالتواصل معه بسرية لتحديد موقعه المفضل.',
                ],
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'category' => [
                    'en' => 'Special Services',
                    'ar' => 'خدمات خاصة',
                ],
                'question' => [
                    'en' => 'Do you deliver directly to hospitals, luxury hotels, and event venues?',
                    'ar' => 'هل تقومون بالتوصيل مباشرة إلى المستشفيات والفنادق الفاخرة وقاعات المناسبات؟',
                ],
                'answer' => [
                    'en' => 'Absolutely. We deliver to VIP hospital suites, hotel concierge desks, and wedding venues with utmost care and luxury presentation.',
                    'ar' => 'بكل تأكيد. نقوم بالتوصيل إلى أجنحة المستشفيات، واستقبال الفنادق، وقاعات الأفراح والمناسبات بأعلى معايير العناية والفخامة.',
                ],
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'category' => [
                    'en' => 'Gifting & Privacy',
                    'ar' => 'الإهداء والخصوصية',
                ],
                'question' => [
                    'en' => 'Can I send the gift anonymously without revealing my name?',
                    'ar' => 'هل يمكنني إرسال الهدية دون الكشف عن هويتي؟',
                ],
                'answer' => [
                    'en' => 'Yes, we respect your privacy. You can choose to keep your name confidential, and the recipient will only see the card message you provide.',
                    'ar' => 'نعم، نحترم خصوصيتك التامة. يمكنك اختيار إبقاء اسمك سرياً ولن يظهر للمستلم سوى رسالة الإهداء المكتوبة في البطاقة.',
                ],
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'category' => [
                    'en' => 'Order Changes',
                    'ar' => 'تعديل الطلبات',
                ],
                'question' => [
                    'en' => 'Can I modify or cancel my order after confirmation?',
                    'ar' => 'هل يمكنني تعديل أو إلغاء طلبي بعد التأكيد؟',
                ],
                'answer' => [
                    'en' => 'You can modify or cancel your order up to one hour before the designated preparation and dispatch time by contacting our customer concierge.',
                    'ar' => 'يمكنك تعديل أو إلغاء طلبك حتى ساعة واحدة قبل موعد تجهيز الباقة وانطلاق المندوب من خلال التواصل مع خدمة العملاء.',
                ],
                'sort_order' => 5,
                'is_active' => true,
            ],
        ];

        foreach ($faqs as $data) {
            Faq::updateOrCreate(
                ['question->en' => $data['question']['en']],
                $data
            );
        }
    }
}
