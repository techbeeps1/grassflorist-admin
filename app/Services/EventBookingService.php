<?php

namespace App\Services;

use App\Models\EventBooking;

class EventBookingService
{
    /**
     * Check if guest count is applicable for the given event type.
     */
    public static function isGuestsApplicable(?string $eventType): bool
    {
        if (!$eventType) {
            return false;
        }

        $applicable = [
            'hall wedding',
            'wedding hall decoration',
            'house wedding',
            'home wedding',
            'home event',
            'home event decoration',
            'تنسيق مناسبة في قاعة',
            'تنسيق مناسبة منزلية',
            'مناسبة منزلية',
            'حفل زفاف',
        ];

        return in_array(mb_strtolower(trim($eventType)), $applicable, true);
    }

    /**
     * Get bilingual event type title.
     */
    public static function getLocalizedEventType(?string $eventType, string $locale = 'en'): string
    {
        if (!$eventType) {
            return $locale === 'ar' ? 'مناسبة غير محددة' : 'Unspecified Event';
        }

        $map = [
            'Newborn welcome decorations' => [
                'en' => 'Newborn Welcome Decorations',
                'ar' => 'ديكورات استقبال مواليد',
            ],
            'Baby reception' => [
                'en' => 'Newborn Welcome Decorations',
                'ar' => 'ديكورات استقبال مواليد',
            ],
            'Bridal bouquets' => [
                'en' => 'Bridal Bouquets',
                'ar' => 'مسكات العرائس',
            ],
            'House wedding' => [
                'en' => 'Home Event Decoration',
                'ar' => 'تنسيق مناسبة منزلية',
            ],
            'Home event' => [
                'en' => 'Home Event Decoration',
                'ar' => 'تنسيق مناسبة منزلية',
            ],
            'Hall wedding' => [
                'en' => 'Wedding Hall Decoration',
                'ar' => 'تنسيق مناسبة في قاعة',
            ],
            'Milkah decoration' => [
                'en' => 'Milkah Ceremony Trays & Engagement Gifts',
                'ar' => 'تنسيق عربيات الملكة وهدايا الخطبة',
            ],
            'Flower supply' => [
                'en' => 'Fresh Flower Supply for Hotels & Corporate Clients',
                'ar' => 'توريد الورد الطبيعي للشركات والفنادق',
            ],
            'Opening ceremony' => [
                'en' => 'Grand Opening Decoration',
                'ar' => 'تنسيق حفل افتتاح',
            ],
            'Corporate event' => [
                'en' => 'Corporate Event Decoration',
                'ar' => 'ديكورات مناسبات الشركات',
            ],
            'Other inquiries' => [
                'en' => 'Other Inquiries & Consultations',
                'ar' => 'استفسارات أخرى واستشارات خاصة',
            ],
        ];

        foreach ($map as $key => $names) {
            if (mb_strtolower($key) === mb_strtolower(trim($eventType))) {
                return $names[$locale] ?? $names['en'];
            }
        }

        return $eventType;
    }

    /**
     * Extract clean user message without concatenated front-end text blocks.
     */
    public static function extractCleanMessage(?string $rawMessage): ?string
    {
        if (!$rawMessage) {
            return null;
        }

        // Split on lines like "--- تفاصيل ..." or "--- Details ..."
        $parts = preg_split('/(\r?\n)?---\s*(تفاصيل|details)[^\n]*---/ui', $rawMessage);
        $clean = trim($parts[0] ?? '');

        return !empty($clean) ? $clean : null;
    }

