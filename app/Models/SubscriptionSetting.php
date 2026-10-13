<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SubscriptionSetting extends Model
{
    protected $table = 'subscription_settings';

    protected $guarded = ['id'];

    protected $casts = [
        'is_enabled' => 'boolean',
        'default_discount_percentage' => 'float',
        'allowed_frequencies' => 'array',
        'allowed_durations' => 'array',
        'customer_notes_enabled' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function ($setting) {
            if (auth()->check()) {
                $setting->updated_by = auth()->id();
            }
        });

        static::saved(function () {
            Cache::forget('subscription_settings');
        });

        static::deleted(function () {
            Cache::forget('subscription_settings');
        });
    }

    /**
     * Singleton accessor for global subscription settings
     */
    public static function current(): self
    {
        return Cache::rememberForever('subscription_settings', function () {
            return static::firstOrCreate(
                ['id' => 1],
                [
                    'is_enabled' => true,
                    'default_discount_percentage' => 10.00,
                    'allowed_frequencies' => ['weekly', 'biweekly', 'monthly'],
                    'duration_mode' => 'fixed',
                    'fixed_duration' => 'until_cancelled',
                    'allowed_durations' => ['3_months', '6_months', '12_months', 'until_cancelled'],
                    'customer_notes_enabled' => true,
                ]
            );
        });
    }

    /**
     * Relationship to the user who updated the settings
     */
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
