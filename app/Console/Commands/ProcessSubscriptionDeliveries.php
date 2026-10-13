<?php

namespace App\Console\Commands;

use App\Models\GlobalSetting;
use App\Models\Subscription;
use App\Models\SubscriptionDelivery;
use App\Services\Payments\PaymentManager;
use App\Services\Subscriptions\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessSubscriptionDeliveries extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'subscriptions:process-deliveries {--date= : Custom target delivery date (YYYY-MM-DD)} {--lead-days=1 : Number of days in advance to process upcoming deliveries (default: 1 day before delivery)} {--dry-run : Simulate without writing changes}';

    /**
     * The console command description.
     */
    protected $description = 'Process upcoming recurring subscription deliveries 1 day in advance, generate orders, and charge payment tokens.';

    /**
     * Execute the console command.
     */
    public function handle(SubscriptionService $subscriptionService)
    {
        $timezone = GlobalSetting::current()->timezone ?: 'Asia/Riyadh';
        $leadDays = (int)($this->option('lead-days') ?? 1);
        $targetDateStr = $this->option('date') ?: Carbon::now($timezone)->addDays($leadDays)->toDateString();
        $isDryRun = (bool)$this->option('dry-run');

        $this->info("=== Processing Subscription Deliveries ({$leadDays} Day Advance Lead Time | Target Date: {$targetDateStr} | TZ: {$timezone}) ===");
        if ($isDryRun) {
            $this->warn('[DRY-RUN MODE ENABLED - No changes will be persisted]');
        }

        // Query scheduled deliveries due on or before target date whose subscription is active
        $deliveries = SubscriptionDelivery::with(['subscription.customer', 'subscription.product'])
            ->whereHas('subscription', function ($q) {
                $q->where('status', 'active');
            })
            ->where('delivery_status', 'scheduled')
            ->whereDate('scheduled_date', '<=', $targetDateStr)
            ->orderBy('scheduled_date', 'asc')
            ->get();

        $count = $deliveries->count();
        $this->info("Found {$count} eligible deliveries due for processing.");

        if ($count === 0) {
            $this->info("No deliveries to process today. Exiting.");
            return 0;
        }

        $processedCount = 0;
        $successCount = 0;
        $failedCount = 0;

        foreach ($deliveries as $delivery) {
            $subscription = $delivery->subscription;
            $effectiveDate = $delivery->scheduled_date->toDateString();

            $this->line("Processing Sub #{$subscription->subscription_number} | Delivery #{$delivery->delivery_number} | Due: {$effectiveDate}");

            if ($isDryRun) {
                $processedCount++;
                continue;
            }

            try {
                // 1. Generate Order for this delivery
                $order = $subscriptionService->generateOrderForDelivery($delivery);

                // 2. Process automated token charge if payment token exists
                $paymentSuccess = false;
                $gateway = $subscription->payment_gateway ?? 'hyperpay';
                $token = $subscription->payment_token;

                if (!empty($token)) {
                    $this->line("   -> Charging recurring token via [{$gateway}]...");

                    // In Phase 3, PaymentManager executes chargeRecurringToken
                    $paymentResult = PaymentManager::chargeRecurringToken($order, $token, $gateway);

                    if (!empty($paymentResult['success'])) {
                        $paymentSuccess = true;
                        $transactionId = $paymentResult['transaction_id'] ?? ('TXN_' . time());
                        PaymentManager::markOrderAsPaid($order, $transactionId, $gateway, $paymentResult, 'RECURRING');

                        $delivery->update([
                            'delivery_status' => 'processing',
                            'payment_status' => 'paid',
                            'payment_reference' => $transactionId,
                        ]);

                        $subscription->update([
                            'consecutive_failed_payments' => 0,
                            'last_payment_failed_at' => null,
                            'last_payment_error' => null,
                        ]);

                        $subscription->logActivity(
                            'payment_success',
                            "Recurring payment of SAR {$order->total_amount} succeeded for delivery #{$delivery->delivery_number}.",
                            ['order_id' => $order->id, 'transaction_id' => $transactionId]
                        );

                        $successCount++;
                    } else {
                        // Payment Failed
                        $errorMsg = $paymentResult['message'] ?? 'Payment transaction declined.';
                        $delivery->update([
                            'delivery_status' => 'failed',
                            'payment_status' => 'failed',
                            'notes' => $errorMsg,
                        ]);

                        $failedAttempts = $subscription->consecutive_failed_payments + 1;
                        $updates = [
                            'consecutive_failed_payments' => $failedAttempts,
                            'last_payment_failed_at' => now(),
                            'last_payment_error' => $errorMsg,
                        ];

                        if ($failedAttempts >= 3) {
                            $updates['status'] = 'paused';
                            $updates['pause_start_date'] = now()->toDateString();
                        }

                        $subscription->update($updates);

                        $subscription->logActivity(
                            'payment_failed',
                            "Payment failed for delivery #{$delivery->delivery_number}: {$errorMsg} (Attempt {$failedAttempts}/3)",
                            ['order_id' => $order->id, 'error' => $errorMsg]
                        );

                        $failedCount++;
                        $this->error("   -> Payment Failed: {$errorMsg}");
                    }
                } else {
                    // Manual/COD/Cash or token pending
                    $this->warn("   -> No payment token saved; Order #{$order->order_number} left pending.");
                    $delivery->update(['delivery_status' => 'processing']);
                    $successCount++;
                }

                // 3. Update next_delivery_date on subscription
                $nextDelivery = $subscription->deliveries()
                    ->where('delivery_status', 'scheduled')
                    ->whereDate('scheduled_date', '>', $effectiveDate)
                    ->orderBy('scheduled_date', 'asc')
                    ->first();

                if ($nextDelivery) {
                    $subscription->update([
                        'next_delivery_date' => $nextDelivery->scheduled_date->toDateString()
                    ]);
                }

                // 4. Check if subscription is completed
                if ($subscription->total_cycles && $subscription->completed_cycles >= $subscription->total_cycles) {
                    $subscription->update(['status' => 'completed']);
                    $subscription->logActivity('completed', "Subscription #{$subscription->subscription_number} completed all {$subscription->total_cycles} cycles.");
                }

                $processedCount++;
            } catch (\Throwable $e) {
                $failedCount++;
                Log::error("Failed to process delivery #{$delivery->id}: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                $this->error("   -> Error processing delivery #{$delivery->id}: {$e->getMessage()}");
            }
        }

        $this->info("=== Finished Processing: {$processedCount} processed, {$successCount} succeeded, {$failedCount} failed. ===");
        return 0;
    }
}
