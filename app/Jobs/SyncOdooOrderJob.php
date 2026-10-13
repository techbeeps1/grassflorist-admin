<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\Odoo\OdooService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncOdooOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Timeout in seconds before failing.
     */
    public int $timeout = 60;

    /**
     * Backoff delay between retries in seconds.
     */
    public int $backoff = 15;

    public function __construct(public int $orderId) {}

    public function handle(OdooService $odooService): void
    {
        try {
            $order = Order::find($this->orderId);
            if (!$order) {
                Log::warning("[SyncOdooOrderJob] Order #{$this->orderId} not found, skipping.");
                return;
            }

            if ($order->odoo_sync_status !== 'synced') {
                Log::info("[SyncOdooOrderJob] Syncing Order #{$order->order_number} to Odoo in background.");
                $odooService->syncOrder($order, 'system_auto');
            }
        } catch (\Throwable $e) {
            Log::error("[SyncOdooOrderJob] Error for Order #{$this->orderId}: " . $e->getMessage());
            throw $e;
        }
    }
}
