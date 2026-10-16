<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'email',
        'event_type',
        'location',
        'event_date',
        'guests',
        'message',
        'custom_fields',
        'locale',
        'status',
        'ip_address',
    ];

    protected $casts = [
        'custom_fields' => 'array',
    ];

    public ?string $oldStatusForNotification = null;

    protected static function booted(): void
    {
        static::updating(function (EventBooking $booking) {
            if ($booking->isDirty('status')) {
                $booking->oldStatusForNotification = $booking->getOriginal('status');
            }
        });

        static::updated(function (EventBooking $booking) {
            if (isset($booking->oldStatusForNotification) && $booking->oldStatusForNotification !== $booking->status) {
                $oldStatus = $booking->oldStatusForNotification;
                $booking->oldStatusForNotification = null;

                try {
                    app(\App\Services\Notifications\NotificationService::class)->sendEventBookingStatusNotification(
                        $booking,
                        $booking->status,
                        $oldStatus
                    );
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error("[EventBooking] Error triggering status email: " . $e->getMessage(), [
                        'booking_id' => $booking->id,
                        'new_status' => $booking->status,
                        'old_status' => $oldStatus,
                    ]);
                }
            }
        });
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
