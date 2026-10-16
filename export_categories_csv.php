<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Category;

// Fetch all categories with parent and products count
$categories = Category::with(['parent', 'products'])->orderBy('parent_id')->orderBy('id')->get();

// Build parent lookup and path helper
$allById = $categories->keyBy('id');

function getCategoryName($category, $locale = 'en') {
    if (!$category) return '';
    if (method_exists($category, 'getTranslation')) {
        $val = $category->getTranslation('name', $locale, false);
        if (!empty($val)) return $val;
    }
    $raw = $category->getAttributes()['name'] ?? '';
    if (is_string($raw) && (str_starts_with($raw, '{') || str_starts_with($raw, '['))) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded[$locale] ?? $decoded['en'] ?? $decoded['ar'] ?? '';
        }
    }
    return (string)$raw;
}

function getTranslatableField($category, $field, $locale = 'en') {
    if (!$category) return '';
    if (method_exists($category, 'getTranslation')) {
        $val = $category->getTranslation($field, $locale, false);
        if (!empty($val)) return $val;
    }
    $raw = $category->getAttributes()[$field] ?? '';
    if (is_string($raw) && (str_starts_with($raw, '{') || str_starts_with($raw, '['))) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded[$locale] ?? $decoded['en'] ?? $decoded['ar'] ?? '';
        }
    }
    return (string)$raw;
}

function buildHierarchyPath($category, $allById, $locale = 'en') {
    $parts = [];
    $curr = $category;
    while ($curr) {
        array_unshift($parts, getCategoryName($curr, $locale));
        $curr = $curr->parent_id && isset($allById[$curr->parent_id]) ? $allById[$curr->parent_id] : null;
    }
    return implode(' > ', $parts);
}

// Prepare CSV headers
$headers = [
    'ID',
    'Type',
    'Category Name (EN)',
    'Category Name (AR)',
    'Slug (EN)',
    'Slug (AR)',
    'Parent ID',
    'Parent Name (EN)',
    'Parent Name (AR)',
    'Hierarchy Path (EN)',
    'Hierarchy Path (AR)',
    'Visibility',
    'Products Count',
    'Image Path',
    'Image Full URL',
    'Meta Title (EN)',
    'Meta Title (AR)',
    'Meta Description (EN)',
    'Meta Description (AR)',
    'Meta Keywords (EN)',
    'Meta Keywords (AR)',
    'Description (EN)',
    'Description (AR)',
    'Source ID (Odoo)',
    'Created At',
    'Updated At',
];

// Build product counts per category (checking both category_product pivot and products.category_id column)
$pivotCounts = \DB::table('category_product')
    ->select('category_id', \DB::raw('count(*) as cnt'))
    ->groupBy('category_id')
    ->pluck('cnt', 'category_id')
    ->toArray();

// Also count products where category_id json column contains the category
$allProducts = \App\Models\Product::select('id', 'category_id')->get();
$jsonCounts = [];
foreach ($allProducts as $p) {
    $cids = $p->category_id;
    if (is_array($cids)) {
        foreach ($cids as $cid) {
            $cid = (int)$cid;
            $jsonCounts[$cid] = ($jsonCounts[$cid] ?? 0) + 1;
        }
    } elseif (is_numeric($cids)) {
        $cid = (int)$cids;
        $jsonCounts[$cid] = ($jsonCounts[$cid] ?? 0) + 1;
    }
}

// Function to clean text for single-line display in CSV
function cleanCsvText(?string $text): string {
    if (!$text) return '';
    $text = strip_tags($text);
    $text = preg_replace('/[\r\n\t]+/', ' ', $text);
    return trim(preg_replace('/\s{2,}/', ' ', $text));
}

// Target export paths
$workspaceExportPath = dirname(__DIR__) . '/product_categories_and_subcategories.csv';
$publicExportDir = __DIR__ . '/public/exports';
if (!is_dir($publicExportDir)) {
    mkdir($publicExportDir, 0755, true);
}
$publicExportPath = $publicExportDir . '/product_categories_and_subcategories.csv';

// Build rows
$rows = [];
foreach ($categories as $cat) {
    $parent = $cat->parent_id ? ($allById[$cat->parent_id] ?? null) : null;
    $type = $cat->parent_id ? 'Subcategory' : 'Root Category';
    
    $nameEn = getCategoryName($cat, 'en');
    $nameAr = getCategoryName($cat, 'ar');
    $parentNameEn = $parent ? getCategoryName($parent, 'en') : '';
    $parentNameAr = $parent ? getCategoryName($parent, 'ar') : '';

    $pathEn = buildHierarchyPath($cat, $allById, 'en');
    $pathAr = buildHierarchyPath($cat, $allById, 'ar');

    $imageUrl = $cat->image_url ?? ($cat->cat_image ? url('storage/' . $cat->cat_image) : '');

    // Best product count: maximum of pivot count or json column count
    $productCount = max($pivotCounts[$cat->id] ?? 0, $jsonCounts[$cat->id] ?? 0);

    $rows[] = [
        $cat->id,
        $type,
        $nameEn,
        $nameAr,
        $cat->slug,
        $cat->slug_ar ?? '',
        $cat->parent_id ?: '',
        $parentNameEn,
        $parentNameAr,
        $pathEn,
        $pathAr,
        $cat->is_visible ? 'Visible (Active)' : 'Hidden (Inactive)',
        $productCount,
        $cat->cat_image ?? '',
        $imageUrl,
        cleanCsvText(getTranslatableField($cat, 'meta_tag_title', 'en')),
        cleanCsvText(getTranslatableField($cat, 'meta_tag_title', 'ar')),
        cleanCsvText(getTranslatableField($cat, 'meta_tag_description', 'en')),
        cleanCsvText(getTranslatableField($cat, 'meta_tag_description', 'ar')),
        cleanCsvText(getTranslatableField($cat, 'meta_tag_keywords', 'en')),
        cleanCsvText(getTranslatableField($cat, 'meta_tag_keywords', 'ar')),
        cleanCsvText(getTranslatableField($cat, 'description', 'en')),
        cleanCsvText(getTranslatableField($cat, 'description', 'ar')),
        $cat->source_id ?? '',
        $cat->created_at ? $cat->created_at->format('Y-m-d H:i:s') : '',
        $cat->updated_at ? $cat->updated_at->format('Y-m-d H:i:s') : '',
    ];
}

// Function to write CSV with UTF-8 BOM
function writeCsvWithBom($filePath, $headers, $rows) {
    $fp = fopen($filePath, 'w');
    // Add UTF-8 BOM so Excel opens Arabic and special characters properly
    fwrite($fp, "\xEF\xBB\xBF");
    fputcsv($fp, $headers);
    foreach ($rows as $row) {
        fputcsv($fp, $row);
    }
    fclose($fp);
}

writeCsvWithBom($workspaceExportPath, $headers, $rows);
writeCsvWithBom($publicExportPath, $headers, $rows);

echo "SUCCESS: Exported " . count($rows) . " categories and subcategories.\n";
echo "Saved to: {$workspaceExportPath}\n";
echo "Also saved to: {$publicExportPath}\n";
