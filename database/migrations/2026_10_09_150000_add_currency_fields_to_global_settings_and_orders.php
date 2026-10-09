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
        if (Schema::hasTable('global_settings')) {
            Schema::table('global_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('global_settings', 'sar_to_usd_rate')) {
                    $table->decimal('sar_to_usd_rate', 10, 4)->default(0.2667)->after('vat_registration_number');
                }
                if (!Schema::hasColumn('global_settings', 'last_currency_rate_fetch_at')) {
                    $table->timestamp('last_currency_rate_fetch_at')->nullable()->after('sar_to_usd_rate');
                }
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (!Schema::hasColumn('orders', 'exchange_rate')) {
                    $table->decimal('exchange_rate', 10, 4)->default(1.0000)->after('currency');
                }
                if (!Schema::hasColumn('orders', 'currency_amount')) {
                    $table->decimal('currency_amount', 12, 2)->nullable()->after('exchange_rate');
                }
                if (!Schema::hasColumn('orders', 'sar_amount')) {
                    $table->decimal('sar_amount', 12, 2)->nullable()->after('currency_amount');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('global_settings')) {
            Schema::table('global_settings', function (Blueprint $table) {
                if (Schema::hasColumn('global_settings', 'sar_to_usd_rate')) {
                    $table->dropColumn('sar_to_usd_rate');
                }
                if (Schema::hasColumn('global_settings', 'last_currency_rate_fetch_at')) {
                    $table->dropColumn('last_currency_rate_fetch_at');
                }
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (Schema::hasColumn('orders', 'exchange_rate')) {
                    $table->dropColumn('exchange_rate');
                }
                if (Schema::hasColumn('orders', 'currency_amount')) {
                    $table->dropColumn('currency_amount');
                }
                if (Schema::hasColumn('orders', 'sar_amount')) {
                    $table->dropColumn('sar_amount');
                }
            });
        }
    }
};