    /**
     * Extract all dynamic conditional fields from input based on event type.
     */
    public static function extractCustomFields(string $eventType, array $data): array
    {
        $fields = [];
        $type = mb_strtolower(trim($eventType));

        // Helper to get non-empty value
        $get = function ($key) use ($data) {
            if (isset($data[$key]) && $data[$key] !== '' && $data[$key] !== null) {
                return $data[$key];
            }
            return null;
        };

        if (str_contains($type, 'newborn') || str_contains($type, 'مواليد') || str_contains($type, 'baby')) {
            if ($val = $get('hospitalName') ?? $get('hospital_name')) $fields['hospital_name'] = $val;
            if ($val = $get('decorationOption') ?? $get('decoration_option')) $fields['decoration_option'] = $val;
            if ($val = $get('flowerType') ?? $get('flower_type')) $fields['flower_type'] = $val;
            if ($val = $get('themeColor') ?? $get('theme_color')) $fields['theme_color'] = $val;
        } elseif (str_contains($type, 'bridal') || str_contains($type, 'عرائس') || str_contains($type, 'مسكة')) {
            if ($val = $get('bouquetColors') ?? $get('bouquet_colors')) $fields['bouquet_colors'] = $val;
            if ($val = $get('bouquetSize') ?? $get('bouquet_size')) $fields['bouquet_size'] = $val;
            if ($val = $get('bouquetStyle') ?? $get('bouquet_style')) $fields['bouquet_style'] = $val;
            if ($val = $get('flowerType') ?? $get('flower_type')) $fields['flower_type'] = $val;
            if ($val = $get('deliveryMethod') ?? $get('delivery_method')) $fields['delivery_method'] = $val;
            if ($val = $get('deliveryAddress') ?? $get('delivery_address')) $fields['delivery_address'] = $val;
        } elseif (str_contains($type, 'milkah') || str_contains($type, 'ملكة')) {
            if ($val = $get('milkahService') ?? $get('milkah_service')) $fields['milkah_service'] = $val;
            if ($val = $get('flowerType') ?? $get('flower_type')) $fields['flower_type'] = $val;
            if ($val = $get('themeColor') ?? $get('theme_color')) $fields['theme_color'] = $val;
            if ($val = $get('deliveryMethod') ?? $get('delivery_method')) $fields['delivery_method'] = $val;
            if ($val = $get('deliveryAddress') ?? $get('delivery_address')) $fields['delivery_address'] = $val;
        } elseif (str_contains($type, 'hall') || str_contains($type, 'house') || str_contains($type, 'home') || str_contains($type, 'قاعة') || str_contains($type, 'منزل')) {
            if ($val = $get('homeEventType') ?? $get('home_event_type')) $fields['home_event_type'] = $val;
            if ($val = $get('homeEventOtherText') ?? $get('home_event_other_text')) $fields['home_event_other_text'] = $val;
            if ($val = $get('homeDecorAreas') ?? $get('decor_areas')) {
                $fields['decor_areas'] = is_array($val) ? $val : [$val];
            }
            if ($val = $get('flowerType') ?? $get('flower_type')) $fields['flower_type'] = $val;
            if ($val = $get('themeColor') ?? $get('theme_color')) $fields['theme_color'] = $val;
            if ($val = $get('homeTheme') ?? $get('theme')) $fields['theme'] = $val;
        } elseif (str_contains($type, 'supply') || str_contains($type, 'توريد')) {
            if ($val = $get('companyName') ?? $get('company_name')) $fields['company_name'] = $val;
            if ($val = $get('flowerSupplyTypes') ?? $get('supply_types')) {
                $fields['supply_types'] = is_array($val) ? $val : [$val];
            }
            if ($val = $get('flowerSupplyOther') ?? $get('supply_other')) $fields['supply_other'] = $val;
            if ($val = $get('flowerType') ?? $get('flower_type')) $fields['flower_type'] = $val;
            if ($val = $get('supplyFrequency') ?? $get('supply_frequency')) $fields['supply_frequency'] = $val;
            if ($val = $get('supplyQuantity') ?? $get('supply_quantity')) $fields['supply_quantity'] = $val;
            if ($val = $get('contractDuration') ?? $get('contract_duration')) $fields['contract_duration'] = $val;
            if ($val = $get('estimatedBudget') ?? $get('estimated_budget')) $fields['estimated_budget'] = $val;
            if ($val = $get('purchasingContactName') ?? $get('purchasing_contact_name')) $fields['purchasing_contact_name'] = $val;
            if ($val = $get('purchasingContactPhone') ?? $get('purchasing_contact_phone')) $fields['purchasing_contact_phone'] = $val;
        } elseif (str_contains($type, 'opening') || str_contains($type, 'افتتاح')) {
            if ($val = $get('openingType') ?? $get('opening_type')) $fields['opening_type'] = $val;
            if ($val = $get('openingTypeOther') ?? $get('opening_type_other')) $fields['opening_type_other'] = $val;
            if ($val = $get('openingDecorAreas') ?? $get('decor_areas')) {
                $fields['decor_areas'] = is_array($val) ? $val : [$val];
            }
            if ($val = $get('openingDecorOther') ?? $get('decor_other')) $fields['decor_other'] = $val;
            if ($val = $get('flowerType') ?? $get('flower_type')) $fields['flower_type'] = $val;
            if ($val = $get('themeColor') ?? $get('theme_color')) $fields['theme_color'] = $val;
            if ($val = $get('homeTheme') ?? $get('theme')) $fields['theme'] = $val;
            if ($val = $get('estimatedBudget') ?? $get('estimated_budget')) $fields['estimated_budget'] = $val;
        } elseif (str_contains($type, 'corporate') || str_contains($type, 'شركات')) {
            if ($val = $get('companyName') ?? $get('company_name')) $fields['company_name'] = $val;
            if ($val = $get('corporateType') ?? $get('corporate_type')) $fields['corporate_type'] = $val;
            if ($val = $get('corporateTypeOther') ?? $get('corporate_type_other')) $fields['corporate_type_other'] = $val;
            if ($val = $get('corporateDecorAreas') ?? $get('decor_areas')) {
                $fields['decor_areas'] = is_array($val) ? $val : [$val];
            }
            if ($val = $get('corporateDecorOther') ?? $get('decor_other')) $fields['decor_other'] = $val;
            if ($val = $get('flowerType') ?? $get('flower_type')) $fields['flower_type'] = $val;
            if ($val = $get('themeColor') ?? $get('theme_color')) $fields['theme_color'] = $val;
            if ($val = $get('homeTheme') ?? $get('theme')) $fields['theme'] = $val;
            if ($val = $get('estimatedBudget') ?? $get('estimated_budget')) $fields['estimated_budget'] = $val;
        } elseif (str_contains($type, 'other') || str_contains($type, 'استفسار')) {
            if ($val = $get('inquiryDetails') ?? $get('inquiry_details')) $fields['inquiry_details'] = $val;
        }

        return $fields;
    }

