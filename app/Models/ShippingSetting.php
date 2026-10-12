<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ShippingSetting extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_free_shipping_enabled' => 'boolean',
        'is_weight_shipping_enabled' => 'boolean',
        'is_cod_enabled' => 'boolean',
        'min_order_for_free_shipping' => 'decimal:2',
        'default_flat_shipping' => 'decimal:2',
        'packaging_buffer_weight' => 'decimal:3',
        'extra_weight_per_kg_rate' => 'decimal:2',
        'max_cod_order_amount' => 'decimal:2',
        'default_cod_charge' => 'decimal:2',
        'weight_slabs' => 'array',
        'cod_slabs' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function ($setting) {
            if (auth()->check()) {
                $setting->updated_by = auth()->id();
            }
        });

        static::saved(function () {
            Cache::forget('shipping_global_settings');
        });

        static::deleted(function () {
            Cache::forget('shipping_global_settings');
        });
    }

    /**
     * Singleton accessor for shipping settings with persistent caching
     */
    public static function current(): self
    {
        return Cache::rememberForever('shipping_global_settings', function () {
            return static::firstOrCreate(
                ['id' => 1],
                [
                    'is_free_shipping_enabled' => true,
                    'min_order_for_free_shipping' => 250.00,
                    'default_flat_shipping' => 30.00,
                    'is_weight_shipping_enabled' => true,
                    'packaging_buffer_weight' => 0.100,
                    'weight_slabs' => [
                        ['label' => 'Up to 500g', 'min_weight' => 0.000, 'max_weight' => 0.500, 'price' => 40.00],
                        ['label' => '500g to 1kg', 'min_weight' => 0.501, 'max_weight' => 1.000, 'price' => 60.00],
                        ['label' => '1kg to 2kg', 'min_weight' => 1.001, 'max_weight' => 2.000, 'price' => 80.00],
                        ['label' => '2kg to 3kg', 'min_weight' => 2.001, 'max_weight' => 3.000, 'price' => 110.00],
                    ],
                    'extra_weight_per_kg_rate' => 30.00,
                    'is_cod_enabled' => true,
                    'max_cod_order_amount' => 5000.00,
                    'default_cod_charge' => 49.00,
                    'cod_slabs' => [
                        ['label' => 'Below ₹500', 'min_amount' => 0.00, 'max_amount' => 499.99, 'cod_charge' => 29.00],
                        ['label' => '₹500 to ₹799', 'min_amount' => 500.00, 'max_amount' => 799.99, 'cod_charge' => 49.00],
                        ['label' => '₹800 and above', 'min_amount' => 800.00, 'max_amount' => 999999.00, 'cod_charge' => 79.00],
                    ],
                ]
            );
        });
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
