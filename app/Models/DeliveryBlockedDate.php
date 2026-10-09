<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class DeliveryBlockedDate extends Model
{
    use HasFactory;

    protected $fillable = [
        'title_en',
        'title_ar',
        'start_date',
        'end_date',
        'reason_en',
        'reason_ar',
        'delivery_slot_id',
        'is_active',
        'admin_notes',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'is_active' => 'boolean',
    ];

    public function deliverySlot()
    {
        return $this->belongsTo(DeliverySlot::class, 'delivery_slot_id');
    }

    public function getTitleAttribute(): string
    {
        $locale = app()->getLocale();
        return ($locale === 'ar' && !empty($this->title_ar)) ? $this->title_ar : ($this->title_en ?: 'Holiday');
    }

    public function getReasonAttribute(): string
    {
        $locale = app()->getLocale();
        return ($locale === 'ar' && !empty($this->reason_ar)) ? $this->reason_ar : ($this->reason_en ?: '');
    }

    /**
     * Check if a given date string (Y-m-d) is blocked.
     */
    public static function isDateBlocked(string $dateStr, ?int $slotId = null): bool
    {
        return static::getBlockedInfoForDate($dateStr, $slotId) !== null;
    }

    /**
     * Get detailed blocked information for a given date if blocked.
     */
    public static function getBlockedInfoForDate(string $dateStr, ?int $slotId = null): ?array
    {
        $targetDate = Carbon::parse($dateStr)->toDateString();

        $record = static::where('is_active', true)
            ->where(function ($q) use ($targetDate) {
                $q->where(function ($sub) use ($targetDate) {
                    // Single date match: start_date equals targetDate and end_date is null
                    $sub->whereDate('start_date', $targetDate)
                        ->whereNull('end_date');
                })->orWhere(function ($sub) use ($targetDate) {
                    // Date range match: targetDate between start_date and end_date
                    $sub->whereDate('start_date', '<=', $targetDate)
                        ->whereDate('end_date', '>=', $targetDate);
                });
            })
            ->where(function ($q) use ($slotId) {
                // If specific slot requested, check if blocked for all (null) or this specific slot
                if ($slotId) {
                    $q->whereNull('delivery_slot_id')->orWhere('delivery_slot_id', $slotId);
                } else {
                    // Check whole day closures
                    $q->whereNull('delivery_slot_id');
                }
            })
            ->first();

        if (!$record) {
            return null;
        }

        $locale = app()->getLocale();
        $reason = ($locale === 'ar' && !empty($record->reason_ar))
            ? $record->reason_ar
            : ($record->reason_en ?: ($record->title_en ?: 'Store closed on this date'));

        $title = ($locale === 'ar' && !empty($record->title_ar))
            ? $record->title_ar
            : ($record->title_en ?: 'Holiday');

        return [
            'id' => $record->id,
            'date' => $targetDate,
            'title_en' => $record->title_en,
            'title_ar' => $record->title_ar,
            'title' => $title,
            'reason_en' => $record->reason_en,
            'reason_ar' => $record->reason_ar,
            'reason' => $reason,
            'is_all_day' => $record->delivery_slot_id === null,
            'delivery_slot_id' => $record->delivery_slot_id,
        ];
    }

    /**
     * Get all active blocked dates for upcoming 60 days in convenient format for frontend.
     */
    public static function getUpcomingBlockedDates(int $days = 60): array
    {
        $today = Carbon::today();
        $futureLimit = Carbon::today()->addDays($days);

        $records = static::where('is_active', true)
            ->where(function ($q) use ($today, $futureLimit) {
                $q->where(function ($sub) use ($today, $futureLimit) {
                    $sub->whereNull('end_date')
                        ->whereDate('start_date', '>=', $today)
                        ->whereDate('start_date', '<=', $futureLimit);
                })->orWhere(function ($sub) use ($today) {
                    $sub->whereNotNull('end_date')
                        ->whereDate('end_date', '>=', $today);
                });
            })
            ->get();

        $blockedList = [];

        foreach ($records as $record) {
            $startDate = Carbon::parse($record->start_date);
            $endDate = $record->end_date ? Carbon::parse($record->end_date) : $startDate;

            if ($endDate->lessThan($startDate)) {
                $endDate = $startDate;
            }

            $period = CarbonPeriod::create($startDate, $endDate);

            foreach ($period as $dateObj) {
                $dStr = $dateObj->toDateString();
                if ($dateObj->greaterThanOrEqualTo($today)) {
                    $blockedList[$dStr] = [
                        'date' => $dStr,
                        'title_en' => $record->title_en,
                        'title_ar' => $record->title_ar,
                        'reason_en' => $record->reason_en,
                        'reason_ar' => $record->reason_ar,
                        'is_all_day' => $record->delivery_slot_id === null,
                        'delivery_slot_id' => $record->delivery_slot_id,
                    ];
                }
            }
        }

        return array_values($blockedList);
    }
}
