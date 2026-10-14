<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
/**
* Run the migrations.
*/
public function up(): void
{
if (!Schema::hasTable('subscription_plans')) {
Schema::create('subscription_plans', function (Blueprint $table) {
$table->id();
$table->json('name'); // {"en": "Classic", "ar": "كلاسيك"}
$table->string('slug')->unique();
$table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
$table->decimal('starting_price', 10, 2)->default(129.00);
$table->decimal('original_price', 10, 2)->nullable();
$table->json('badge')->nullable(); // {"en": "Best Value", "ar": "الأكثر طلباً"}
$table->json('description')->nullable();
$table->json('details_content')->nullable();
$table->string('image')->nullable();
$table->json('gallery')->nullable();
$table->json('features')->nullable();
$table->json('allowed_frequencies')->nullable();
$table->json('duration_discounts')->nullable();
$table->boolean('is_active')->default(true);
$table->integer('sort_order')->default(0);
$table->timestamps();
});

// Seed initial 3 default featured plans matching user screenshots
DB::table('subscription_plans')->insert([
[
'name' => json_encode(['en' => 'Classic', 'ar' => 'الباقة الكلاسيكية']),
'slug' => 'classic',
'starting_price' => 129.00,
'original_price' => 160.00,
'badge' => json_encode(['en' => 'Timeless Favorite', 'ar' => 'الأكثر طلباً']),
'description' => json_encode([
'en' => 'A selection of timeless flowers, featuring roses, lilies, carnations, and more. Perfect for those who love
classic beauty in every bouquet.',
'ar' => 'تشكيلة مختارة من الزهور الكلاسيكية الدائمة، تتضمن الجوري، الليليوم، والقرنفل. مثالية لعشاق الأناقة الخالدة في
كل باقة.'
]),
'details_content' => json_encode([
'en' => 'Our Classic Plan brings elegance and timeless fragrance directly to your doorstep. Each arrangement is
handcrafted by master florists using the freshest farm-grown blooms, pre-treated for maximum vase life. Includes a
complimentary luxury vase with your first delivery.',
'ar' => 'تمنحك الباقة الكلاسيكية لمسة من الفخامة والعبير المنعش دورياً إلى عتبة بابك. كل باقة يتم تنسيقها يدوياً بواسطة
أمهر منسقي الزهور باستخدام أزهار طبيعية طازجة تدوم طويلاً. تشمل مزهرية فاخرة مجانية مع التوصيلة الأولى.'
]),
'image' => 'https://api.grassflorist.com/storage/products/g01720flower20delivery20jeddah202-600x600-1.jpg',
'gallery' => json_encode([
'https://api.grassflorist.com/storage/products/g01720flower20delivery20jeddah202-600x600-1.jpg',
]),
'features' => json_encode([
'Fresh roses, lilies & seasonal greenery',
'Free delivery on every scheduled cycle',
'Free luxury glass vase on first delivery',
'Flexible pause, skip or cancel anytime'
]),
'allowed_frequencies' => json_encode(['weekly', 'biweekly', 'monthly']),
'duration_discounts' => json_encode([
'1_month' => 0,
'3_months' => 10,
'6_months' => 15,
'12_months' => 20
]),
'is_active' => true,
'sort_order' => 1,
'created_at' => now(),
'updated_at' => now(),
],
[
'name' => json_encode(['en' => 'Seasonal', 'ar' => 'الباقة الموسمية']),
'slug' => 'seasonal',
'starting_price' => 173.00,
'original_price' => 210.00,
'badge' => json_encode(['en' => 'Curated Selection', 'ar' => 'زهور الموسم المختارة']),
'description' => json_encode([
'en' => 'A curated selection of flowers that change with the seasons. From spring tulips to summer sunflowers,
experience nature’s beauty year-round.',
'ar' => 'مجموعة زهور مميزة تتجدد مع كل موسم. من توليب الربيع المنعش إلى دوار الشمس الصيفي، استمتع بجمال الطبيعة المتجدد
طوال العام.'
]),
'details_content' => json_encode([
'en' => 'Embrace the rhythm of nature with our Seasonal Subscription. Every delivery arrives with distinct flower
varieties imported directly from top global growers, celebrating what is most vibrant this season.',
'ar' => 'استمتع بإيقاع الطبيعة المتغير مع باقتنا الموسمية. كل دورة توصيل تأتيك بأصناف مميزة مستوردة خصيصاً من أفضل
المزارع العالمية لتواكب رونق الموسم.'
]),
'image' => 'https://api.grassflorist.com/storage/products/g46220flower20delivery20jeddah202-600x600-1.jpg',
'gallery' => json_encode([
'https://api.grassflorist.com/storage/products/g46220flower20delivery20jeddah202-600x600-1.jpg',
]),
'features' => json_encode([
'Exotic seasonal flowers (Hydrangeas, Tulips, Peonies)',
'Free delivery inside Jeddah',
'Free vase on 1st delivery',
'Customized card messages on every bouquet'
]),
'allowed_frequencies' => json_encode(['weekly', 'biweekly', 'monthly']),
'duration_discounts' => json_encode([
'1_month' => 0,
'3_months' => 10,
'6_months' => 15,
'12_months' => 20
]),
'is_active' => true,
'sort_order' => 2,
'created_at' => now(),
'updated_at' => now(),
],
[
'name' => json_encode(['en' => 'DIY Flower Box', 'ar' => 'صندوق التنسيق الذاتي']),
'slug' => 'diy-flower-box',
'starting_price' => 142.00,
'original_price' => 175.00,
'badge' => json_encode(['en' => 'Creative Experience', 'ar' => 'تجربة إبداعية']),
'description' => json_encode([
'en' => 'Create your own floral magic with fresh, handpicked stems. Arrange unique bouquets that reflect your personal
style.',
'ar' => 'أطلق العنان لإبداعك ونسق باقتك بنفسك مع سيقان الزهور الطازجة المنتقاة بعناية لتعكس ذوقك ولمستك الخاصة.'
]),
'details_content' => json_encode([
'en' => 'Delivered in our signature eco-friendly presentation box, each stem is trimmed and prepped. Complete with
flower food and care guide for passionate flower arrangers.',
'ar' => 'تصلك في صندوقنا الفاخر المخصص الصديق للبيئة، حيث تكون السيقان مجهزة ومقلمة وجاهزة لتنسيقها بنفسك مع مغذيات
الزهور ودليل العناية.'
]),
'image' => 'https://api.grassflorist.com/storage/products/g40820flower20delivery20jeddah202-600x600-1.jpg',
'gallery' => json_encode([
'https://api.grassflorist.com/storage/products/g40820flower20delivery20jeddah202-600x600-1.jpg',
]),
'features' => json_encode([
'20-25 premium loose stems ready for arrangement',
'Includes flower food & arranging guide',
'Free delivery on every schedule',
'Complete creative freedom'
]),
'allowed_frequencies' => json_encode(['weekly', 'biweekly', 'monthly']),
'duration_discounts' => json_encode([
'1_month' => 0,
'3_months' => 10,
'6_months' => 15,
'12_months' => 20
]),
'is_active' => true,
'sort_order' => 3,
'created_at' => now(),
'updated_at' => now(),
],
]);
}

// Add banner configuration fields to subscription_settings if not present
if (Schema::hasTable('subscription_settings')) {
Schema::table('subscription_settings', function (Blueprint $table) {
if (!Schema::hasColumn('subscription_settings', 'banner_title')) {
$table->json('banner_title')->nullable();
$table->json('banner_subtitle')->nullable();
$table->string('banner_image')->nullable();
$table->json('perks')->nullable();
$table->json('how_it_works')->nullable();
}
});
}
}

/**
* Reverse the migrations.
*/
public function down(): void
{
Schema::dropIfExists('subscription_plans');
}
};