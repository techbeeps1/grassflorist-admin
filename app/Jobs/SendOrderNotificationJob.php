<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\Notifications\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendOrderNotificationJob implements ShouldQueue
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
    public int $backoff = 10;

    public function __construct(
        public int $orderId,
        public string $eventKey,
        public ?string $preferredLocale = null
    ) {}

    public function handle(NotificationService $notificationService): void
    {
        try {
            $order = Order::find($this->orderId);
            if (!$order) {
                Log::warning("[SendOrderNotificationJob] Order #{$this->orderId} not found, skipping.");
                return;
            }

            Log::info("[SendOrderNotificationJob] Processing background notification for Order #{$order->order_number} ({$this->eventKey}).");
            $notificationService->sendOrderNotificationSync($order, $this->eventKey, $this->preferredLocale);
        } catch (\Throwable $e) {
            Log::error("[SendOrderNotificationJob] Failed for Order #{$this->orderId} ({$this->eventKey}): " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
