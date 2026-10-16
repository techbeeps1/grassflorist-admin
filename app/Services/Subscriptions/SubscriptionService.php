<?php

namespace App\Services\Subscriptions;

use App\Models\Customer;
use App\Models\GlobalSetting;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\Subscription;
use App\Models\SubscriptionDelivery;
use App\Models\SubscriptionSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SubscriptionService
{
    /**
     * Calculate zero-trust subscription pricing server-side.
     */
    public function calculatePricing(
        int $productId,
        int $quantity = 1,
        string $frequency = 'weekly',
        string $durationType = 'until_cancelled',
        ?string $shippingMethodCode = null,
        array $customDates = []
    ): array {
        $settings = SubscriptionSetting::current();

        if (!$settings->is_enabled) {
            throw ValidationException::withMessages([
                'product_id' => ["Subscriptions are currently disabled."],
            ]);
        }

        $product = Product::findOrFail($productId);

        $subscriptionProductIds = array_map('intval', (array)($settings->subscription_product_ids ?: []));
        $isSubAllowed = !empty($subscriptionProductIds)
            ? in_array((int)$product->id, $subscriptionProductIds, true)
            : (bool)$product->is_subscription_enabled;

        if (!$isSubAllowed) {
            throw ValidationException::withMessages([
                'product_id' => ["Subscriptions are not enabled for '{$product->name}' in subscription settings."],
            ]);
        }

        $quantity = max(1, $quantity);

        // Allowed frequencies check (global setting)
        $allowedFrequencies = !empty($settings->allowed_frequencies) ? $settings->allowed_frequencies : ['weekly', 'biweekly', 'monthly'];
        if (!in_array($frequency, $allowedFrequencies, true) && $frequency !== 'custom') {
            throw ValidationException::withMessages([
                'frequency' => ["Selected frequency '{$frequency}' is not available."],
            ]);
        }

        // Allowed durations check (global setting: fixed vs customer_choice)
        if ($settings->duration_mode === 'fixed') {
            $durationType = $settings->fixed_duration ?: 'until_cancelled';
        } else {
            $allowedDurations = !empty($settings->allowed_durations) ? $settings->allowed_durations : ['3_months', '6_months', '12_months', 'until_cancelled'];
            if (!in_array($durationType, $allowedDurations, true) && $durationType !== 'custom') {
                throw ValidationException::withMessages([
                    'duration_type' => ["Selected duration '{$durationType}' is not available."],
                ]);
            }
        }

        // Base unit price
        $unitPrice = (float)($product->sale_price ?: $product->regular_price ?: $product->price ?: 0);
        $perDeliveryRawSubtotal = round($unitPrice * $quantity, 2);

        // Subscription discount: Product override takes precedence if set, otherwise global setting default
        $discountPercentage = ($product->subscription_discount_percentage !== null && $product->subscription_discount_percentage > 0)
            ? (float)$product->subscription_discount_percentage
            : (float)($settings->default_discount_percentage ?? 10.00);

        $discountedUnitPrice = round($unitPrice * (1 - ($discountPercentage / 100)), 2);
        $discountAmount = round($perDeliveryRawSubtotal * ($discountPercentage / 100), 2);
        $discountedSubtotal = max(0, $perDeliveryRawSubtotal - $discountAmount);

        // Shipping fee per delivery
        $shippingMethod = ShippingMethod::where('code', $shippingMethodCode)->first()
            ?? ShippingMethod::where('is_active', true)->first()
            ?? new ShippingMethod(['name' => 'Delivery Inside Jeddah', 'code' => 'inside_jeddah', 'price' => 30.00]);
        $shippingFee = (float)($shippingMethod->price ?? 30.00);

        // VAT Calculation
        $globalSetting = GlobalSetting::current();
        $isVatIncluded = (bool)($globalSetting->is_vat_included ?? false);
        $vatRate = (float)($globalSetting->vat_percentage ?? 15.00);

        if ($isVatIncluded) {
            $taxAmount = round($discountedSubtotal - ($discountedSubtotal / (1 + ($vatRate / 100))), 2);
            $totalPerDelivery = round($discountedSubtotal + $shippingFee, 2);
        } else {
            $taxAmount = round($discountedSubtotal * ($vatRate / 100), 2);
            $totalPerDelivery = round($discountedSubtotal + $taxAmount + $shippingFee, 2);
        }

        // Estimate total cycles based on frequency & duration
        $totalCycles = $this->estimateTotalCycles($frequency, $durationType, $customDates);
        $projectedGrandTotal = $totalCycles ? round($totalPerDelivery * $totalCycles, 2) : null;
        $totalSavings = $totalCycles ? round($discountAmount * $totalCycles, 2) : null;

        $productName = is_array($product->name) ? ($product->name['en'] ?? reset($product->name)) : (string)$product->name;

        return [
            'product_id' => $product->id,
            'product_name' => $productName,
            'quantity' => $quantity,
            'frequency' => $frequency,
            'duration_type' => $durationType,
            'unit_price' => $unitPrice,
            'discounted_unit_price' => $discountedUnitPrice,
            'discount_percentage' => $discountPercentage,
            'raw_subtotal' => $perDeliveryRawSubtotal,
            'discount_amount' => $discountAmount,
            'subtotal' => $discountedSubtotal,
            'tax_amount' => $taxAmount,
            'is_vat_included' => $isVatIncluded,
            'vat_rate' => $vatRate,
            'delivery_fee' => $shippingFee,
            'shipping_method_code' => $shippingMethod->code ?? 'inside_jeddah',
            'shipping_method_name' => is_array($shippingMethod->name) ? ($shippingMethod->name['en'] ?? reset($shippingMethod->name)) : (string)$shippingMethod->name,
            'total_per_delivery' => $totalPerDelivery,
            'estimated_total_cycles' => $totalCycles,
            'projected_grand_total' => $projectedGrandTotal,
            'projected_total_savings' => $totalSavings,
        ];
    }

    /**
     * Estimate total cycles for a given duration & frequency.
     */
    public function estimateTotalCycles(string $frequency, string $durationType, array $customDates = []): ?int
    {
        if ($durationType === 'until_cancelled') {
            return null; // Ongoing
        }

        if ($frequency === 'custom' || $durationType === 'custom') {
            return count($customDates) > 0 ? count($customDates) : null;
        }

        $months = match ($durationType) {
            '1_month' => 1,
            '3_months' => 3,
            '6_months' => 6,
            '12_months' => 12,
            default => 3,
        };

        return match ($frequency) {
            'weekly' => (int)round($months * 4.33),
            'biweekly' => (int)round($months * 2.16),
            'monthly' => $months,
            default => $months,
        };
    }

    /**
     * Create a brand new subscription with zero-trust price validation.
     */
    public function createSubscription(int $customerId, array $payload, ?string $paymentToken = null): Subscription
    {
        return DB::transaction(function () use ($customerId, $payload, $paymentToken) {
            $customer = Customer::findOrFail($customerId);
            $productId = (int)($payload['product_id'] ?? 0);
            $quantity = (int)($payload['quantity'] ?? 1);
            $frequency = $payload['frequency'] ?? 'weekly';
            $durationType = $payload['duration_type'] ?? 'until_cancelled';
            $shippingCode = $payload['shipping_method_code'] ?? null;
            $customDates = (array)($payload['custom_dates'] ?? []);

            // 🛡️ Idempotency: Prevent duplicate subscriptions for the same initial order & product
            if (!empty($payload['initial_order_id'])) {
                $existing = Subscription::where('customer_id', $customerId)
                    ->where('product_id', $productId)
                    ->where(function ($q) use ($payload) {
                        $q->where('metadata->initial_order_id', (int)$payload['initial_order_id'])
                          ->orWhere('metadata->initial_order_id', (string)$payload['initial_order_id']);
                    })
                    ->first();

                if ($existing) {
                    \Log::info("Idempotent check: Subscription #{$existing->subscription_number} already exists for initial_order_id {$payload['initial_order_id']}. Reusing existing record.");
                    return $existing;
                }
            }

            $pricing = $this->calculatePricing(
                $productId,
                $quantity,
                $frequency,
                $durationType,
                $shippingCode,
                $customDates
            );

            $durationType = $pricing['duration_type'];

            $timezone = GlobalSetting::current()->timezone ?: 'Asia/Riyadh';
            $startDate = !empty($payload['start_date'])
                ? Carbon::parse($payload['start_date'], $timezone)->startOfDay()
                : Carbon::now($timezone)->addDay()->startOfDay();

            $frequencyDays = match ($frequency) {
                'weekly' => 7,
                'biweekly' => 14,
                'monthly' => 30,
                default => 7,
            };

            $deliveryAddress = [
                'recipient_name' => $payload['recipient_name'] ?? $customer->name,
                'recipient_phone' => $payload['recipient_phone'] ?? $customer->phone,
                'address' => $payload['address'] ?? $customer->address,
                'address_2' => $payload['address_2'] ?? $customer->address_2,
                'city' => $payload['city'] ?? $customer->city ?? 'Jeddah',
                'district' => $payload['district'] ?? $customer->district,
                'state' => $payload['state'] ?? 'Makkah',
                'country' => $payload['country'] ?? 'Saudi Arabia',
                'zip_code' => $payload['zip_code'] ?? $customer->zip_code,
                'location_link' => $payload['location_link'] ?? null,
            ];

            $subscription = Subscription::create([
                'customer_id' => $customer->id,
                'product_id' => $productId,
                'quantity' => $quantity,
                'frequency' => $frequency,
                'frequency_days' => $frequencyDays,
                'duration_type' => $durationType,
                'status' => !empty($payload['status']) ? $payload['status'] : 'active',
                'start_date' => $startDate->toDateString(),
                'next_delivery_date' => $startDate->toDateString(),
                'next_billing_date' => $startDate->toDateString(),
                'delivery_slot_id' => $payload['delivery_slot_id'] ?? null,
                'delivery_time_slot' => $payload['delivery_time_slot'] ?? $payload['time_slot'] ?? 'Morning (09:00 AM - 01:00 PM)',
                'delivery_address' => $deliveryAddress,
                'gift_message' => $payload['card_message'] ?? $payload['gift_message'] ?? null,
                'unit_price' => $pricing['unit_price'],
                'discount_percentage' => $pricing['discount_percentage'],
                'discounted_price' => $pricing['discounted_unit_price'],
                'delivery_fee' => $pricing['delivery_fee'],
                'tax_amount' => $pricing['tax_amount'],
                'total_per_delivery' => $pricing['total_per_delivery'],
                'currency' => 'SAR',
                'payment_gateway' => $payload['payment_gateway'] ?? 'hyperpay',
                'payment_token' => $paymentToken ?? $payload['payment_token'] ?? null,
                'metadata' => [
                    'special_instructions' => $payload['special_instructions'] ?? null,
                    'custom_dates' => $customDates,
                    'estimated_total_cycles' => $pricing['estimated_total_cycles'],
                    'projected_grand_total' => $pricing['projected_grand_total'],
                    'initial_order_id' => $payload['initial_order_id'] ?? null,
                ],
            ]);

            // Generate initial delivery schedule (up to 6 cycles or full sequence)
            $this->generateDeliverySchedule($subscription, $customDates);

            $subscription->logActivity(
                'created',
                "Subscription #{$subscription->subscription_number} initiated for {$pricing['product_name']} ({$frequency}, {$durationType}).",
                ['pricing' => $pricing, 'initial_schedule_count' => $subscription->deliveries()->count()]
            );

            return $subscription;
        });
    }

    /**
     * Generate or replenish the delivery schedule for a subscription.
     */
    public function generateDeliverySchedule(Subscription $subscription, array $customDates = [], int $limit = 6): void
    {
        $timezone = GlobalSetting::current()->timezone ?: 'Asia/Riyadh';
        $startDate = Carbon::parse($subscription->start_date, $timezone);

        $savedCustomDates = $subscription->metadata['custom_dates'] ?? [];
        $datesToUse = !empty($savedCustomDates) ? $savedCustomDates : $customDates;

        if ($subscription->frequency === 'custom' || !empty($datesToUse)) {
            $sequence = 1;
            foreach ($datesToUse as $dateStr) {
                $schedDate = Carbon::parse($dateStr, $timezone)->toDateString();
                SubscriptionDelivery::firstOrCreate(
                    [
                        'subscription_id' => $subscription->id,
                        'delivery_number' => $sequence,
                    ],
                    [
                        'customer_id' => $subscription->customer_id,
                        'scheduled_date' => $schedDate,
                        'time_slot' => $subscription->delivery_time_slot,
                        'delivery_status' => 'scheduled',
                        'payment_status' => 'pending',
                        'unit_price' => $subscription->unit_price,
                        'discounted_price' => $subscription->discounted_price,
                        'delivery_fee' => $subscription->delivery_fee,
                        'total_amount' => $subscription->total_per_delivery,
                        'delivery_address' => $subscription->delivery_address,
                    ]
                );
                $sequence++;
            }
            return;
        }

        // Standard frequencies: weekly, biweekly, monthly
        $estCycles = $subscription->metadata['estimated_total_cycles'] ?? null;
        $totalCyclesToGenerate = $estCycles ? min($estCycles, $limit) : $limit;
        $existingCount = $subscription->deliveries()->count();

        for ($i = 0; $i < $totalCyclesToGenerate; $i++) {
            $cycleNumber = $i + 1;
            if ($cycleNumber <= $existingCount) {
                continue;
            }

            $cycleDate = $this->calculateDateForCycle($subscription->frequency, $startDate, $i);

            SubscriptionDelivery::create([
                'subscription_id' => $subscription->id,
                'customer_id' => $subscription->customer_id,
                'delivery_number' => $cycleNumber,
                'scheduled_date' => $cycleDate->toDateString(),
                'time_slot' => $subscription->delivery_time_slot,
                'delivery_status' => 'scheduled',
                'payment_status' => 'pending',
                'unit_price' => $subscription->unit_price,
                'discounted_price' => $subscription->discounted_price,
                'delivery_fee' => $subscription->delivery_fee,
                'total_amount' => $subscription->total_per_delivery,
                'delivery_address' => $subscription->delivery_address,
            ]);
        }
    }

    /**
     * Calculate delivery date for cycle index.
     */
    protected function calculateDateForCycle(string $frequency, Carbon $startDate, int $cycleIndex): Carbon
    {
        $date = $startDate->copy();
        return match ($frequency) {
            'weekly' => $date->addWeeks($cycleIndex),
            'biweekly' => $date->addWeeks($cycleIndex * 2),
            'monthly' => $date->addMonthsNoOverflow($cycleIndex),
            default => $date->addWeeks($cycleIndex),
        };
    }

    /**
     * Generate an actual Order for a scheduled delivery cycle, inherit complete customer/address details, and charge recurring payment token.
     */
    public function generateOrderForDelivery(SubscriptionDelivery $delivery, bool $autoCharge = true): Order
    {
        return DB::transaction(function () use ($delivery, $autoCharge) {
            $subscription = $delivery->subscription;
            $customer = $subscription->customer;
            $product = $subscription->product;
            $addr = $delivery->delivery_address ?: ($subscription->delivery_address ?? []);

            // 🔍 1. Find initial parent order to inherit all exact checkout preferences
            $initialOrderId = $subscription->metadata['initial_order_id'] ?? null;
            $initialOrder = null;
            if ($initialOrderId) {
                $initialOrder = Order::find($initialOrderId);
            }
            if (!$initialOrder) {
                $initialOrder = Order::where('subscription_id', $subscription->id)
                    ->where('is_recurring_order', false)
                    ->orderBy('id', 'asc')
                    ->first();
            }

            // 👤 2. Fix Name Split & Customer Phone (Prevents duplicate last name e.g. "gurjeet singh singh")
            $custFirst = $initialOrder?->first_name ?: ($customer?->first_name ?: '');
            $custLast = $initialOrder?->last_name ?: ($customer?->last_name ?: '');
            if (empty($custFirst)) {
                $rawName = trim((string)($customer?->name ?: ($initialOrder?->sender_name ?: 'Valued Customer')));
                $parts = array_values(array_filter(explode(' ', $rawName)));
                $custFirst = $parts[0] ?? 'Valued';
                $custLast = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '';
            }

            $senderName = $initialOrder?->sender_name ?: ($customer?->name ?: trim("{$custFirst} {$custLast}"));
            $recipientName = $addr['recipient_name'] ?? ($initialOrder?->recipient_name ?: ($customer?->name ?? 'Valued Customer'));
            $recipientPhone = $addr['recipient_phone'] ?? ($initialOrder?->recipient_phone ?: ($customer?->phone ?? ''));
            $customerPhone = $initialOrder?->customer_phone ?: ($customer?->phone ?: $recipientPhone);

            // 📍 3. Fix Address, State, District, and Google Maps Location Link
            $address = $addr['address'] ?? ($initialOrder?->address ?: 'Jeddah');
            $address2 = $addr['address_2'] ?? ($initialOrder?->address_2 ?: null);
            $city = $addr['city'] ?? ($initialOrder?->city ?: 'Jeddah');
            $district = $addr['district'] ?? ($initialOrder?->district ?: null);
            $state = $addr['state'] ?? ($initialOrder?->state ?: 'Makkah');
            $country = $addr['country'] ?? ($initialOrder?->country ?: 'Saudi Arabia');
            $zipCode = $addr['zip_code'] ?? ($initialOrder?->zip_code ?: '23434');
            $locationLink = $addr['location_link'] ?? ($initialOrder?->location_link ?: null);

            // 💱 4. Fix Currency & Locked Exchange Rate (PayPal USD Conversion)
            $effectiveGateway = strtolower((string)($subscription->payment_gateway ?? ($initialOrder?->payment_method ?? 'hyperpay')));
            $sarAmount = (float)$delivery->total_amount;
            $exchangeRate = 1.0000;
            $currency = 'SAR';
            $currencyAmount = $sarAmount;

            if ($effectiveGateway === 'paypal') {
                $currency = 'USD';
                $exchangeRate = (float)($initialOrder?->exchange_rate ?: 0.2667);
                if ($exchangeRate <= 0 || $exchangeRate >= 1.0) {
                    $exchangeRate = 0.2667;
                }
                $currencyAmount = round($sarAmount * $exchangeRate, 2);
            }

            $deliveryDate = $delivery->scheduled_date->toDateString();
            $deliveryTime = $delivery->time_slot ?: ($initialOrder?->delivery_time ?: '11:00:00');
            $shippingMethodName = $initialOrder?->shipping_method ?: 'Delivery Inside Jeddah';

            $order = Order::create([
                'session_id' => 'sub_order_' . $delivery->id . '_' . time(),
                'user_id' => $subscription->customer_id,
                'email' => $customer?->email ?? ($initialOrder?->email ?? 'customer@grassflorist.com'),
                'first_name' => $custFirst,
                'last_name' => $custLast,
                'customer_phone' => $customerPhone,
                'subtotal' => round($subscription->unit_price * $subscription->quantity, 2),
                'discount_amount' => round(($subscription->unit_price - $subscription->discounted_price) * $subscription->quantity, 2),
                'tax_amount' => $subscription->tax_amount,
                'shipping_amount' => $subscription->delivery_fee,
                'delivery_amount' => 0.00,
                'total_amount' => $sarAmount,
                'sar_amount' => $sarAmount,
                'currency' => $currency,
                'exchange_rate' => $exchangeRate,
                'currency_amount' => $currencyAmount,
                'payment_method' => $effectiveGateway,
                'payment_status' => 'payment_pending',
                'status' => 'pending',
                'order_language' => $initialOrder?->order_language ?? 'en',
                'delivery_date' => $deliveryDate,
                'delivery_time' => $deliveryTime,
                'delivery_message' => $subscription->gift_message ?? $initialOrder?->delivery_message,
                'sender_name' => $senderName,
                'recipient_name' => $recipientName,
                'recipient_phone' => $recipientPhone,
                'address' => $address,
                'address_2' => $address2,
                'city' => $city,
                'district' => $district,
                'state' => $state,
                'zip_code' => $zipCode,
                'country' => $country,
                'location_link' => $locationLink,
                'shipping_method' => $shippingMethodName,
                'notes' => "Recurring Subscription Delivery #{$delivery->delivery_number} of {$subscription->subscription_number}.",
                'subscription_id' => $subscription->id,
                'subscription_delivery_id' => $delivery->id,
                'is_recurring_order' => true,
                'ordered_at' => now(),
                'meta_data' => [
                    'currency' => 'SAR',
                    'exchange_rate' => $exchangeRate,
                    'sar_total' => $sarAmount,
                    'currency_total' => $sarAmount,
                    'usd_total' => $currencyAmount,
                    'has_subscription' => true,
                    'subscription_ids' => [$subscription->id],
                    'paypal_charge_usd' => $currencyAmount,
                ],
            ]);

            $order->update([
                'order_number' => 'SUB-OR-' . str_pad((string)$order->id, 6, '0', STR_PAD_LEFT),
            ]);

            // Add OrderItem snapshot
            DB::table('order_items')->insert([
                'order_id' => $order->id,
                'product_id' => $product?->id,
                'product_name' => is_array($product?->name) ? ($product?->name['en'] ?? reset($product?->name)) : (string)($product?->name ?? 'Subscription Flower Box'),
                'product_image' => is_string($product?->image) ? $product?->image : (is_array($product?->image) ? reset($product?->image) : ''),
                'price' => $subscription->discounted_price,
                'unit_price' => $subscription->discounted_price,
                'quantity' => $subscription->quantity,
                'tax' => $subscription->tax_amount,
                'subtotal' => round($subscription->discounted_price * $subscription->quantity, 2),
                'total' => $delivery->total_amount,
                'payment_method' => $effectiveGateway,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Link to delivery record
            $delivery->update([
                'order_id' => $order->id,
                'delivery_status' => 'processing',
            ]);

            // Advance parent subscription next_delivery_date to the subsequent scheduled cycle
            $subsequentDelivery = $subscription->deliveries()
                ->where('delivery_status', 'scheduled')
                ->where('id', '!=', $delivery->id)
                ->orderBy('scheduled_date', 'asc')
                ->first();

            $subscription->update([
                'next_delivery_date' => $subsequentDelivery?->scheduled_date?->toDateString(),
            ]);

            $subscription->logActivity(
                'delivery_order_created',
                "Order #{$order->order_number} generated for delivery #{$delivery->delivery_number}.",
                ['order_id' => $order->id, 'delivery_id' => $delivery->id]
            );

            // 💳 5. Automatic Recurring Payment Charge via Payment Token
            if ($autoCharge && !empty($subscription->payment_token)) {
                $token = $subscription->payment_token;
                Log::info("[SubscriptionService] Executing auto recurring payment for Order #{$order->order_number} via [{$effectiveGateway}] using token [{$token}]");

                $options = [];
                if (!empty($subscription->payment_token_details['brand'])) {
                    $options['payment_brand'] = $subscription->payment_token_details['brand'];
                }

                $paymentResult = \App\Services\Payments\PaymentManager::chargeRecurringToken($order, $token, $effectiveGateway, $options);

                if (!empty($paymentResult['success'])) {
                    $txId = $paymentResult['transaction_id'] ?? ('TXN_REC_' . time());
                    \App\Services\Payments\PaymentManager::markOrderAsPaid($order, $txId, $effectiveGateway, $paymentResult, 'RECURRING');

                    $delivery->update([
                        'delivery_status' => 'processing',
                        'payment_status' => 'paid',
                        'payment_reference' => $txId,
                    ]);

                    $subscription->update([
                        'consecutive_failed_payments' => 0,
                        'last_payment_failed_at' => null,
                        'last_payment_error' => null,
                    ]);

                    $subscription->logActivity(
                        'payment_success',
                        "Recurring payment of SAR {$order->total_amount} charged successfully for delivery #{$delivery->delivery_number} (TXN: {$txId}).",
                        ['order_id' => $order->id, 'transaction_id' => $txId, 'gateway' => $effectiveGateway]
                    );
                } else {
                    $errMsg = $paymentResult['message'] ?? 'Payment transaction declined.';
                    $order->update([
                        'payment_status' => 'payment_pending',
                        'failed_order_reason' => $errMsg,
                    ]);

                    $delivery->update([
                        'payment_status' => 'failed',
                        'notes' => $errMsg,
                    ]);

                    $failedAttempts = $subscription->consecutive_failed_payments + 1;
                    $subUpdates = [
                        'consecutive_failed_payments' => $failedAttempts,
                        'last_payment_failed_at' => now(),
                        'last_payment_error' => $errMsg,
                    ];
                    if ($failedAttempts >= 3) {
                        $subUpdates['status'] = 'paused';
                        $subUpdates['pause_start_date'] = now()->toDateString();
                    }
                    $subscription->update($subUpdates);

                    $subscription->logActivity(
                        'payment_failed',
                        "Recurring payment charge failed for delivery #{$delivery->delivery_number}: {$errMsg} (Attempt {$failedAttempts}/3)",
                        ['order_id' => $order->id, 'error' => $errMsg, 'gateway' => $effectiveGateway]
                    );
                }
            }

            return $order->fresh();
        });
    }

    /**
     * Pause a subscription.
     */
    public function pauseSubscription(Subscription $subscription, ?string $reason = null): bool
    {
        $subscription->pause(null, $reason);

        // Also update upcoming scheduled deliveries
        $subscription->deliveries()
            ->where('delivery_status', 'scheduled')
            ->where('scheduled_date', '>=', now()->toDateString())
            ->update(['delivery_status' => 'paused']);

        // 🔔 Dispatch Subscription Paused notification (Email & SMS)
        try {
            app(\App\Services\Notifications\NotificationService::class)->sendSubscriptionNotification(
                $subscription,
                'subscription_paused',
                ['reason' => $reason]
            );
        } catch (\Throwable $e) {
            Log::error("Failed to dispatch subscription_paused notification: " . $e->getMessage());
        }

        return true;
    }

    /**
     * Resume a paused subscription.
     */
    public function resumeSubscription(Subscription $subscription): bool
    {
        $subscription->resume();

        $timezone = GlobalSetting::current()->timezone ?: 'Asia/Riyadh';
        $today = Carbon::now($timezone)->toDateString();

        $subscription->deliveries()
            ->where('delivery_status', 'paused')
            ->where('scheduled_date', '>=', $today)
            ->update(['delivery_status' => 'scheduled']);

        $nextDelivery = $subscription->deliveries()
            ->where('delivery_status', 'scheduled')
            ->where('scheduled_date', '>=', $today)
            ->orderBy('scheduled_date', 'asc')
            ->first();

        if ($nextDelivery) {
            $subscription->update(['next_delivery_date' => $nextDelivery->scheduled_date->toDateString()]);
        }

        // 🔔 Dispatch Subscription Resumed notification (Email & SMS)
        try {
            app(\App\Services\Notifications\NotificationService::class)->sendSubscriptionNotification(
                $subscription,
                'subscription_resumed'
            );
        } catch (\Throwable $e) {
            Log::error("Failed to dispatch subscription_resumed notification: " . $e->getMessage());
        }

        return true;
    }

    /**
     * Skip the next scheduled delivery.
     */
    public function skipDelivery(SubscriptionDelivery $delivery, ?string $reason = null): bool
    {
        if (!in_array($delivery->delivery_status, ['scheduled', 'rescheduled'], true)) {
            throw ValidationException::withMessages([
                'delivery' => ["Only scheduled deliveries can be skipped (Current: {$delivery->delivery_status})."],
            ]);
        }

        $delivery->skip($reason ?: 'Skipped by customer');
        $subscription = $delivery->subscription;

        // Advance subscription's next_delivery_date to the subsequent scheduled cycle
        $nextDelivery = $subscription->deliveries()
            ->whereIn('delivery_status', ['scheduled', 'rescheduled'])
            ->where('scheduled_date', '>', $delivery->scheduled_date->toDateString())
            ->orderBy('scheduled_date', 'asc')
            ->first();

        if ($nextDelivery) {
            $subscription->update(['next_delivery_date' => $nextDelivery->scheduled_date->toDateString()]);
        }

        $this->replenishScheduleIfNeeded($subscription);

        // 🔔 Dispatch Subscription Skipped notification (Email & SMS)
        try {
            app(\App\Services\Notifications\NotificationService::class)->sendSubscriptionNotification(
                $subscription,
                'subscription_skipped',
                ['skipped_date' => $delivery->scheduled_date->toDateString()]
            );
        } catch (\Throwable $e) {
            Log::error("Failed to dispatch subscription_skipped notification: " . $e->getMessage());
        }

        return true;
    }

    /**
     * Reschedule a delivery cycle to a different date or time slot.
     */
    public function rescheduleDelivery(
        SubscriptionDelivery $delivery,
        string $newDate,
        ?string $newSlot = null,
        ?string $reason = null
    ): bool {
        if (!in_array($delivery->delivery_status, ['scheduled', 'paused', 'rescheduled'], true)) {
            throw ValidationException::withMessages([
                'delivery' => ["Only scheduled or paused deliveries can be rescheduled."],
            ]);
        }

        $delivery->reschedule($newDate, $newSlot, $reason ?: 'Rescheduled by customer');

        // 🔔 Dispatch Subscription Rescheduled notification (Email & SMS)
        try {
            app(\App\Services\Notifications\NotificationService::class)->sendSubscriptionNotification(
                $delivery->subscription,
                'subscription_rescheduled',
                [
                    'new_delivery_date' => $newDate,
                    'new_time_slot' => $newSlot,
                    'reason' => $reason,
                ]
            );
        } catch (\Throwable $e) {
            Log::error("Failed to dispatch subscription_rescheduled notification: " . $e->getMessage());
        }

        return true;
    }

    /**
     * Update delivery address for subscription.
     */
    public function updateAddress(Subscription $subscription, array $addressData): bool
    {
        $currentAddress = $subscription->delivery_address ?? [];
        $newAddress = array_merge($currentAddress, [
            'recipient_name' => $addressData['recipient_name'] ?? ($currentAddress['recipient_name'] ?? ''),
            'recipient_phone' => $addressData['recipient_phone'] ?? ($currentAddress['recipient_phone'] ?? ''),
            'address' => $addressData['address'] ?? ($currentAddress['address'] ?? ''),
            'address_2' => $addressData['address_2'] ?? ($currentAddress['address_2'] ?? null),
            'city' => $addressData['city'] ?? ($currentAddress['city'] ?? 'Jeddah'),
            'district' => $addressData['district'] ?? ($currentAddress['district'] ?? null),
            'zip_code' => $addressData['zip_code'] ?? ($currentAddress['zip_code'] ?? null),
        ]);

        $subscription->update(['delivery_address' => $newAddress]);

        // Also update future scheduled deliveries
        $subscription->deliveries()
            ->where('delivery_status', 'scheduled')
            ->update(['delivery_address' => $newAddress]);

        $subscription->logActivity(
            'address_updated',
            "Delivery address updated to '{$newAddress['address']}, {$newAddress['city']}'.",
            ['delivery_address' => $newAddress]
        );

        // 🔔 Dispatch Subscription Address Updated notification (Email & SMS)
        try {
            app(\App\Services\Notifications\NotificationService::class)->sendSubscriptionNotification(
                $subscription,
                'subscription_address_updated'
            );
        } catch (\Throwable $e) {
            Log::error("Failed to dispatch subscription_address_updated notification: " . $e->getMessage());
        }

        return true;
    }

    /**
     * Update quantity and recalculate per-delivery pricing.
     */
    public function updateQuantity(Subscription $subscription, int $newQuantity): bool
    {
        if ($newQuantity < 1) {
            throw ValidationException::withMessages(['quantity' => ['Quantity must be at least 1.']]);
        }

        $oldQty = $subscription->quantity;

        // Recalculate pricing
        $pricing = $this->calculatePricing(
            $subscription->product_id,
            $newQuantity,
            $subscription->frequency,
            $subscription->duration_type
        );

        $subscription->update([
            'quantity' => $newQuantity,
            'discounted_price' => $pricing['discounted_unit_price'],
            'tax_amount' => $pricing['tax_amount'],
            'total_per_delivery' => $pricing['total_per_delivery'],
        ]);

        // Update upcoming scheduled deliveries with the new per-delivery amount
        $subscription->deliveries()
            ->where('delivery_status', 'scheduled')
            ->update([
                'discounted_price' => $pricing['discounted_unit_price'],
                'total_amount' => $pricing['total_per_delivery'],
            ]);

        $subscription->logActivity(
            'quantity_updated',
            "Quantity updated from {$oldQty} to {$newQuantity}. New delivery price: SAR {$pricing['total_per_delivery']}.",
            ['old_quantity' => $oldQty, 'new_quantity' => $newQuantity, 'new_pricing' => $pricing]
        );

        return true;
    }

    /**
     * Cancel a subscription.
     */
    public function cancelSubscription(Subscription $subscription, string $reason, string $cancelledBy = 'customer'): bool
    {
        $subscription->cancel($reason);

        // Cancel upcoming unscheduled/unprocessed deliveries
        $subscription->deliveries()
            ->where('delivery_status', 'scheduled')
            ->update(['delivery_status' => 'cancelled']);

        // 🔔 Dispatch Subscription Cancelled notifications (Customer & Admin Email/SMS)
        try {
            $notifService = app(\App\Services\Notifications\NotificationService::class);
            $notifService->sendSubscriptionNotification($subscription, 'subscription_cancelled', ['reason' => $reason]);
            $notifService->sendSubscriptionNotification($subscription, 'admin_subscription_cancelled', ['reason' => $reason]);
        } catch (\Throwable $e) {
            Log::error("Failed to dispatch subscription_cancelled notification: " . $e->getMessage());
        }

        return true;
    }

    /**
     * Replenish future schedule if running low (keeps at least 3-6 cycles ahead).
     */
    protected function replenishScheduleIfNeeded(Subscription $subscription): void
    {
        if ($subscription->duration_type !== 'until_cancelled') {
            return;
        }

        $scheduledCount = $subscription->deliveries()->where('delivery_status', 'scheduled')->count();
        if ($scheduledCount < 3) {
            $lastDelivery = $subscription->deliveries()->orderBy('delivery_number', 'desc')->first();
            $lastCycleNumber = $lastDelivery ? $lastDelivery->delivery_number : 0;
            $lastDate = $lastDelivery ? Carbon::parse($lastDelivery->scheduled_date) : Carbon::parse($subscription->start_date);

            for ($i = 1; $i <= 3; $i++) {
                $nextCycleNumber = $lastCycleNumber + $i;
                $nextDate = $this->calculateDateForCycle($subscription->frequency, $lastDate, $i);

                SubscriptionDelivery::create([
                    'subscription_id' => $subscription->id,
                    'customer_id' => $subscription->customer_id,
                    'delivery_number' => $nextCycleNumber,
                    'scheduled_date' => $nextDate->toDateString(),
                    'time_slot' => $subscription->delivery_time_slot,
                    'delivery_status' => 'scheduled',
                    'payment_status' => 'pending',
                    'unit_price' => $subscription->unit_price,
                    'discounted_price' => $subscription->discounted_price,
                    'delivery_fee' => $subscription->delivery_fee,
                    'total_amount' => $subscription->total_per_delivery,
                    'delivery_address' => $subscription->delivery_address,
                ]);
            }
        }
    }
}
