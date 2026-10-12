<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('global_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('global_settings', 'email_custom_logo_url')) {
                $table->string('email_custom_logo_url', 500)->nullable()->after('email_logo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('global_settings', function (Blueprint $table) {
            $table->dropColumn('email_custom_logo_url');
        });
    }
};
