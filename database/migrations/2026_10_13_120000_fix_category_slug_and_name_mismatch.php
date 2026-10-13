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
            ['wedding', 138, 'Wedding', 'زواج', 'زواج'],
            ['bundles', 177, 'Bundles', 'باقات', 'باقات'],
            ['bridal-bouquet', 178, 'Bridal Bouquet', 'مسكة عروس', 'مسكة-عروس'],
            ['white-friday', 189, 'White Friday', 'الجمعة البيضاء', 'الجمعة-البيضاء'],
            ['fatime-birthday', 262, 'Fatima Birthday', 'عيد ميلاد فاطمة', 'عيد-ميلاد-فاطمة'],
            ['sister-of-fatime-birthday', 263, 'Sister of Fatima Birthday', 'عيد ميلاد أخت فاطمة', 'عيد-ميلاد-أخت-فاطمة'],
            ['teachers-day', 266, "Teacher's Day", 'يوم المعلم', 'يوم-المعلم'],
            ['new-year', 267, 'New Year', 'رأس السنة', 'رأس-السنة'],
            ['bridal-bookeh', 269, 'Bridal Bookeh', 'بوكيه عروس', 'بوكيه-عروس'],
            ['flag-day', 270, 'Flag Day', 'يوم العلم', 'يوم-العلم'],
            ['womens-day', 271, "Women's Day", 'يوم المرأة', 'يوم-المرأة'],
            ['foundation-day', 272, 'Founding Day', 'يوم التأسيس', 'يوم-التأسيس'],
            ['anniversary', 287, 'Happy Anniversary', 'عيد زواج سعيد', 'عيد-زواج-سعيد'],
            ['get-well-soon', 289, 'Get well soon', 'تمنيات بالشفاء', 'تمنيات-بالشفاء'],
            ['housewarming', 291, 'Housewarming', 'منزل مبارك', 'منزل-مبارك'],
            ['new-born', 293, 'New born', 'تهنئة بالمولود', 'تهنئة-بالمولود'],
            ['i-love-you', 295, 'I love you', 'أحبك', 'أحبك'],
            ['letters-balloons-2', 255, 'Letters Balloons', 'بالونات أحرف', 'بالونات-أحرف'],
            ['letters-balloons', 168, 'Letters Balloons', 'بالونات الحروف', 'بالونات-الحروف'],
            ['numbers-ballons', 169, 'Numbers Ballons', 'بالونات الأرقام', 'بالونات-الأرقام'],
            ['golden-letters', 170, 'Golden Letters', 'أحرف ذهبية', 'أحرف-ذهبية'],
            ['silver-letters', 171, 'Silver Letters', 'أحرف فضية', 'أحرف-فضية'],
            ['silver-numbers', 172, 'Silver Numbers', 'أرقام فضية', 'أرقام-فضية'],
            ['golden-numbers', 174, 'Golden Numbers', 'أرقام ذهبية', 'أرقام-ذهبية'],
            ['latex-balloons', 173, 'Latex Balloons', 'بالونات مطاطية', 'بالونات-مطاطية'],
            ['peach', 150, 'Peach', 'بيج', 'بيج'],
            ['mixed', 152, 'Mixed', 'منوع', 'منوع'],
        ];

        foreach ($fixes as [$slug, $sourceId, $enName, $arName, $slugAr]) {
            $cat = Category::where('slug', $slug)
                ->orWhere('source_id', $sourceId)
                ->first();

            if ($cat) {
                $cat->slug = $slug;
                $cat->source_id = $sourceId;
                $cat->slug_ar = $slugAr;
                $cat->setTranslation('name', 'en', $enName);
                $cat->setTranslation('name', 'ar', $arName);
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
