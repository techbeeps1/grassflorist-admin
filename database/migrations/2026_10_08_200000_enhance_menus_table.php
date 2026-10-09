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
        Schema::table('menus', function (Blueprint $table) {
            if (!Schema::hasColumn('menus', 'location')) {
                $table->string('location')->nullable()->after('slug');
            }
            if (!Schema::hasColumn('menus', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('location');
            }
            if (!Schema::hasColumn('menus', 'items')) {
                $table->json('items')->nullable()->after('is_active');
            }
            if (!Schema::hasColumn('menus', 'settings')) {
                $table->json('settings')->nullable()->after('items');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->dropColumn(['location', 'is_active', 'items', 'settings']);
        });
    }
};
