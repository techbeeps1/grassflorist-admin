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

        // 2. FAQs (12 complete storefront questions matching /faq page)
        $faqs = [
            // 1. DELIVERY
            [
                'category' => [
                    'en' => 'Delivery',
                    'ar' => 'التوصيل والجدولة',
                ],
                'question' => [
                    'en' => 'How fast is express delivery in Riyadh, Jeddah, and Khobar?',
                    'ar' => 'كم يستغرق توصيل الطلب في الرياض وجدة والخبر؟',
                ],
                'answer' => [
                    'en' => 'We offer express same-day refrigerated delivery within 2 to 3 hours of order confirmation across major metropolitan areas (Riyadh, Jeddah, Khobar, and Dammam). You can also schedule advance delivery for any specific date and time slot.',
                    'ar' => 'نوفر خدمة التوصيل الفوري السريع في نفس اليوم خلال ساعتين إلى ثلاث ساعات من تأكيد الطلب للطلبات داخل المدن الرئيسية (الرياض، جدة، الخبر، الدمام). كما يمكنك جدولة الطلب واختيار موعد وتاريخ محدد يناسبك أو يناسب المستلم.',
                ],
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'category' => [
                    'en' => 'Delivery',
                    'ar' => 'التوصيل والجدولة',
                ],
                'question' => [
                    'en' => 'How do you preserve floral freshness during hot summer weather?',
                    'ar' => 'كيف تضمنون وصول الزهور طازجة وغير ذابلة في حرارة الصيف؟',
                ],
                'answer' => [
                    'en' => 'Our entire delivery fleet is custom-fitted with temperature-regulated climate control (16–18°C). Stems are hydrated with individual water reservoirs ensuring zero wilting between our atelier and the recipient doorstep.',
                    'ar' => 'نمتلك أسطولاً خاصاً من سيارات النقل المجهزة بنظام تبريد حراري مدروس (درجة حرارة 16-18 مئوية)، كما يتم تزويد سيقان الزهور بكبسولات ترطيب مائية تحافظ على امتصاص الماء حتى لحظة التسليم لباب العميل أو المستلم.',
                ],
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'category' => [
                    'en' => 'Delivery',
                    'ar' => 'التوصيل والجدولة',
                ],
                'question' => [
                    'en' => 'Can I send a surprise gift if I do not have the recipient full address?',
                    'ar' => 'هل يمكنني إرسال الزهور كهدية مفاجئة دون معرفة العنوان الدقيق للمستلم؟',
                ],
                'answer' => [
                    'en' => 'Yes! Simply provide the recipient’s name and mobile number. Our concierge will discreetly coordinate delivery location and timing via WhatsApp while keeping the nature of the gift a pleasant surprise.',
                    'ar' => 'نعم بكل تأكيد! يكفيك تزويدنا باسم المستلم ورقم جواله، وسيقوم فريق خدمة العملاء اللبق بالتواصل معه عبر الواتساب لتحديد الموقع الجغرافي المناسب وموعد الاستلام دون إفساد عنصر المفاجأة أو ذكر تفاصيل الهدية.',
                ],
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'category' => [
                    'en' => 'Delivery',
                    'ar' => 'التوصيل والجدولة',
                ],
                'question' => [
                    'en' => 'Do you deliver directly to hospitals, luxury hotels, and event venues?',
                    'ar' => 'هل يتوفر التوصيل إلى المستشفيات والفنادق وقاعات الاحتفالات؟',
                ],
                'answer' => [
                    'en' => 'Yes, we deliver directly to hospital patient rooms, luxury hotel concierges, corporate towers, and wedding venues. Please include room numbers or suite details in checkout notes.',
                    'ar' => 'نعم، نقوم بالتوصيل المباشر إلى غرف المستشفيات وأجنحة الفنادق وصالات الأفراح والمكاتب التنفيذية. يرجى تزويدنا برقم الغرفة أو اسم الجناح واسم المستلم عند إتمام الطلب.',
                ],
                'sort_order' => 4,
                'is_active' => true,
            ],

            // 2. ORDERING & CUSTOMIZATION
            [
                'category' => [
                    'en' => 'Ordering & Customization',
                    'ar' => 'الطلب والتخصيص',
                ],
                'question' => [
                    'en' => 'Can I include a personalized handwritten greeting card?',
                    'ar' => 'هل يمكنني إضافة رسالة إهداء خاصة مكتوبة بخط اليد؟',
                ],
                'answer' => [
                    'en' => 'Complimentary bespoke greeting cards sealed with artisan wax are included with every order. Type your heartfelt words at checkout, and our calligrapher will handwrite them prior to delivery.',
                    'ar' => 'نعم، نوفر مع كل باقة أو هدية بطاقة إهداء فاخرة مجانية مختومة بختم الشمع. يمكنك كتابة رسالتك أثناء إتمام الطلب وسيقوم خطاط محترف بكتابتها بخط أنيق قبل تسليم الهدية.',
                ],
                'sort_order' => 5,
                'is_active' => true,
            ],
            [
                'category' => [
                    'en' => 'Ordering & Customization',
                    'ar' => 'الطلب والتخصيص',
                ],
                'question' => [
                    'en' => 'Is it possible to send a gift completely anonymously?',
                    'ar' => 'هل يمكن إرسال الهدية كمجهول الهوية دون الكشف عن اسمي؟',
                ],
                'answer' => [
                    'en' => 'Yes, you can toggle the "Send Anonymously" option at checkout. We strictly protect sender identity and will never disclose your details to the recipient.',
                    'ar' => 'نعم، يتوفر خيار "إرسال كمجهول" عند صفحة الدفع. في هذه الحالة لن يتم ذكر اسم المرسل أو رقم هاتفه للمستلم، ونلتزم بالسرية التامة للبيانات وفق سياسة الخصوصية.',
                ],
                'sort_order' => 6,
                'is_active' => true,
            ],
            [
                'category' => [
                    'en' => 'Ordering & Customization',
                    'ar' => 'الطلب والتخصيص',
                ],
                'question' => [
                    'en' => 'Can I modify or cancel my order after confirmation?',
                    'ar' => 'هل يمكنني تعديل أو إلغاء الطلب بعد تأكيده؟',
                ],
                'answer' => [
                    'en' => 'Modifications to delivery details, card text, or cancellations are accepted as long as the order has not entered physical floral arrangement (within 30 minutes of placement).',
                    'ar' => 'يمكنك تعديل بيانات التوصيل أو نص بطاقة الإهداء أو إلغاء الطلب واسترداد المبلغ بالكامل ما دام الطلب في مرحلة "قيد الانتظار" ولم يبدأ خبير التنسيق بتجهيز وقص الزهور (خلال 30 دقيقة من الطلب).',
                ],
                'sort_order' => 7,
                'is_active' => true,
            ],

            // 3. FLOWER CARE
            [
                'category' => [
                    'en' => 'Floral Care',
                    'ar' => 'العناية بالزهور',
                ],
                'question' => [
                    'en' => 'How do I care for my fresh flower bouquet to prolong its lifespan?',
                    'ar' => 'كيف أعتني بباقة الورد لتدوم أطول فترة ممكنة؟',
                ],
                'answer' => [
                    'en' => 'Trim stems diagonally at a 45-degree angle every 2 days, replenish fresh cold water enriched with the provided floral preservative packet, and position away from direct drafts and sunlight.',
                    'ar' => 'قص أطراف السيقان بزاوية 45 درجة بمقدار 2 سم كل يومين، ضع الزهور في ماء بارد ونظيف مع إضافة غذاء الزهور المرفق، وأبعد الفازة عن أشعة الشمس المباشرة ومصادر التكييف الحار أو البارد المباشر.',
                ],
                'sort_order' => 8,
                'is_active' => true,
            ],
            [
                'category' => [
                    'en' => 'Floral Care',
                    'ar' => 'العناية بالزهور',
                ],
                'question' => [
                    'en' => 'What are preserved forever roses and how long do they last?',
                    'ar' => 'ما هو الورد الدائم وكم تدوم مدة بقائه؟',
                ],
                'answer' => [
                    'en' => 'Preserved roses are 100% natural flowers harvested at peak bloom, treated with an eco-friendly biological preservation formula that retains soft petal texture and vibrancy for 1 to 3 years without watering.',
                    'ar' => 'الورد الدائم هو ورد طبيعي 100% تم قطفه في قمة تفتحه واستبدال عصارة النبات الطبيعية بمحلول عضوي غير سام يحافظ على ملمس البتلات ولونها الطبيعي لمدة تتراوح بين سنة إلى ثلاث سنوات دون الحاجة لماء أو شمس.',
                ],
                'sort_order' => 9,
                'is_active' => true,
            ],
            [
                'category' => [
                    'en' => 'Floral Care',
                    'ar' => 'العناية بالزهور',
                ],
                'question' => [
                    'en' => 'How do I care for indoor plants such as Peace Lilies and Monsteras?',
                    'ar' => 'كيف أعتني بنباتات الظل الداخلية مثل زنبق السلام والمونستيرا؟',
                ],
                'answer' => [
                    'en' => 'Place in medium-to-bright indirect ambient light. Water thoroughly only when the top 2-3 cm of soil feels dry (typically once every 7–10 days). Wipe broad leaves with a damp cloth occasionally.',
                    'ar' => 'تحتاج هذه النباتات إلى إضاءة ساطعة ولكن غير مباشرة، وري معتدل عند جفاف السطح العلوي للتربة بمقدار 2-3 سم (مرة كل أسبوع إلى 10 أيام)، مع مسح أوراقها برفق بقطعة قماش مبللة لإزالة الغبار.',
                ],
                'sort_order' => 10,
                'is_active' => true,
            ],

            // 4. PAYMENTS & GUARANTEE
            [
                'category' => [
                    'en' => 'Payments & Guarantee',
                    'ar' => 'الدفع والضمان',
                ],
                'question' => [
                    'en' => 'What payment methods are supported on Grass Florist?',
                    'ar' => 'ما هي طرق الدفع المتاحة على متجر غراس فلوريست؟',
                ],
                'answer' => [
                    'en' => 'We accept Mada, Apple Pay, Visa, MasterCard, Tabby (split into 4 interest-free installments), and Cash on Delivery.',
                    'ar' => 'نقبل جميع وسائل الدفع الإلكترونية الآمنة: مدى (Mada)، أبل باي (Apple Pay)، فيزا وماستركارد، وخيار التقسيط عبر تابي (Tabby) على 4 دفعات ميسرة بدون أي فوائد، بالإضافة للدفع عند الاستلام.',
                ],
                'sort_order' => 11,
                'is_active' => true,
            ],
            [
                'category' => [
                    'en' => 'Payments & Guarantee',
                    'ar' => 'الدفع والضمان',
                ],
                'question' => [
                    'en' => 'What is your 7-day freshness guarantee policy?',
                    'ar' => 'ما هو ضمان النضارة لمدة 7 أيام وكيف يعمل؟',
                ],
                'answer' => [
                    'en' => 'We pride ourselves on farm-direct blooms. If your arrangement wilts prematurely within 7 days despite proper care, we will gladly replace it free of charge or issue a full refund.',
                    'ar' => 'نحن واثقون تماماً من جودة زهورنا المستوردة مباشرة من المزارع. إذا ذبلت زهورك بشكل غير طبيعي خلال 7 أيام من الاستلام رغم اتباع إرشادات العناية، سنقوم باستبدال الباقة مجاناً أو إعادة قيمتها.',
                ],
                'sort_order' => 12,
                'is_active' => true,
            ],
        ];

        // Wipe old placeholder FAQs and insert clean comprehensive 12 FAQs
        Faq::truncate();

        foreach ($faqs as $data) {
            Faq::create($data);
        }
    }
}
