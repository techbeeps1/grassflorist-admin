<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id',
        'customer_id',
        'order_id',
        'delivery_number',
        'scheduled_date',
        'time_slot',
        'delivery_status',
        'payment_status',
        'rescheduled_from_date',
        'reschedule_reason',
        'skip_reason',
        'unit_price',
        'discounted_price',
        'delivery_fee',
        'total_amount',
        'payment_reference',
        'delivery_address',
        'notes',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'rescheduled_from_date' => 'date',
        'delivery_number' => 'integer',
        'unit_price' => 'decimal:2',
        'discounted_price' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'delivery_address' => 'array',
    ];

    /* -------------------------------------------------------------------------- */
    /*                                Relationships                              */
    /* -------------------------------------------------------------------------- */

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /* -------------------------------------------------------------------------- */
    /*                                Actions                                     */
    /* -------------------------------------------------------------------------- */

    public function skip(string $reason = 'Skipped by customer'): void
    {
        $this->update([
            'delivery_status' => 'skipped',
            'skip_reason' => $reason,
        ]);

        $this->subscription->logActivity('skipped_delivery', "Skipped delivery #{$this->delivery_number} scheduled for {$this->scheduled_date->toDateString()}", [
            'delivery_id' => $this->id,
            'reason' => $reason,
        ]);
    }

    public function reschedule(string $newDate, ?string $newSlot = null, string $reason = 'Rescheduled'): void
    {
        $oldDate = $this->scheduled_date->toDateString();
        $this->update([
            'scheduled_date' => $newDate,
            'time_slot' => $newSlot ?: $this->time_slot,
            'rescheduled_from_date' => $oldDate,
            'reschedule_reason' => $reason,
            'delivery_status' => 'rescheduled',
        ]);

        // If this is the next upcoming delivery, update parent subscription next_delivery_date
        if ($this->subscription->next_delivery_date?->toDateString() === $oldDate) {
            $this->subscription->update([
                'next_delivery_date' => $newDate,
            ]);
        }

        $this->subscription->logActivity('rescheduled_delivery', "Rescheduled delivery #{$this->delivery_number} from {$oldDate} to {$newDate}", [
            'delivery_id' => $this->id,
            'old_date' => $oldDate,
            'new_date' => $newDate,
            'slot' => $newSlot ?: $this->time_slot,
            'reason' => $reason,
        ]);
    }
}