    /**
     * Format Guest Count table row or return empty string if not applicable.
     */
    public static function formatGuestCountRow(?string $guests, string $locale = 'en'): string
    {
        if (empty(trim((string)$guests))) {
            return '';
        }

        $guestsClean = htmlspecialchars(trim((string)$guests));

        if ($locale === 'ar') {
            return <<<HTML
      <tr>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 13px; font-weight: 600; color: #685D54; width: 35%; background: #FCFBF9;">عدد الضيوف المتوقع</td>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 14px; color: #201B18;">👥 {$guestsClean} ضيف</td>
      </tr>
HTML;
        }

        return <<<HTML
      <tr>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 13px; font-weight: 600; color: #685D54; width: 35%; background: #FCFBF9;">Guest Count</td>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 14px; color: #201B18;">👥 {$guestsClean} Guests</td>
      </tr>
HTML;
    }

    /**
     * Generate HTML table rows for all custom dynamic fields in the specified locale.
     */
    public static function formatCustomDetailsHtml(string $eventType, ?array $fields, string $locale = 'en'): string
    {
        if (empty($fields)) {
            return '';
        }

        $isAr = $locale === 'ar';
        $rows = [];

        // Dictionary for labels
        $labels = [
            'hospital_name' => ['en' => 'Hospital / Clinic', 'ar' => 'اسم المستشفى'],
            'decoration_option' => ['en' => 'Decoration Scope', 'ar' => 'التنسيق المرغوب'],
            'flower_type' => ['en' => 'Flower Type', 'ar' => 'نوع الورد'],
            'theme_color' => ['en' => 'Color Palette & Theme', 'ar' => 'الألوان وثيم التنسيق'],
            'bouquet_colors' => ['en' => 'Bouquet Colors', 'ar' => 'الألوان المفضلة للمسكة'],
            'bouquet_size' => ['en' => 'Bouquet Size', 'ar' => 'حجم المسكة'],
            'bouquet_style' => ['en' => 'Bouquet Style', 'ar' => 'شكل وتصميم المسكة'],
            'delivery_method' => ['en' => 'Delivery Method', 'ar' => 'طريقة الاستلام'],
            'delivery_address' => ['en' => 'Delivery Address', 'ar' => 'عنوان التوصيل'],
            'milkah_service' => ['en' => 'Requested Service', 'ar' => 'الخدمة المطلوبة'],
            'home_event_type' => ['en' => 'Occasion Type', 'ar' => 'نوع المناسبة'],
            'home_event_other_text' => ['en' => 'Occasion Specification', 'ar' => 'تحديد نوع المناسبة'],
            'decor_areas' => ['en' => 'Decorated Areas', 'ar' => 'تفاصيل مناطق التنسيق'],
            'decor_other' => ['en' => 'Other Decor Areas', 'ar' => 'مناطق تنسيق إضافية'],
            'theme' => ['en' => 'Concept / Theme', 'ar' => 'الثيم والمفهوم العام'],
            'company_name' => ['en' => 'Company / Establishment', 'ar' => 'اسم المنشأة / الشركة'],
            'supply_types' => ['en' => 'Supply Arrangement Types', 'ar' => 'نوع التوريد المطلوب'],
            'supply_other' => ['en' => 'Other Supply Requirements', 'ar' => 'تفاصيل توريد أخرى'],
            'supply_frequency' => ['en' => 'Supply Frequency', 'ar' => 'تكرار التوريد'],
            'supply_quantity' => ['en' => 'Quantity', 'ar' => 'الكمية المطلوبة'],
            'contract_duration' => ['en' => 'Contract Duration', 'ar' => 'مدة التعاقد'],
            'estimated_budget' => ['en' => 'Estimated Budget', 'ar' => 'الميزانية التقريبية'],
            'purchasing_contact_name' => ['en' => 'Procurement Contact Person', 'ar' => 'مسؤول المشتريات'],
            'purchasing_contact_phone' => ['en' => 'Procurement Phone', 'ar' => 'رقم تواصل المشتريات'],
            'opening_type' => ['en' => 'Opening Ceremony Type', 'ar' => 'نوع حفل الافتتاح'],
            'opening_type_other' => ['en' => 'Opening Type Specification', 'ar' => 'تحديد نوع الافتتاح'],
            'corporate_type' => ['en' => 'Corporate Event Type', 'ar' => 'نوع فعالية الشركة'],
            'corporate_type_other' => ['en' => 'Corporate Type Specification', 'ar' => 'تحديد نوع الفعالية'],
            'inquiry_details' => ['en' => 'Inquiry Details', 'ar' => 'تفاصيل الاستفسار'],
        ];

        // Dictionary for values
        $valuesMap = [
            'Fresh Flowers' => ['en' => 'Fresh Flowers', 'ar' => 'ورد طبيعي'],
            'Artificial Flowers' => ['en' => 'Artificial Flowers', 'ar' => 'ورد صناعي'],
            'A Mix of Fresh and Artificial Flowers' => ['en' => 'Mix of Fresh & Artificial Flowers', 'ar' => 'مكس بين الطبيعي والصناعي'],
            'Both' => ['en' => 'Both (Entrance & Hospitality Displays)', 'ar' => 'الاثنين معًا (مدخل الغرفة وستاندات الضيافة)'],
            'Hospital Room Entrance' => ['en' => 'Hospital Room Entrance', 'ar' => 'مدخل الغرفة'],
            'Hospitality Display Stands' => ['en' => 'Hospitality Display Stands', 'ar' => 'ستاندات للضيافة'],
            'Other Decorations or Special Requests' => ['en' => 'Special Custom Requests', 'ar' => 'تفاصيل وإضافات خاصة'],
            'Small' => ['en' => 'Small', 'ar' => 'صغيرة'],
            'Medium' => ['en' => 'Medium', 'ar' => 'متوسطة'],
            'Large' => ['en' => 'Large', 'ar' => 'كبيرة'],
            'Round' => ['en' => 'Round', 'ar' => 'دائرية'],
            'Long' => ['en' => 'Long', 'ar' => 'طويلة'],
            'Cascading' => ['en' => 'Cascading', 'ar' => 'منسدلة'],
            'Pickup' => ['en' => 'Pickup from Boutique', 'ar' => 'استلام من الفرع'],
            'Delivery' => ['en' => 'Delivery', 'ar' => 'توصيل'],
            'Milkah Ceremony Tray Styling' => ['en' => 'Milkah Ceremony Tray Styling', 'ar' => 'تنسيق عربية الملكة'],
            'Engagement Gifts' => ['en' => 'Engagement Gifts', 'ar' => 'هدايا الخطبة'],
            'Engagement Party' => ['en' => 'Engagement Party', 'ar' => 'خطوبة'],
            'Wedding' => ['en' => 'Wedding', 'ar' => 'زواج'],
            'Birthday' => ['en' => 'Birthday', 'ar' => 'عيد ميلاد'],
            'Graduation' => ['en' => 'Graduation', 'ar' => 'تخرج'],
            'Main Celebration Backdrop' => ['en' => 'Main Celebration Backdrop (Kosha)', 'ar' => 'كوشة / المنصة الرئيسية'],
            'Entrance' => ['en' => 'Entrance', 'ar' => 'المدخل'],
            'Vases and Table Arrangements' => ['en' => 'Vases & Table Arrangements', 'ar' => 'فازات وتنسيقات الطاولات'],
            'Other Decorations' => ['en' => 'Other Decorations', 'ar' => 'تفاصيل أخرى'],
            'Office Flower Arrangements' => ['en' => 'Office Flower Arrangements', 'ar' => 'باقات مكتبية'],
            'Reception Area Vases' => ['en' => 'Reception Area Vases', 'ar' => 'فازات استقبال'],
            'Recurring Floral Arrangements' => ['en' => 'Recurring Floral Arrangements', 'ar' => 'تنسيق دوري'],
            'Other Requirements' => ['en' => 'Other Requirements', 'ar' => 'احتياجات أخرى'],
            'Daily' => ['en' => 'Daily', 'ar' => 'يومي'],
            'Weekly' => ['en' => 'Weekly', 'ar' => 'أسبوعي'],
            'For a Specific Event' => ['en' => 'For a Specific Event', 'ar' => 'لمناسبة معينة'],
            'One-Time Order' => ['en' => 'One-Time Order', 'ar' => 'لمرة واحدة'],
            'Monthly' => ['en' => 'Monthly', 'ar' => 'شهري'],
            'Annually' => ['en' => 'Annually', 'ar' => 'سنوي'],
            'Shop Opening' => ['en' => 'Shop Opening', 'ar' => 'افتتاح محل'],
            'Café Opening' => ['en' => 'Café Opening', 'ar' => 'افتتاح كافيه'],
            'Corporate Office Opening' => ['en' => 'Corporate Office Opening', 'ar' => 'افتتاح شركة'],
            'Hotel Opening' => ['en' => 'Hotel Opening', 'ar' => 'افتتاح فندق'],
            'Grand Opening' => ['en' => 'Grand Opening', 'ar' => 'افتتاح'],
            'Conference' => ['en' => 'Conference', 'ar' => 'مؤتمر'],
            'Award Ceremony' => ['en' => 'Award Ceremony', 'ar' => 'حفل تكريم'],
            'Exhibition' => ['en' => 'Exhibition', 'ar' => 'معرض'],
            'Stage' => ['en' => 'Stage', 'ar' => 'منصة مسرح'],
            'Reception Area' => ['en' => 'Reception Area', 'ar' => 'استقبال'],
            'Tables' => ['en' => 'Tables', 'ar' => 'طاولات'],
            'Storefront or Building Façade' => ['en' => 'Storefront or Façade', 'ar' => 'واجهة المنشأة'],
            'Photo Area' => ['en' => 'Photo Area', 'ar' => 'منطقة تصوير'],
            'Full Venue Decoration' => ['en' => 'Full Venue Decoration', 'ar' => 'تنسيق كامل للمكان'],
            'غير محدد' => ['en' => 'Unspecified', 'ar' => 'غير محدد'],
        ];

        foreach ($fields as $key => $rawVal) {
            if ($rawVal === null || $rawVal === '' || (is_array($rawVal) && empty($rawVal))) {
                continue;
            }

            $label = $labels[$key][$locale] ?? ucwords(str_replace('_', ' ', $key));

            // Format value
            if (is_array($rawVal)) {
                $translatedItems = array_map(function ($item) use ($valuesMap, $locale) {
                    return $valuesMap[$item][$locale] ?? $item;
                }, $rawVal);
                $valStr = implode($isAr ? '، ' : ', ', $translatedItems);
            } else {
                $valStr = $valuesMap[$rawVal][$locale] ?? (string)$rawVal;
            }

            $valSafe = nl2br(htmlspecialchars($valStr));

            $rows[] = <<<HTML
      <tr>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 13px; font-weight: 600; color: #685D54; width: 35%; background: #FCFBF9;">{$label}</td>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 14px; color: #201B18;">{$valSafe}</td>
      </tr>
HTML;
        }

        return implode("\n", $rows);
    }
}
