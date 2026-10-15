<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->integer('sort_order')->default(0)->after('is_visible')->index();
        });

        // Initialize sort_order with id order
        $categories = \App\Models\Category::orderBy('id')->get();
        foreach ($categories as $index => $category) {
            $category->timestamps = false;
            $category->sort_order = $index + 1;
            $category->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
