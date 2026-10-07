<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Services\Payments\PaymentManager;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ReconcilePendingPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:reconcile-pending';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile and auto-recover pending payment orders by directly querying Gateway APIs (HyperPay, Tabby, Tamara)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting Payment Auto-Recovery and Reconciliation worker...');

        // Pick orders created between 5 minutes ago and 24 hours ago that are still pending
        $pendingOrders = Order::where('payment_status', 'pending')
            ->where('status', '!=', 'cancelled')
            ->where('created_at', '<=', Carbon::now()->subMinutes(5))
            ->where('created_at', '>=', Carbon::now()->subHours(24))
            ->orderBy('id', 'desc')
            ->get();

        $this->info("Found {$pendingOrders->count()} pending orders to verify.");

        $reconciledCount = 0;
        $cancelledCount = 0;

        foreach ($pendingOrders as $order) {
            $gatewayCode = $order->payment_method ?? 'hyperpay';

            // Skip cash on delivery
            if (in_array(strtolower($gatewayCode), ['cod', 'cash_on_delivery'])) {
                continue;
            }

            $service = PaymentManager::resolveService($gatewayCode);
            if (!$service) {
                continue;
            }

            $referenceId = $order->razorpay_payment_id ?? $order->order_number ?? (string)$order->id;

            try {
                $this->line("Querying gateway [{$gatewayCode}] for Order #{$order->id} (Ref: {$referenceId})...");
                $res = $service->verifyPayment($referenceId);

                if (!empty($res['is_paid'])) {
                    // Payment was captured on gateway!
                    $this->info("✅ [RECOVERED] Order #{$order->id} was PAID on gateway! Updating order status...");
                    PaymentManager::markOrderAsPaid($order, $res['transaction_id'] ?? $referenceId, $gatewayCode, $res['raw'] ?? [], 'CRON_RECONCILED');
                    $reconciledCount++;
                } else {
                    // If order is older than 2 hours and not paid, auto-expire / cancel
                    if ($order->created_at->lessThan(Carbon::now()->subHours(2))) {
                        $this->warn("⚠️ Order #{$order->id} expired without payment after 2 hours. Marking as cancelled.");
                        $order->status = 'cancelled';
                        $order->cancellation_reason = 'Payment session expired without completion.';
                        $order->save();
                        $cancelledCount++;
                    }
                }
            } catch (\Exception $e) {
                Log::error("Payment reconciliation error for Order #{$order->id}: " . $e->getMessage());
                $this->error("Error checking Order #{$order->id}: " . $e->getMessage());
            }
        }

        $this->info("Reconciliation finished: {$reconciledCount} orders recovered & marked paid, {$cancelledCount} expired orders cancelled.");

        return Command::SUCCESS;
    }
}
