<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            DB::statement("ALTER TABLE `wishlists` MODIFY `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT;");
        } catch (\Exception $e) {
            // Ignore if already auto_increment
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
