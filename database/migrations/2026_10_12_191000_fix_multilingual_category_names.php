<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Category;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $fixes = [
            'wedding' => ['en' => 'Wedding', 'ar' => 'زواج', 'slug_ar' => 'زواج'],
            'bundles' => ['en' => 'Bundles', 'ar' => 'باقات', 'slug_ar' => 'باقات'],
            'bridal-bouquet' => ['en' => 'Bridal Bouquet', 'ar' => 'مسكة عروس', 'slug_ar' => 'مسكة-عروس'],
            'white-friday' => ['en' => 'White Friday', 'ar' => 'الجمعة البيضاء', 'slug_ar' => 'الجمعة-البيضاء'],
            'fatime-birthday' => ['en' => 'Fatima Birthday', 'ar' => 'عيد ميلاد فاطمة', 'slug_ar' => 'عيد-ميلاد-فاطمة'],
            'sister-of-fatime-birthday' => ['en' => 'Sister of Fatima Birthday', 'ar' => 'عيد ميلاد أخت فاطمة', 'slug_ar' => 'عيد-ميلاد-أخت-فاطمة'],
            'teachers-day' => ['en' => "Teacher's Day", 'ar' => 'يوم المعلم', 'slug_ar' => 'يوم-المعلم'],
            'new-year' => ['en' => 'New Year', 'ar' => 'رأس السنة', 'slug_ar' => 'رأس-السنة'],
            'anniversary' => ['en' => 'Happy Anniversary', 'ar' => 'عيد زواج سعيد', 'slug_ar' => 'عيد-زواج-سعيد'],
            'flag-day' => ['en' => 'Flag Day', 'ar' => 'يوم العلم', 'slug_ar' => 'يوم-العلم'],
            'foundation-day' => ['en' => 'Founding Day', 'ar' => 'يوم التأسيس', 'slug_ar' => 'يوم-التأسيس'],
            'womens-day' => ['en' => "Women's Day", 'ar' => 'يوم المرأة', 'slug_ar' => 'يوم-المرأة'],
            'get-well-soon' => ['en' => 'Get well soon', 'ar' => 'تمنيات بالشفاء', 'slug_ar' => 'تمنيات-بالشفاء'],
            'housewarming' => ['en' => 'Housewarming', 'ar' => 'منزل مبارك', 'slug_ar' => 'منزل-مبارك'],
            'new-born' => ['en' => 'New born', 'ar' => 'تهنئة بالمولود', 'slug_ar' => 'تهنئة-بالمولود'],
            'i-love-you' => ['en' => 'I love you', 'ar' => 'أحبك', 'slug_ar' => 'أحبك'],
        ];

        foreach ($fixes as $slug => $data) {
            $cat = Category::where('slug', $slug)->first();
            if ($cat) {
                $cat->setTranslation('name', 'en', $data['en']);
                $cat->setTranslation('name', 'ar', $data['ar']);
                if (!empty($data['slug_ar'])) {
                    $cat->slug_ar = $data['slug_ar'];
                }
                $cat->save();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse needed
    }
};
