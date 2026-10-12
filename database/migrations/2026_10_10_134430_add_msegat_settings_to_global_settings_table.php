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
        Schema::table('global_settings', function (Blueprint $table) {
            $table->boolean('msegat_sms_enabled')->default(false)->after('site_name_ar');
            $table->string('msegat_username')->nullable()->after('msegat_sms_enabled');
            $table->string('msegat_api_key')->nullable()->after('msegat_username');
            $table->string('msegat_user_sender')->nullable()->after('msegat_api_key');
            $table->string('admin_notification_phone')->nullable()->after('msegat_user_sender');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('global_settings', function (Blueprint $table) {
            $table->dropColumn([
                'msegat_sms_enabled',
                'msegat_username',
                'msegat_api_key',
                'msegat_user_sender',
                'admin_notification_phone',
            ]);
        });
    }
};
