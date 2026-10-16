<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Subscription extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'subscription_number',
        'customer_id',
        'product_id',
        'quantity',
        'frequency',
        'frequency_days',
        'duration_type',
        'status',
        'start_date',
        'end_date',
        'next_delivery_date',
        'next_billing_date',
        'cancelled_at',
        'cancellation_reason',
        'pause_start_date',
        'pause_resume_date',
        'delivery_slot_id',
        'delivery_time_slot',
        'delivery_address',
        'gift_message',
        'unit_price',
        'discount_percentage',
        'discounted_price',
        'delivery_fee',
        'tax_amount',
        'total_per_delivery',
        'currency',
        'payment_gateway',
        'payment_token',
        'payment_token_details',
        'consecutive_failed_payments',
        'last_payment_failed_at',
        'last_payment_error',
        'metadata',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'next_delivery_date' => 'date',
        'next_billing_date' => 'date',
        'pause_start_date' => 'date',
        'pause_resume_date' => 'date',
        'cancelled_at' => 'datetime',
        'last_payment_failed_at' => 'datetime',
        'delivery_address' => 'array',
        'payment_token_details' => 'array',
        'metadata' => 'array',
        'quantity' => 'integer',
        'frequency_days' => 'integer',
        'consecutive_failed_payments' => 'integer',
        'unit_price' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'discounted_price' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_per_delivery' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Subscription $subscription) {
            if (empty($subscription->subscription_number)) {
                $year = date('Y');
                $prefix = 'SUB-' . $year . '-';

                // Find the highest existing sequence number for this year (including soft-deleted)
                $maxRecord = static::withTrashed()
                    ->where('subscription_number', 'like', $prefix . '%')
                    ->orderByRaw('CAST(SUBSTRING(subscription_number, 10) AS UNSIGNED) DESC')
                    ->first();

                $nextSeq = 1;
                if ($maxRecord && preg_match('/SUB-\d{4}-(\d+)/', $maxRecord->subscription_number, $matches)) {
                    $nextSeq = ((int)$matches[1]) + 1;
                }

                // Ensure candidate does not collide with any existing record (withTrashed)
                do {
                    $candidate = $prefix . str_pad((string)$nextSeq, 5, '0', STR_PAD_LEFT);
                    $exists = static::withTrashed()->where('subscription_number', $candidate)->exists();
                    if ($exists) {
                        $nextSeq++;
                    }
                } while ($exists);

                $subscription->subscription_number = $candidate;
            }
        });

        static::created(function (Subscription $subscription) {
            try {
                \App\Services\Webhooks\WebhookService::dispatch(
                    \App\Services\Webhooks\WebhookService::EVENT_SUBSCRIPTION_CREATED,
                    [
                        'subscription_id' => $subscription->id,
                        'subscription_number' => $subscription->subscription_number,
                        'customer_id' => $subscription->customer_id,
                        'product_id' => $subscription->product_id,
                        'product_name' => $subscription->product?->name,
                        'frequency' => $subscription->frequency,
                        'duration_type' => $subscription->duration_type,
                        'status' => $subscription->status,
                        'start_date' => $subscription->start_date?->format('Y-m-d'),
                        'next_delivery_date' => $subscription->next_delivery_date?->format('Y-m-d'),
                        'unit_price' => (float) $subscription->unit_price,
                        'total_per_delivery' => (float) $subscription->total_per_delivery,
                        'currency' => $subscription->currency ?: 'SAR',
                        'customer' => [
                            'name' => $subscription->customer?->name,
                            'email' => $subscription->customer?->email,
                            'phone' => $subscription->customer?->phone,
                        ],
                        'delivery_address' => $subscription->delivery_address,
                        'created_at' => $subscription->created_at?->toIso8601String(),
                    ],
                    $subscription->subscription_number ?: (string)$subscription->id,
                    'App\Models\Subscription'
                );
            } catch (\Throwable $e) {
                logger()->error('Webhook subscription.created error: ' . $e->getMessage());
            }
        });
    }

    /* -------------------------------------------------------------------------- */
    /*                                Relationships                              */
    /* -------------------------------------------------------------------------- */

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function deliverySlot(): BelongsTo
    {
        return $this->belongsTo(DeliverySlot::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(SubscriptionDelivery::class)->orderBy('scheduled_date', 'asc');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->orderBy('id', 'desc');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(SubscriptionActivity::class)->orderBy('id', 'desc');
    }

    /* -------------------------------------------------------------------------- */
    /*                                   Scopes                                   */
    /* -------------------------------------------------------------------------- */

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePaused($query)
    {
        return $query->where('status', 'paused');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeDueForDelivery($query, ?string $date = null)
    {
        $targetDate = $date ?: now()->toDateString();
        return $query->where('status', 'active')
            ->whereNotNull('next_delivery_date')
            ->where('next_delivery_date', '<=', $targetDate);
    }

    /* -------------------------------------------------------------------------- */
    /*                                Helper Methods                              */
    /* -------------------------------------------------------------------------- */

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function pause(?string $resumeDate = null, ?string $reason = null): void
    {
        $this->update([
            'status' => 'paused',
            'pause_start_date' => now()->toDateString(),
            'pause_resume_date' => $resumeDate,
        ]);

        $this->logActivity('paused', 'Subscription paused', [
            'pause_resume_date' => $resumeDate,
            'reason' => $reason,
        ]);
    }

    public function resume(): void
    {
        $this->update([
            'status' => 'active',
            'pause_start_date' => null,
            'pause_resume_date' => null,
        ]);

        $this->logActivity('resumed', 'Subscription resumed');
    }

    public function cancel(string $reason = ''): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);

        $this->logActivity('cancelled', 'Subscription cancelled', ['reason' => $reason]);
    }

    public function logActivity(string $type, string $description, array $properties = [], ?int $userId = null): void
    {
        $adminUserId = null;
        if ($userId && \App\Models\User::where('id', $userId)->exists()) {
            $adminUserId = $userId;
        } elseif (auth()->check() && auth()->user() instanceof \App\Models\User) {
            $adminUserId = auth()->id();
        }

        $customerId = $this->customer_id;
        if (!$customerId && auth()->check() && auth()->user() instanceof \App\Models\Customer) {
            $customerId = auth()->id();
        }

        $this->activities()->create([
            'customer_id' => $customerId,
            'user_id' => $adminUserId,
            'activity_type' => $type,
            'description' => $description,
            'properties' => $properties,
        ]);
    }

    public function getFrequencyLabelAttribute(): string
    {
        return match ($this->frequency) {
            'weekly' => 'Every Week',
            'biweekly' => 'Every 2 Weeks',
            'monthly' => 'Every Month',
            default => 'Every ' . $this->frequency_days . ' Days',
        };
    }

    public function getFrequencyLabelArAttribute(): string
    {
        return match ($this->frequency) {
            'weekly' => 'أسبوعياً (كل أسبوع)',
            'biweekly' => 'كل أسبوعين',
            'monthly' => 'شهرياً (كل شهر)',
            default => 'كل ' . $this->frequency_days . ' يوم',
        };
    }
}
