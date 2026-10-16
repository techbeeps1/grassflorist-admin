<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add Webhook configuration fields to global_settings
        if (Schema::hasTable('global_settings')) {
            Schema::table('global_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('global_settings', 'webhook_enabled')) {
                    $table->boolean('webhook_enabled')->default(false)->after('updated_by');
                }
                if (!Schema::hasColumn('global_settings', 'webhook_default_url')) {
                    $table->text('webhook_default_url')->nullable()->after('webhook_enabled');
                }
                if (!Schema::hasColumn('global_settings', 'webhook_secret')) {
                    $table->string('webhook_secret')->nullable()->after('webhook_default_url');
                }
                if (!Schema::hasColumn('global_settings', 'webhook_timeout')) {
                    $table->integer('webhook_timeout')->default(10)->after('webhook_secret');
                }
                if (!Schema::hasColumn('global_settings', 'webhook_events')) {
                    $table->json('webhook_events')->nullable()->after('webhook_timeout');
                }
                if (!Schema::hasColumn('global_settings', 'webhook_headers')) {
                    $table->json('webhook_headers')->nullable()->after('webhook_events');
                }
                if (!Schema::hasColumn('global_settings', 'webhook_order_created_url')) {
                    $table->text('webhook_order_created_url')->nullable()->after('webhook_headers');
                }
                if (!Schema::hasColumn('global_settings', 'webhook_order_status_url')) {
                    $table->text('webhook_order_status_url')->nullable()->after('webhook_order_created_url');
                }
                if (!Schema::hasColumn('global_settings', 'webhook_user_registered_url')) {
                    $table->text('webhook_user_registered_url')->nullable()->after('webhook_order_status_url');
                }
                if (!Schema::hasColumn('global_settings', 'webhook_subscription_created_url')) {
                    $table->text('webhook_subscription_created_url')->nullable()->after('webhook_user_registered_url');
                }
                if (!Schema::hasColumn('global_settings', 'webhook_newsletter_url')) {
                    $table->text('webhook_newsletter_url')->nullable()->after('webhook_subscription_created_url');
                }
                if (!Schema::hasColumn('global_settings', 'webhook_password_changed_url')) {
                    $table->text('webhook_password_changed_url')->nullable()->after('webhook_newsletter_url');
                }
            });
        }

        // 2. Create webhook_logs table
        if (!Schema::hasTable('webhook_logs')) {
            Schema::create('webhook_logs', function (Blueprint $table) {
                $table->id();
                $table->string('event', 100)->index();
                $table->text('url');
                $table->string('method', 10)->default('POST');
                $table->json('headers')->nullable();
                $table->longText('payload')->nullable();
                $table->integer('response_status')->nullable()->index();
                $table->json('response_headers')->nullable();
                $table->longText('response_body')->nullable();
                $table->integer('duration_ms')->nullable();
                $table->string('status', 30)->default('pending')->index();
                $table->text('error_message')->nullable();
                $table->integer('attempts')->default(1);
                $table->string('reference_id', 150)->nullable()->index();
                $table->string('reference_type', 150)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');

        if (Schema::hasTable('global_settings')) {
            Schema::table('global_settings', function (Blueprint $table) {
                $columns = [
                    'webhook_enabled',
                    'webhook_default_url',
                    'webhook_secret',
                    'webhook_timeout',
                    'webhook_events',
                    'webhook_headers',
                    'webhook_order_created_url',
                    'webhook_order_status_url',
                    'webhook_user_registered_url',
                    'webhook_subscription_created_url',
                    'webhook_newsletter_url',
                    'webhook_password_changed_url',
                ];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('global_settings', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
