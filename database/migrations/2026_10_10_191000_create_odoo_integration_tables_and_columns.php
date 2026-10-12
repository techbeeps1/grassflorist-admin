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
        // 1. Add Odoo settings to global_settings table
        Schema::table('global_settings', function (Blueprint $table) {
            $table->boolean('odoo_enabled')->default(false)->after('msegat_sms_enabled');
            $table->string('odoo_api_url', 500)->default('https://odoo.grassflorist.com/website/api/order/upsert')->after('odoo_enabled');
            $table->text('odoo_api_token')->nullable()->after('odoo_api_url');
            $table->text('odoo_bypass_emails')->nullable()->after('odoo_api_token');
            $table->text('odoo_bypass_domains')->nullable()->after('odoo_bypass_emails');
            $table->text('odoo_bypass_user_ids')->nullable()->after('odoo_bypass_domains');
            $table->boolean('odoo_auto_sync_on_paid')->default(true)->after('odoo_bypass_user_ids');
        });

        // 2. Add Odoo tracking columns to orders table
        Schema::table('orders', function (Blueprint $table) {
            $table->string('odoo_sync_status', 50)->default('pending')->index()->after('payment_status');
            $table->timestamp('odoo_synced_at')->nullable()->after('odoo_sync_status');
            $table->string('odoo_invoice_pdf_path', 500)->nullable()->after('odoo_synced_at');
            $table->string('odoo_invoice_pdf_url', 500)->nullable()->after('odoo_invoice_pdf_path');
            $table->text('odoo_error')->nullable()->after('odoo_invoice_pdf_url');
        });

        // 3. Create dedicated odoo_sync_logs table
        Schema::create('odoo_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('order_number', 100)->index();
            $table->string('status', 50)->index(); // 'success', 'failed', 'bypassed'
            $table->string('request_url', 500)->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable(); // base64 stripped out
            $table->string('invoice_pdf_url', 500)->nullable();
            $table->text('error_message')->nullable();
            $table->string('synced_by', 100)->default('system');
            $table->timestamps();
        });

        // Set default values in global_settings row 1 if exists
        \Illuminate\Support\Facades\DB::table('global_settings')->where('id', 1)->update([
            'odoo_api_token' => '9b8a82e926bfc65571587fab53195f9d667169',
            'odoo_bypass_domains' => "techbeeps.com\ntechbeeps.co.in",
            'odoo_bypass_user_ids' => '3254, 6350',
            'odoo_bypass_emails' => 'gurjeet@techbeeps.com',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('odoo_sync_logs');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'odoo_sync_status',
                'odoo_synced_at',
                'odoo_invoice_pdf_path',
                'odoo_invoice_pdf_url',
                'odoo_error',
            ]);
        });

        Schema::table('global_settings', function (Blueprint $table) {
            $table->dropColumn([
                'odoo_enabled',
                'odoo_api_url',
                'odoo_api_token',
                'odoo_bypass_emails',
                'odoo_bypass_domains',
                'odoo_bypass_user_ids',
                'odoo_auto_sync_on_paid',
            ]);
        });
    }
};
