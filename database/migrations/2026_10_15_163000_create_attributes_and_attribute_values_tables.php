<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Attributes definition table (e.g., Color, Size, Flower Type, Occasion)
        if (!Schema::hasTable('attributes')) {
            Schema::create('attributes', function (Blueprint $table) {
                $table->id();
                $table->json('name'); // {"en": "Color", "ar": "اللون"}
                $table->string('slug')->unique(); // e.g. 'color'
                $table->string('type')->default('select'); // 'color', 'select', 'button'
                $table->boolean('is_filterable')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        // 2. Attribute values table (e.g., White, Pink, Red, Gold)
        if (!Schema::hasTable('attribute_values')) {
            Schema::create('attribute_values', function (Blueprint $table) {
                $table->id();
                $table->foreignId('attribute_id')->constrained('attributes')->cascadeOnDelete();
                $table->json('value'); // {"en": "White", "ar": "أبيض"}
                $table->string('slug'); // 'white'
                $table->string('color_code')->nullable(); // '#FFFFFF'
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->index(['attribute_id', 'slug']);
            });
        }

        // 3. Product attribute value pivot
        if (!Schema::hasTable('product_attribute_value')) {
            Schema::create('product_attribute_value', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('attribute_id')->constrained('attributes')->cascadeOnDelete();
                $table->foreignId('attribute_value_id')->constrained('attribute_values')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['product_id', 'attribute_value_id'], 'prod_attr_val_unique');
                $table->index(['attribute_id', 'attribute_value_id'], 'attr_val_index');
            });
        }

        // 4. Seed initial Color attribute and values
        $this->seedInitialAttributes();
    }

    /**
     * Seed initial standard attributes and auto-link existing products.
     */
    protected function seedInitialAttributes(): void
    {
        // Check if 'color' attribute already exists
        $colorAttr = DB::table('attributes')->where('slug', 'color')->first();
        if (!$colorAttr) {
            $colorAttrId = DB::table('attributes')->insertGetId([
                'name' => json_encode(['en' => 'Color', 'ar' => 'اللون'], JSON_UNESCAPED_UNICODE),
                'slug' => 'color',
                'type' => 'color',
                'is_filterable' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $colorAttrId = $colorAttr->id;
        }

        $standardColors = [
            ['en' => 'White', 'ar' => 'أبيض', 'slug' => 'white', 'hex' => '#FFFFFF', 'cat_id' => 12],
            ['en' => 'Pink', 'ar' => 'وردي', 'slug' => 'pink', 'hex' => '#FFC0CB', 'cat_id' => 13],
            ['en' => 'Gold', 'ar' => 'ذهبي', 'slug' => 'gold', 'hex' => '#D4AF37', 'cat_id' => null],
            ['en' => 'Red', 'ar' => 'أحمر', 'slug' => 'red', 'hex' => '#E50914', 'cat_id' => 8],
            ['en' => 'Purple', 'ar' => 'بنفسجي', 'slug' => 'purple', 'hex' => '#800080', 'cat_id' => 10],
            ['en' => 'Fuchsia', 'ar' => 'فوشيا', 'slug' => 'fuchsia', 'hex' => '#FF00FF', 'cat_id' => null],
            ['en' => 'Yellow Gold', 'ar' => 'ذهب أصفر', 'slug' => 'yellow-gold', 'hex' => '#E6CA65', 'cat_id' => null],
            ['en' => 'Silver', 'ar' => 'فضي', 'slug' => 'silver', 'hex' => '#C0C0C0', 'cat_id' => null],
            ['en' => 'Yellow', 'ar' => 'أصفر', 'slug' => 'yellow', 'hex' => '#FFFF00', 'cat_id' => 9],
            ['en' => 'Black', 'ar' => 'أسود', 'slug' => 'black', 'hex' => '#1A1A1A', 'cat_id' => null],
            ['en' => 'Peach', 'ar' => 'بيج', 'slug' => 'peach', 'hex' => '#FFE5B4', 'cat_id' => 14],
            ['en' => 'Rose Gold', 'ar' => 'ذهب وردي', 'slug' => 'rose-gold', 'hex' => '#B76E79', 'cat_id' => null],
            ['en' => 'Orange', 'ar' => 'برتقالي', 'slug' => 'orange', 'hex' => '#FFA500', 'cat_id' => 11],
            ['en' => 'Green', 'ar' => 'أخضر', 'slug' => 'green', 'hex' => '#2E7D32', 'cat_id' => 15],
            ['en' => 'Mixed', 'ar' => 'منوع', 'slug' => 'mixed', 'hex' => '#4A90E2', 'cat_id' => 16],
        ];

        $valIdMap = [];
        $order = 1;
        foreach ($standardColors as $sc) {
            $existing = DB::table('attribute_values')
                ->where('attribute_id', $colorAttrId)
                ->where('slug', $sc['slug'])
                ->first();

            if ($existing) {
                $valIdMap[$sc['slug']] = $existing->id;
            } else {
                $valId = DB::table('attribute_values')->insertGetId([
                    'attribute_id' => $colorAttrId,
                    'value' => json_encode(['en' => $sc['en'], 'ar' => $sc['ar']], JSON_UNESCAPED_UNICODE),
                    'slug' => $sc['slug'],
                    'color_code' => $sc['hex'],
                    'sort_order' => $order++,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $valIdMap[$sc['slug']] = $valId;
            }
        }

        // Auto-link existing products based on category IDs or title
        $products = DB::table('products')->select('id', 'name', 'category_id')->get();
        $links = [];

        foreach ($products as $p) {
            $catIds = [];
            if (!empty($p->category_id)) {
                $decoded = json_decode($p->category_id, true);
                if (is_array($decoded)) {
                    $catIds = array_map('intval', $decoded);
                }
            }

            $nameStr = is_string($p->name) ? $p->name : json_encode($p->name);
            $lowerName = strtolower($nameStr);

            foreach ($standardColors as $sc) {
                $match = false;
                if ($sc['cat_id'] && in_array((int)$sc['cat_id'], $catIds, true)) {
                    $match = true;
                } elseif (str_contains($lowerName, strtolower($sc['en'])) || str_contains($lowerName, $sc['ar'])) {
                    $match = true;
                }

                if ($match && isset($valIdMap[$sc['slug']])) {
                    $links[] = [
                        'product_id' => $p->id,
                        'attribute_id' => $colorAttrId,
                        'attribute_value_id' => $valIdMap[$sc['slug']],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        if (!empty($links)) {
            // Insert in chunks with ignore
            foreach (array_chunk($links, 200) as $chunk) {
                DB::table('product_attribute_value')->insertOrIgnore($chunk);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_attribute_value');
        Schema::dropIfExists('attribute_values');
        Schema::dropIfExists('attributes');
    }
};
