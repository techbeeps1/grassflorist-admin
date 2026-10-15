<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

$tables = Schema::getTableListing();
$customTables = array_values(array_filter($tables, fn($t) => !str_contains($t, 'mod29_')));
echo "=== Custom Tables ===\n" . json_encode($customTables, JSON_PRETTY_PRINT) . "\n\n";

echo "=== Product Columns ===\n";
echo json_encode(Schema::getColumnListing('products'), JSON_PRETTY_PRINT) . "\n\n";

// Check if there are wc attribute tables
$wcAttrTables = array_values(array_filter($tables, fn($t) => str_contains($t, 'attribute')));
echo "=== Attribute Tables in DB ===\n" . json_encode($wcAttrTables, JSON_PRETTY_PRINT) . "\n\n";

// Check sample product attributes from products table
$prod = App\Models\Product::first();
echo "=== Sample Product Attributes field or meta_data ===\n";
if ($prod) {
    echo "attributes: " . json_encode($prod->attributes ?? null) . "\n";
    echo "meta_data: " . json_encode($prod->meta_data ?? null, JSON_PRETTY_PRINT) . "\n";
}

// Check mod29_woocommerce_attribute_taxonomies
try {
    $tax = DB::table('mod29_woocommerce_attribute_taxonomies')->get();
    echo "=== WooCommerce Attribute Taxonomies ===\n" . json_encode($tax, JSON_PRETTY_PRINT) . "\n";
} catch (\Exception $e) {
    echo "Error checking wc attribute taxonomies: " . $e->getMessage() . "\n";
}
