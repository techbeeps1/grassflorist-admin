<?php

namespace App\Models;

use App\Traits\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingMethod extends Model
{
    use HasTranslations;

    protected $fillable = [
        'name',
        'code',
        'delivery_time',
        'description',
        'city',
        'price',
        'calculation_type',
        'is_free_shipping_eligible',
        'min_order_for_free_shipping',
        'is_cod_allowed',
        'is_active',
        'sort_order',
        'updated_by',
    ];

    /**
     * Multilingual translatable fields (Arabic & English)
     */
    protected array $translatable = [
        'name',
        'delivery_time',
        'description',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'min_order_for_free_shipping' => 'decimal:2',
        'is_free_shipping_eligible' => 'boolean',
        'is_cod_allowed' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(function ($method) {
            if (auth()->check()) {
                $method->updated_by = auth()->id();
            }
        });

        static::creating(function ($method) {
            if (auth()->check()) {
                $method->updated_by = auth()->id();
            }
        });
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get localized name with fallback
     */
    public function getLocalizedNameAttribute(): string
    {
        return format_translatable($this->name, app()->getLocale()) 
            ?: (is_array($this->name) ? ($this->name['en'] ?? $this->name['ar'] ?? 'Shipping') : (string)($this->name ?: 'Shipping'));
    }

    /**
     * Get localized delivery time with fallback
     */
    public function getLocalizedDeliveryTimeAttribute(): ?string
    {
        return format_translatable($this->delivery_time, app()->getLocale());
    }
}
