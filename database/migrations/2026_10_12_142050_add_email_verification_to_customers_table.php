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
        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable()->after('email');
            }
            if (!Schema::hasColumn('customers', 'email_verification_token')) {
                $table->string('email_verification_token', 100)->nullable()->after('email_verified_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'email_verification_token')) {
                $table->dropColumn('email_verification_token');
            }
            if (Schema::hasColumn('customers', 'email_verified_at')) {
                $table->dropColumn('email_verified_at');
            }
        });
    }
};
