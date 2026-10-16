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
        if (Schema::hasTable('contact_inquiries') && !Schema::hasColumn('contact_inquiries', 'locale')) {
            Schema::table('contact_inquiries', function (Blueprint $table) {
                $table->string('locale', 10)->default('ar')->after('status')->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('contact_inquiries') && Schema::hasColumn('contact_inquiries', 'locale')) {
            Schema::table('contact_inquiries', function (Blueprint $table) {
                $table->dropColumn('locale');
            });
        }
    }
};
