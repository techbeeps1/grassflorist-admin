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
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->json('author_name');          // Bilingual: en, ar
            $table->json('city')->nullable();     // Bilingual: en, ar (e.g. Riyadh, Jeddah, Khobar)
            $table->json('occasion_tag')->nullable(); // Bilingual: en, ar (e.g. Wedding Anniversary, Birthday)
            $table->json('content');              // Bilingual: en, ar
            $table->decimal('rating', 2, 1)->default(5.0); // 1.0 to 5.0
            $table->string('avatar')->nullable();
            $table->boolean('is_verified')->default(true);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->json('category')->nullable(); // Bilingual: en, ar (e.g. Ordering & Delivery, Payments)
            $table->json('question');             // Bilingual: en, ar
            $table->json('answer');               // Bilingual: en, ar
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('testimonials');
    }
};
