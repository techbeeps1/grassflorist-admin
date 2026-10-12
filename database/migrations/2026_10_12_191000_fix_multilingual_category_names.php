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
            60 => ['en' => 'Wedding', 'ar' => 'زواج', 'slug_ar' => 'زواج'],
            61 => ['en' => 'Bundles', 'ar' => 'باقات', 'slug_ar' => 'باقات'],
            62 => ['en' => 'Bridal Bouquet', 'ar' => 'مسكة عروس', 'slug_ar' => 'مسكة-عروس'],
            63 => ['en' => 'White Friday', 'ar' => 'الجمعة البيضاء', 'slug_ar' => 'الجمعة-البيضاء'],
            64 => ['en' => 'Fatima Birthday', 'ar' => 'عيد ميلاد فاطمة', 'slug_ar' => 'عيد-ميلاد-فاطمة'],
            65 => ['en' => 'Sister of Fatima Birthday', 'ar' => 'عيد ميلاد أخت فاطمة', 'slug_ar' => 'عيد-ميلاد-أخت-فاطمة'],
            66 => ['en' => "Teacher's Day", 'ar' => 'يوم المعلم', 'slug_ar' => 'يوم-المعلم'],
            67 => ['en' => 'New Year', 'ar' => 'رأس السنة', 'slug_ar' => 'رأس-السنة'],
            68 => ['en' => 'Anniversary', 'ar' => 'ذكرى سنوية', 'slug_ar' => 'ذكرى-سنوية'],
            54 => ['en' => 'Flag Day', 'ar' => 'يوم العلم', 'slug_ar' => 'يوم-العلم'],
            55 => ['en' => 'Founding Day', 'ar' => 'يوم التأسيس', 'slug_ar' => 'يوم-التأسيس'],
            56 => ['en' => "Women's Day", 'ar' => 'يوم المرأة', 'slug_ar' => 'يوم-المرأة'],
            15 => ['en' => 'Beige', 'ar' => 'بيج', 'slug_ar' => 'بيج'],
            17 => ['en' => 'Assorted', 'ar' => 'منوع', 'slug_ar' => 'منوع'],
            34 => ['en' => 'Number Balloons', 'ar' => 'بالونات أرقام', 'slug_ar' => 'بالونات-أرقام'],
            51 => ['en' => 'White Friday', 'ar' => 'الجمعة البيضاء', 'slug_ar' => 'الجمعة-البيضاء'],
            69 => ['en' => 'Happy Anniversary', 'ar' => 'عيد زواج سعيد', 'slug_ar' => 'عيد-زواج-سعيد'],
            70 => ['en' => 'Get well soon', 'ar' => 'تمنيات بالشفاء', 'slug_ar' => 'تمنيات-بالشفاء'],
            71 => ['en' => 'Housewarming', 'ar' => 'منزل مبارك', 'slug_ar' => 'منزل-مبارك'],
            72 => ['en' => 'New born', 'ar' => 'تهنئة بالمولود', 'slug_ar' => 'تهنئة-بالمولود'],
            73 => ['en' => 'I love you', 'ar' => 'أحبك', 'slug_ar' => 'أحبك'],
        ];

        foreach ($fixes as $id => $data) {
            $cat = Category::find($id);
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
