<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CmsCategory;
use App\Models\CmsPost;
use App\Models\Faq;
use App\Models\HomePage;
use App\Models\Testimonial;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class StorefrontContentSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Testimonials
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
                'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80',
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
                    'en' => 'Birthday Celebration',
                    'ar' => 'عيد ميلاد',
                ],
                'content' => [
                    'en' => 'Undoubtedly the premier online floral experience in Saudi Arabia. The Belgian chocolates were decadent and the roses stayed vibrant for over eight days at home.',
                    'ar' => 'بلا شك أفضل تجربة لطلب الورود أونلاين في المملكة العربية السعودية. الشوكولاتة البلجيكية كانت لذيذة للغاية والورد ظل نضراً لأكثر من ثمانية أيام في المنزل.',
                ],
                'rating' => 5.0,
                'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=200&q=80',
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
                'avatar' => 'https://images.unsplash.com/photo-1517841905240-472988babdf9?auto=format&fit=crop&w=200&q=80',
                'is_verified' => true,
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'author_name' => [
                    'en' => 'Faisal Al-Shammari',
                    'ar' => 'فيصل الشمري',
                ],
                'city' => [
                    'en' => 'Dammam',
                    'ar' => 'الدمام',
                ],
                'occasion_tag' => [
                    'en' => 'Mother\'s Day Special',
                    'ar' => 'هدية يوم الأم',
                ],
                'content' => [
                    'en' => 'The arrangement for my mother brought tears of joy to her eyes. Customer support updated me every step from arrangement to hand-delivery.',
                    'ar' => 'الباقة التي أرسلتها لوالدتي أسعدت قلبها كثيراً. فريق الدعم أرسل لي صور الباقة قبل التوصيل وأبلغني فور تسليمها باليد.',
                ],
                'rating' => 5.0,
                'avatar' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=200&q=80',
                'is_verified' => true,
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'author_name' => [
                    'en' => 'Laila Al-Ghamdi',
                    'ar' => 'ليلى الغامدي',
                ],
                'city' => [
                    'en' => 'Jeddah',
                    'ar' => 'جدة',
                ],
                'occasion_tag' => [
                    'en' => 'Baby Girl Congratulations',
                    'ar' => 'مباركة مولودة جديدة',
                ],
                'content' => [
                    'en' => 'The pastel pink roses with Patchi chocolates and balloons were breathtaking. Delivered directly to the hospital room seamlessly.',
                    'ar' => 'تنسيق ورد البيبي روز الوردي مع شوكولاتة باتشي والبالونات كان خيالياً. تم التوصيل لغرفة المستشفى بكل يسر واحترافية.',
                ],
                'rating' => 5.0,
                'avatar' => 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&w=200&q=80',
                'is_verified' => true,
                'sort_order' => 5,
                'is_active' => true,
            ],
        ];

        foreach ($testimonials as $data) {
            Testimonial::updateOrCreate(
                ['author_name->en' => $data['author_name']['en']],
                $data
            );
        }

        // 2. Seed FAQs
        $faqs = [
            [
                'category' => [
                    'en' => 'Delivery',
                    'ar' => 'التوصيل',
                ],
                'question' => [
                    'en' => 'How long does it take to deliver the order in Jeddah and major cities?',
                    'ar' => 'كم يستغرق توصيل الطلب في جدة والمدن الرئيسية؟',
                ],
                'answer' => [
                    'en' => 'We offer express same-day refrigerated delivery within 2 to 3 hours of order confirmation across Jeddah and major metropolitan cities. You can also schedule advance delivery for any specific date and time slot.',
                    'ar' => 'نوفر خدمة التوصيل السريع المبرد في نفس اليوم خلال ساعتين إلى ثلاث ساعات من تأكيد الطلب في جدة والمدن الرئيسية. كما يمكنك جدولة الطلب واختيار موعد وتاريخ محدد يناسبك أو يناسب المستلم.',
                ],
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'category' => [
                    'en' => 'Delivery',
                    'ar' => 'التوصيل',
                ],
                'question' => [
                    'en' => 'How do you preserve floral freshness in warm weather?',
                    'ar' => 'كيف تضمنون وصول الزهور طازجة وغير ذابلة في حرارة الجو؟',
                ],
                'answer' => [
                    'en' => 'Our entire delivery fleet is custom-fitted with temperature-regulated climate control (16–18°C). Stems are hydrated with individual water reservoirs ensuring zero wilting between our atelier and the recipient doorstep.',
                    'ar' => 'نمتلك أسطولاً مجهزاً بنظام تبريد حراري مدروس (16-18 درجة مئوية)، كما يتم تزويد سيقان الزهور بكبسولات ترطيب مائية تحافظ على امتصاص الماء حتى لحظة التسليم لباب العميل أو المستلم.',
                ],
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'category' => [
                    'en' => 'Ordering',
                    'ar' => 'الطلب والإهداء',
                ],
                'question' => [
                    'en' => 'Can I send a surprise gift if I do not have the recipient full address?',
                    'ar' => 'هل يمكنني إرسال هدية مفاجئة إذا لم يكن لدي عنوان المستلم بالكامل؟',
                ],
                'answer' => [
                    'en' => 'Yes! Simply provide the recipient’s name and mobile number. Our concierge will discreetly coordinate delivery location and timing via WhatsApp while keeping the nature of the gift a pleasant surprise.',
                    'ar' => 'نعم بكل تأكيد! يكفيك تزويدنا باسم المستلم ورقم جواله، وسيقوم فريق خدمة العملاء بالتواصل معه بلباقة لتحديد الموقع الجغرافي المناسب دون إفساد عنصر المفاجأة.',
                ],
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'category' => [
                    'en' => 'Ordering',
                    'ar' => 'الطلب والإهداء',
                ],
                'question' => [
                    'en' => 'Can I include a personalized handwritten greeting card?',
                    'ar' => 'هل يمكنني إضافة رسالة إهداء خاصة مكتوبة بخط اليد؟',
                ],
                'answer' => [
                    'en' => 'Yes, every gift includes a luxury branded card where you can enter your heartfelt message during checkout. Our calligraphers handwrite your dedication with utmost care free of charge.',
                    'ar' => 'نعم، تشمل كل هدية بطاقة إهداء فاخرة مجانية حيث يمكنك كتابة رسالتك أثناء إتمام الطلب، ويقوم خطاطونا المحترفون بكتابتها يدوياً بكل إتقان.',
                ],
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'category' => [
                    'en' => 'Special Services',
                    'ar' => 'خدمات خاصة',
                ],
                'question' => [
                    'en' => 'Do you deliver directly to hospitals, luxury hotels, and event venues?',
                    'ar' => 'هل يتوفر التوصيل إلى المستشفيات والفنادق وقاعات الاحتفالات؟',
                ],
                'answer' => [
                    'en' => 'Yes, we deliver directly to hospital patient suites, luxury hotel concierges, corporate towers, and wedding venues. Please include room numbers or suite details in checkout notes.',
                    'ar' => 'نعم، نقوم بالتوصيل المباشر إلى غرف المستشفيات وأجنحة الفنادق وصالات الأفراح والمكاتب التنفيذية. يرجى تزويدنا برقم الغرفة أو اسم الجناح عند إتمام الطلب.',
                ],
                'sort_order' => 5,
                'is_active' => true,
            ],
            [
                'category' => [
                    'en' => 'Gifting & Privacy',
                    'ar' => 'الخصوصية',
                ],
                'question' => [
                    'en' => 'Can I send the gift anonymously without revealing my name?',
                    'ar' => 'هل يمكنني إرسال الهدية دون الكشف عن هويتي؟',
                ],
                'answer' => [
                    'en' => 'Yes, we respect your privacy. You can choose to keep your identity confidential, and the recipient will only receive the card message you specify.',
                    'ar' => 'نعم، نحترم خصوصيتك التامة. يمكنك اختيار إبقاء اسمك سرياً ولن يظهر للمستلم سوى رسالة الإهداء المكتوبة في البطاقة.',
                ],
                'sort_order' => 6,
                'is_active' => true,
            ],
        ];

        foreach ($faqs as $data) {
            Faq::updateOrCreate(
                ['question->en' => $data['question']['en']],
                $data
            );
        }

        // 3. Seed CMS Category & Blog Posts
        $cmsCat = CmsCategory::firstOrCreate(
            ['slug' => 'floral-care'],
            [
                'name' => [
                    'en' => 'Floral Care & Inspiration',
                    'ar' => 'العناية بالزهور وإلهام التصاميم',
                ],
                'slug_ar' => 'العناية-بالزهور',
                'is_active' => true,
            ]
        );

        $posts = [
            [
                'cms_category_id' => $cmsCat->id,
                'title' => [
                    'en' => 'Master Florist Guide: 7 Essential Steps to Extend Flower Vase Life Past 10 Days',
                    'ar' => 'دليل الخبراء: 7 خطوات ذهبية للحفاظ على نضارة باقات الورد لأكثر من 10 أيام',
                ],
                'slug' => 'how-to-keep-cut-flowers-fresh-longer',
                'slug_ar' => 'كيفية-الحفاظ-على-نضارة-الزهور',
                'short_description' => [
                    'en' => 'Learn the scientific practices employed by high-end floral ateliers to trim stems, optimize water hydration, and prevent premature wilting.',
                    'ar' => 'تعرف على الطرق المتبعة في أرقى بوتيكات الزهور لقص السيقان، تغيير الماء، واستخدام المغذيات لإبقاء الزهور يانعة وفواحة لأطول مدة.',
                ],
                'content' => [
                    'en' => '<p>Fresh flower bouquets are an exquisite sensory addition to your living space. To ensure your stems remain radiant for ten days or longer, apply these expert protocols from Grass Florist.</p><h3>1. Cut Stems at a 45-Degree Angle</h3><p>Trim 2-3 cm diagonally under running water to expand absorption area.</p><h3>2. Strip Submerged Leaves</h3><p>Remove foliage below the waterline to prevent microbial growth.</p><h3>3. Keep in Cool Temperature</h3><p>Shield arrangements from direct sunlight and drafts.</p>',
                    'ar' => '<p>باقات الزهور الطبيعية لوحة فنية نابضة بالحياة تضفي على المنزل بهجة لا مثيل لها. للحفاظ على نضارة زهورك لأطول فترة ممكنة، اتبع هذه النصائح الذهبية من خبراء غراس فلوريست:</p><h3>1. تقليم السيقان بزاوية 45 درجة</h3><p>قم بقص 2-3 سم مائلاً لزيادة مساحة امتصاص الماء.</p><h3>2. إزالة الأوراق المغمورة بالماء</h3><p>لمنع تكاثر البكتيريا والحفاظ على نظافة الماء.</p><h3>3. إبعاد الفازة عن مصادر الحرارة وأشعة الشمس المباشرة</h3><p>لحماية البتلات من الذبول المبكر.</p>',
                ],
                'image' => 'https://images.unsplash.com/photo-1561181286-d3fee7d55364?auto=format&fit=crop&w=1200&q=80',
                'banner_image' => 'https://images.unsplash.com/photo-1561181286-d3fee7d55364?auto=format&fit=crop&w=1200&q=80',
                'author' => 'Maha Al-Tamimi',
                'is_active' => true,
                'published_at' => now(),
            ],
            [
                'cms_category_id' => $cmsCat->id,
                'title' => [
                    'en' => 'The Royal Language of Blooms: Symbolism Behind Rose Colors in Saudi Gifting Culture',
                    'ar' => 'لغة الزهور الملكية: معاني ألوان الجوري في ثقافة الإهداء السعودية',
                ],
                'slug' => 'language-of-roses-saudi-gifting-culture',
                'slug_ar' => 'معاني-الوان-الورد-الجوري-في-السعودية',
                'short_description' => [
                    'en' => 'Discover the nuanced emotional significance of red, white, yellow, and pastel roses when sending gifts for celebrations and intimate milestones.',
                    'ar' => 'اكتشف الدلالات العاطفية الراقية لألوان الورد الجوري الأحمر والأبيض والأصفر عند تقديم الهدايا في المناسبات السعيدة والأعياد.',
                ],
                'content' => [
                    'en' => '<p>Flowers speak a universal language of elegance and grace. In Arabian hospitality and gifting culture, every hue carries distinct sentiments.</p><h3>Red Roses</h3><p>Symbolizes timeless love, deep appreciation, and majestic celebrations.</p><h3>White Roses</h3><p>Embodies purity, reverence, new beginnings, and congratulatory milestones.</p><h3>Yellow & Peach Roses</h3><p>Expresses joyous friendships, warmth, and radiant beginnings.</p>',
                    'ar' => '<p>تحمل الزهور رسائل صامتة تعبر عن أسمى المشاعر الإنسانية. في ثقافة الإهداء الراقية، لكل لون دلالة تنبض بالمعاني:</p><h3>الورد الأحمر الملكي</h3><p>رمز المحبة الصادقة، الامتنان العميق، والاحتفالات الخاصة.</p><h3>الورد الأبيض الناصع</h3><p>يعبر عن النقاء، البدايات الجديدة، وتهاني التخرج والزواج.</p><h3>الورد الأصفر والدرّاقي</h3><p>يرمز إلى الصداقة المخلصة، الفرح، ودفء اللقاءات العائلية.</p>',
                ],
                'image' => 'https://images.unsplash.com/photo-1518895949257-7621c3c786d7?auto=format&fit=crop&w=1200&q=80',
                'banner_image' => 'https://images.unsplash.com/photo-1518895949257-7621c3c786d7?auto=format&fit=crop&w=1200&q=80',
                'author' => 'Nouf Al-Harbi',
                'is_active' => true,
                'published_at' => now()->subDays(2),
            ],
            [
                'cms_category_id' => $cmsCat->id,
                'title' => [
                    'en' => 'Orchid Elegance: Decorating Luxury Living Spaces with Phalaenopsis',
                    'ar' => 'فخامة الأوركيد: فن تزيين المجالس والمساحات الراقية بنباتات فالينوبسيس',
                ],
                'slug' => 'orchid-elegance-luxury-interior-decor',
                'slug_ar' => 'فخامة-اوركيد-تزيين-المجالس-والمنازل',
                'short_description' => [
                    'en' => 'How to style cascading white and purple phalaenopsis orchids as architectural centerpieces in contemporary majlis salons and luxury homes.',
                    'ar' => 'كيفية توظيف نباتات الأوركيد البيضاء والبنفسجية كقطع ديكور فنية تخطف الأنظار في المجالس العصرية وصالات الاستقبال الفاخرة.',
                ],
                'content' => [
                    'en' => '<p>Phalaenopsis orchids stand as the quintessential choice for interior designers seeking architectural poise and enduring luxury.</p><h3>Lighting Requirements</h3><p>Filtered indirect daylight fosters prolonged blooming cycles up to 3 months.</p><h3>Watering Discipline</h3><p>Water moderately once weekly, ensuring adequate drainage without submerged roots.</p>',
                    'ar' => '<p>تعتبر نباتات الأوركيد الاختيار الأول لمصممي الديكور لما تتمتع به من أناقة استثنائية وزهور تدوم لأشهر عديدة.</p><h3>الإضاءة المثالية</h3><p>تفضل نباتات الأوركيد الضوء الطبيعي غير المباشر لضمان تفتح براعمها لأكثر من 3 أشهر.</p><h3>الري المنظم</h3><p>قم بريها مرة واحدة أسبوعياً بكمية معتدلة مع التأكد من تصريف الماء الزائد.</p>',
                ],
                'image' => 'https://images.unsplash.com/photo-1525310072745-f49212b5ac6d?auto=format&fit=crop&w=1200&q=80',
                'banner_image' => 'https://images.unsplash.com/photo-1525310072745-f49212b5ac6d?auto=format&fit=crop&w=1200&q=80',
                'author' => 'Tariq Al-Mansour',
                'is_active' => true,
                'published_at' => now()->subDays(5),
            ],
        ];

        foreach ($posts as $postData) {
            CmsPost::updateOrCreate(
                ['slug' => $postData['slug']],
                $postData
            );
        }

        // 4. Update HomePage Record 1 Defaults
        $homePage = HomePage::first();
        if ($homePage) {
            // Find categories for default sections
            $cat1 = Category::where('slug', 'ocassions')->orWhere('slug_ar', 'المناسبات')->first();
            $cat2 = Category::where('slug', 'best-seller')->orWhere('slug_ar', 'الأفضل-مبيعاً')->first();
            $cat3 = Category::where('slug', 'new-baby-girl')->orWhere('slug_ar', 'مولودة-جديدة')->first();
            $cat4 = Category::where('slug', 'colors')->orWhere('slug_ar', 'الألوان')->first();

            $categorySections = [];
            if ($cat1) {
                $categorySections[] = [
                    'title' => [
                        'en' => 'Curated Floral Occasions',
                        'ar' => 'باقات لكافة المناسبات',
                    ],
                    'subtitle' => [
                        'en' => 'Delivering emotions through flowers with carefully crafted arrangements',
                        'ar' => 'نوصل مشاعرك من خلال زهورنا المتميزة التي تناسب جميع الأذواق والمناسبات',
                    ],
                    'category_id' => (string) $cat1->id,
                    'limit' => 8,
                ];
            }
            if ($cat3) {
                $categorySections[] = [
                    'title' => [
                        'en' => 'New Baby Celebrations',
                        'ar' => 'استقبال المواليد الجدد',
                    ],
                    'subtitle' => [
                        'en' => 'Delightful pastel blooms and gifting packages for new arrivals',
                        'ar' => 'تنسيقات ورد فاخرة وهدايا استثنائية للاحتفال بقدوم المولود الجديد',
                    ],
                    'category_id' => (string) $cat3->id,
                    'limit' => 8,
                ];
            }
            if ($cat4) {
                $categorySections[] = [
                    'title' => [
                        'en' => 'Vibrant Color Palettes',
                        'ar' => 'ألوان مبهجة تناسب ذوقك',
                    ],
                    'subtitle' => [
                        'en' => 'Color your celebrations with our vibrant floral arrangements',
                        'ar' => 'لونوا مناسباتكم بتشكيلات زهور ملونة مفعمة بالحياة والجمال',
                    ],
                    'category_id' => (string) $cat4->id,
                    'limit' => 8,
                ];
            }

            // Set default sliders with bilingual English and Arabic banner images
            $sliders = [
                [
                    'slider_image_en' => 'home-page/banner/banner-1.jpg',
                    'slider_image_ar' => 'home-page/banner/banner-1.jpg',
                    'slider_image' => 'home-page/banner/banner-1.jpg',
                    'slider_url' => '/category/ocassions',
                ],
                [
                    'slider_image_en' => 'home-page/banner/banner-2.jpg',
                    'slider_image_ar' => 'home-page/banner/banner-2.jpg',
                    'slider_image' => 'home-page/banner/banner-2.jpg',
                    'slider_url' => '/category/best-seller',
                ],
                [
                    'slider_image_en' => 'home-page/banner/banner-3.jpg',
                    'slider_image_ar' => 'home-page/banner/banner-3.jpg',
                    'slider_image' => 'home-page/banner/banner-3.jpg',
                    'slider_url' => '/products',
                ],
            ];

            $msliders = [
                [
                    'mslider_image_en' => 'home-page/banner/banner-1.jpg',
                    'mslider_image_ar' => 'home-page/banner/banner-1.jpg',
                    'mslider_image' => 'home-page/banner/banner-1.jpg',
                    'mslider_url' => '/category/ocassions',
                ],
                [
                    'mslider_image_en' => 'home-page/banner/banner-2.jpg',
                    'mslider_image_ar' => 'home-page/banner/banner-2.jpg',
                    'mslider_image' => 'home-page/banner/banner-2.jpg',
                    'mslider_url' => '/category/best-seller',
                ],
                [
                    'mslider_image_en' => 'home-page/banner/banner-3.jpg',
                    'mslider_image_ar' => 'home-page/banner/banner-3.jpg',
                    'mslider_image' => 'home-page/banner/banner-3.jpg',
                    'mslider_url' => '/products',
                ],
            ];

            $homePage->update([
                'slider_section' => $sliders,
                'mslider_section' => $msliders,
                'page_title' => [
                    'en' => 'Grass Florist - Luxury Flowers & Gifts in Saudi Arabia',
                    'ar' => 'غراس فلوريست - أرقى باقات الورد والهدايا الفاخرة في السعودية',
                ],
                'popular_title' => [
                    'en' => 'Popular Categories',
                    'ar' => 'التصنيفات الأكثر طلباً',
                ],
                'popular_subtitle' => [
                    'en' => 'Everything to shop from, chosen with love and precision',
                    'ar' => 'استكشف باقات الزهور وتنسيقات الهدايا حسب التصنيف المفضل لديك',
                ],
                'best_sellers_title' => [
                    'en' => 'Best Sellers',
                    'ar' => 'الأفضل مبيعاً',
                ],
                'best_sellers_subtitle' => [
                    'en' => 'Our most loved and handcrafted floral arrangements',
                    'ar' => 'أجمل باقات الورد والهدايا المختارة بعناية فائقة لعملائنا',
                ],
                'blog_title' => [
                    'en' => 'Floral Inspiration & Care Guides',
                    'ar' => 'إلهام وأسرار العناية بالزهور',
                ],
                'blog_subtitle' => [
                    'en' => 'Curated articles from master florists to guide your gifting choices and prolong bloom life.',
                    'ar' => 'مقالات حصرية من خبراء تنسيق الزهور لإرشادك في اختيار الهدية المثالية والحفاظ على نضارتها.',
                ],
                'testimonials_title' => [
                    'en' => 'Stories from Our Clients',
                    'ar' => 'تجارب عملائنا المميزين',
                ],
                'testimonials_subtitle' => [
                    'en' => 'Real feedback from those who trusted us with their special moments',
                    'ar' => 'آراء وتقييمات حقيقية من عملائنا الكرام الذين شاركونا أجمل لحظاتهم',
                ],
                'faq_title' => [
                    'en' => 'Frequently Asked Questions',
                    'ar' => 'الأسئلة الأكثر شيوعاً',
                ],
                'faq_subtitle' => [
                    'en' => 'Find quick answers to common questions about our fresh flowers, delivery, and services.',
                    'ar' => 'إجابات وافية وشاملة عن أكثر الأسئلة شيوعاً حول باقاتنا وخدمات التوصيل المبرد.',
                ],
                'banner_title' => [
                    'en' => "Same Day Delivery\nFlowers & Gifts",
                    'ar' => "توصيل في نفس اليوم\nزهور وهدايا فاخرة",
                ],
                'banner_button_title' => [
                    'en' => 'Shop Same Day Flowers',
                    'ar' => 'تسوق زهور اليوم نفسه',
                ],
                'banner_button_url' => '/category/all-flowers',
                'banner_image_en' => 'home-page/banner/editorial-woman-bouquet.webp',
                'banner_image_ar' => 'home-page/banner/editorial-woman-bouquet.webp',
                'benefits_section' => [
                    [
                        'icon' => 'sprout',
                        'title' => [
                            'en' => 'Large Assortment',
                            'ar' => 'تشكيلة واسعة وكبيرة',
                        ],
                        'description' => [
                            'en' => 'Many different types of products with fewer variations.',
                            'ar' => 'العديد من أنواع الزهور والتنسيقات الفاخرة التي تناسب جميع مناسباتكم.',
                        ],
                    ],
                    [
                        'icon' => 'package',
                        'title' => [
                            'en' => 'Same day delivery',
                            'ar' => 'التسليم في نفس اليوم',
                        ],
                        'description' => [
                            'en' => '1-day or less delivery time, an expedited delivery option.',
                            'ar' => 'مدة التسليم يوم واحد أو أقل، خيار تسليم سريع مع أسطول مبرد.',
                        ],
                    ],
                    [
                        'icon' => 'wallet',
                        'title' => [
                            'en' => 'Easy and Secure Payment',
                            'ar' => 'الدفع السهل والآمن',
                        ],
                        'description' => [
                            'en' => 'Answers to any business related query within a few hours.',
                            'ar' => 'طرق دفع إلكترونية متعددة وآمنة تماماً لراحة بال تامة.',
                        ],
                    ],
                ],
                'events_section' => [
                    'badge' => [
                        'en' => 'EVENTS',
                        'ar' => 'المناسبات',
                    ],
                    'title_main' => [
                        'en' => 'Blossom your events with our',
                        'ar' => 'أضف لمسة من السحر لمناسباتك مع',
                    ],
                    'title_highlight' => [
                        'en' => 'expert touch!',
                        'ar' => 'لمستنا الاحترافية!',
                    ],
                    'description' => [
                        'en' => 'Discover the perfect harmony of floral elegance and event planning expertise with our flower shop. Let us bring your events to life with our meticulous attention to detail and stunning floral arrangements. From weddings to corporate gatherings, our team of professionals will create a captivating ambiance that exceeds your expectations.',
                        'ar' => 'اكتشف التناغم المثالي بين أناقة الزهور وخبرة تنظيم المناسبات مع بوتيك غراس. دعنا ننبض الحياة في مناسباتكم بعناية فائقة بأدق التفاصيل وتنسيقات زهور مذهلة. من حفلات الزفاف إلى الفعاليات الرسمية، سيبتكر فريقنا من المحترفين أجواءً ساحرة تفوق توقعاتكم.',
                    ],
                    'button_text' => [
                        'en' => 'BOOK NOW',
                        'ar' => 'احجز الآن',
                    ],
                    'button_url' => '/event-booking',
                    'tag1' => [
                        'en' => 'Weddings',
                        'ar' => 'حفلات الزفاف',
                    ],
                    'tag2' => [
                        'en' => 'Corporate Events',
                        'ar' => 'فعاليات الشركات',
                    ],
                    'tag3' => [
                        'en' => 'Special Occasions',
                        'ar' => 'مناسبات خاصة',
                    ],
                    'slides' => [
                        [
                            'image' => 'home-page/events/wedding-runway.jpg',
                            'title' => ['en' => 'Romantic Walkways', 'ar' => 'ممرات الزفاف الرومانسية'],
                            'service_tag' => ['en' => 'EVENT SERVICE', 'ar' => 'باقة المناسبات'],
                            'url' => '/event-booking',
                        ],
                        [
                            'image' => 'home-page/events/baby-reception.jpg',
                            'title' => ['en' => 'Haute Floral Stages', 'ar' => 'كوشات ومنصات زهور فاخرة'],
                            'service_tag' => ['en' => 'EVENT SERVICE', 'ar' => 'باقة المناسبات'],
                            'url' => '/event-booking',
                        ],
                        [
                            'image' => 'home-page/events/wedding-cake.jpg',
                            'title' => ['en' => 'Bespoke Wedding Cakes', 'ar' => 'كيك وحلويات الزفاف'],
                            'service_tag' => ['en' => 'EVENT SERVICE', 'ar' => 'باقة المناسبات'],
                            'url' => '/event-booking',
                        ],
                        [
                            'image' => 'home-page/events/wedding-ballroom.jpg',
                            'title' => ['en' => 'Grand Ballrooms', 'ar' => 'قاعات ملكية فخمة'],
                            'service_tag' => ['en' => 'EVENT SERVICE', 'ar' => 'باقة المناسبات'],
                            'url' => '/event-booking',
                        ],
                        [
                            'image' => 'home-page/events/table-setup.jpg',
                            'title' => ['en' => 'VIP Tablescapes', 'ar' => 'طاولات وضيافة كبار الشخصيات'],
                            'service_tag' => ['en' => 'EVENT SERVICE', 'ar' => 'باقة المناسبات'],
                            'url' => '/event-booking',
                        ],
                        [
                            'image' => 'home-page/events/floral-scenography.jpg',
                            'title' => ['en' => 'Floral Scenography', 'ar' => 'سينوغرافيا الزهور'],
                            'service_tag' => ['en' => 'EVENT SERVICE', 'ar' => 'باقة المناسبات'],
                            'url' => '/event-booking',
                        ],
                    ],
                ],
                'category_sections' => !empty($categorySections) ? $categorySections : $homePage->category_sections,
                'meta_tag_title' => [
                    'en' => 'Grass Florist | Same-Day Flower Delivery in Jeddah & Saudi Arabia',
                    'ar' => 'غراس فلوريست | توصيل ورد وهدايا في نفس اليوم في جدة والسعودية',
                ],
                'meta_tag_description' => [
                    'en' => 'Send luxury flower bouquets, fresh roses, chocolates and bespoke gifts across Saudi Arabia. Same-day refrigerated delivery.',
                    'ar' => 'اطلب أرقى باقات الورد الطبيعي، الشوكولاتة الفاخرة والهدايا في جدة والمملكة. توصيل فوري مبرد بنفس اليوم بأيدي خبراء تنسيق الزهور.',
                ],
                'meta_tag_keywords' => [
                    'en' => 'flowers jeddah, saudi florist, rose bouquets, gift delivery, grass florist',
                    'ar' => 'توصيل ورد جدة, متجر زهور, باقات ورد طبيعي, هدايا فاخرة, غراس فلوريست',
                ],
            ]);
        }
    }
}
