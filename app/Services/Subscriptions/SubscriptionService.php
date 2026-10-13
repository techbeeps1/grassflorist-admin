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

        if (!$product->is_subscription_enabled) {
            throw ValidationException::withMessages([
                'product_id' => ["Subscriptions are not enabled for '{$product->name}'."],
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
                'zip_code' => $payload['zip_code'] ?? $customer->zip_code,
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
     * Generate an actual Order for a scheduled delivery cycle and process/link payment.
     */
    public function generateOrderForDelivery(SubscriptionDelivery $delivery): Order
    {
        return DB::transaction(function () use ($delivery) {
            $subscription = $delivery->subscription;
            $customer = $subscription->customer;
            $product = $subscription->product;
            $addr = $delivery->delivery_address ?: ($subscription->delivery_address ?? []);

            $deliveryDate = $delivery->scheduled_date->toDateString();
            $deliveryTime = $delivery->time_slot;

            $shippingMethodName = 'Delivery Inside Jeddah';

            $order = Order::create([
                'session_id' => 'sub_order_' . $delivery->id . '_' . time(),
                'user_id' => $subscription->customer_id,
                'email' => $customer?->email ?? 'customer@grassflorist.com',
                'first_name' => $addr['recipient_name'] ?? ($customer?->first_name ?? 'Subscriber'),
                'last_name' => $customer?->last_name ?? '',
                'customer_phone' => $addr['recipient_phone'] ?? ($customer?->phone ?? ''),
                'subtotal' => round($subscription->discounted_price * $subscription->quantity, 2),
                'discount_amount' => round(($subscription->unit_price - $subscription->discounted_price) * $subscription->quantity, 2),
                'tax_amount' => $subscription->tax_amount,
                'shipping_amount' => $subscription->delivery_fee,
                'delivery_amount' => $subscription->delivery_fee,
                'total_amount' => $delivery->total_amount,
                'currency' => $subscription->currency ?? 'SAR',
                'payment_method' => $subscription->payment_gateway ?? 'hyperpay',
                'payment_status' => 'payment_pending',
                'status' => 'pending',
                'order_language' => 'en',
                'delivery_date' => $deliveryDate,
                'delivery_time' => $deliveryTime,
                'delivery_message' => $subscription->gift_message,
                'sender_name' => $customer?->name ?? 'Grass Subscriber',
                'recipient_name' => $addr['recipient_name'] ?? ($customer?->name ?? 'Subscriber'),
                'recipient_phone' => $addr['recipient_phone'] ?? ($customer?->phone ?? ''),
                'address' => $addr['address'] ?? ($customer?->address ?? 'Jeddah'),
                'address_2' => $addr['address_2'] ?? null,
                'city' => $addr['city'] ?? 'Jeddah',
                'district' => $addr['district'] ?? null,
                'zip_code' => $addr['zip_code'] ?? null,
                'country' => 'Saudi Arabia',
                'shipping_method' => $shippingMethodName,
                'notes' => "Recurring Subscription Delivery #{$delivery->delivery_number} of {$subscription->subscription_number}.",
                'subscription_id' => $subscription->id,
                'subscription_delivery_id' => $delivery->id,
                'is_recurring_order' => true,
                'ordered_at' => now(),
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
                'payment_method' => $subscription->payment_gateway ?? 'hyperpay',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Link to delivery record
            $delivery->update([
                'order_id' => $order->id,
                'delivery_status' => 'processing',
            ]);

            $subscription->logActivity(
                'delivery_order_created',
                "Order #{$order->order_number} generated for delivery #{$delivery->delivery_number}.",
                ['order_id' => $order->id, 'delivery_id' => $delivery->id]
            );

            return $order;
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

        return true;
    }

    /**
     * Skip the next scheduled delivery.
     */
    public function skipDelivery(SubscriptionDelivery $delivery, ?string $reason = null): bool
    {
        if ($delivery->delivery_status !== 'scheduled') {
            throw ValidationException::withMessages([
                'delivery' => ["Only scheduled deliveries can be skipped (Current: {$delivery->delivery_status})."],
            ]);
        }

        $delivery->skip($reason ?: 'Skipped by customer');
        $subscription = $delivery->subscription;

        // Advance subscription's next_delivery_date to the subsequent scheduled cycle
        $nextDelivery = $subscription->deliveries()
            ->where('delivery_status', 'scheduled')
            ->where('scheduled_date', '>', $delivery->scheduled_date->toDateString())
            ->orderBy('scheduled_date', 'asc')
            ->first();

        if ($nextDelivery) {
            $subscription->update(['next_delivery_date' => $nextDelivery->scheduled_date->toDateString()]);
        }

        $this->replenishScheduleIfNeeded($subscription);

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
