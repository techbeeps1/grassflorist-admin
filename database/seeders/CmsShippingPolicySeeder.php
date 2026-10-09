<?php

namespace Database\Seeders;

use App\Models\CmsPage;
use Illuminate\Database\Seeder;

class CmsShippingPolicySeeder extends Seeder
{
    public function run(): void
    {
        $page = CmsPage::firstOrNew(['slug' => 'shipping-policy']);
        $page->title = [
            'en' => 'Cold-Chain Shipping & Delivery Terms',
            'ar' => 'سياسة الشحن والتوصيل المبرد',
        ];
        $page->short_description = [
            'en' => 'Express temperature-controlled flower delivery across Riyadh, Jeddah, Khobar, and Dammam.',
            'ar' => 'خدمة التوصيل المبرد الفوري لباقات الزهور في الرياض، جدة، الخبر والدمام.',
        ];
        $page->content = [
            'en' => '<p>We provide express same-day climate-controlled delivery within 2 to 3 hours across Riyadh, Jeddah, Khobar, and Dammam.</p><p>All arrangements travel in refrigerated fleet vehicles regulated between 16°C and 18°C, shielding delicate blossoms from ambient heat.</p><p>Standard express delivery is 35 SAR, and completely complimentary on all qualifying orders exceeding 250 SAR.</p><p>Clients can schedule exact future delivery dates and select preferred morning, afternoon, or evening delivery intervals.</p>',
            'ar' => '<p>نوفر خدمة التوصيل المبرد الفوري في نفس اليوم خلال ساعتين إلى ثلاث ساعات في الرياض وجدة والخبر والدمام.</p><p>تُنقل جميع الباقات في سيارات مكيفة خصيصاً ومزودة بأنظمة تبريد تحافظ على حرارة ما بين 16 إلى 18 درجة مئوية لحماية بتلات الزهور من حرارة الطقس الخارجية.</p><p>رسوم التوصيل السريع هي 35 ر.س، وتكون مجانية تماماً لأي طلب تتجاوز قيمته 250 ر.س.</p><p>يمكن للعميل جدولة موعد وتاريخ التوصيل بدقة واختيار الفترة الصباحية، الظهيرة، أو المسائية المناسبة.</p>',
        ];
        $page->meta_title = [
            'en' => 'Cold-Chain Shipping & Delivery Terms | Grass Florist',
            'ar' => 'الشحن والتوصيل المبرد الفوري | غراس فلوريست',
        ];
        $page->meta_description = [
            'en' => 'Express same-day refrigerated flower delivery across Saudi Arabia.',
            'ar' => 'خدمة توصيل الزهور في سيارات مبردة لضمان أقصى درجات النضارة والفخامة.',
        ];
        $page->is_active = true;
        $page->save();

        echo "Shipping policy CMS page seeded with ID: " . $page->id . PHP_EOL;
    }
}
