<?php
// app/Models/Coupon.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Coupon extends Model
{
    // protected $fillable = [
    //     'code', 'type', 'value', 'min_cart_amount', 
    //     'valid_from', 'valid_to', 'is_active'
    // ];
    
    protected $fillable = [
        'code',
        'type',
        'value',
        'max_discount_amount',
        'category_id',
        'min_cart_amount',
        'max_cart_amount',
        'usage_limit',
        'user_limit',
        'is_first_order_only',
        'is_subscriber_only',
        'payment_method_restriction',
        'valid_from',
        'valid_to',
        'is_active',
        'exclude_categories',
    ];

    protected $casts = [
        'valid_from' => 'datetime',
        'valid_to' => 'datetime',
        'category_id' => 'array',
        'is_first_order_only' => 'boolean',
        'is_subscriber_only' => 'boolean',
        'is_active' => 'boolean',
        'value' => 'float',
        'max_discount_amount' => 'float',
        'min_cart_amount' => 'float',
        'max_cart_amount' => 'float',
    ];

    /**
     * Scope to get only valid coupons
     */
    public function scopeValid(Builder $query): Builder
    {
        $now = Carbon::now();
        
        return $query->where('is_active', true)
            ->where('valid_from', '<=', $now)
            ->where('valid_to', '>=', $now);
    }

    /**
     * Calculate discount amount based on cart total
     */
    public function calculateDiscount(float $amount): float
    {
        if ($this->type === 'fixed') {
            return min((float) $this->value, $amount);
        }
        
        // For percentage discount
        $discount = round($amount * ((float) $this->value / 100), 2);
        
        // Apply maximum discount cap if configured
        if ($this->max_discount_amount && $this->max_discount_amount > 0) {
            $discount = min($discount, (float) $this->max_discount_amount);
        }
        
        return min($discount, $amount);
    }

    /**
     * Check if coupon is valid for payment method (all, online_only, cod_only)
     */
    public function isValidForPaymentMethod(?string $paymentMethod): bool
    {
        if (empty($this->payment_method_restriction) || $this->payment_method_restriction === 'all') {
            return true;
        }

        if (empty($paymentMethod)) {
            return true;
        }

        $isOnline = in_array(strtolower($paymentMethod), ['razorpay', 'online', 'prepaid']);
        $isCod = in_array(strtolower($paymentMethod), ['cod', 'cash_on_delivery']);

        if ($this->payment_method_restriction === 'online_only' && !$isOnline) {
            return false;
        }

        if ($this->payment_method_restriction === 'cod_only' && !$isCod) {
            return false;
        }

        return true;
    }

    /**
     * Check if coupon is valid for a given amount
     */
    public function isValidForAmount(float $amount): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = Carbon::now();
        if ($now->lt($this->valid_from) || $now->gt($this->valid_to)) {
            return false;
        }

        if ($this->min_cart_amount && $amount < $this->min_cart_amount) {
            return false;
        }

        if ($this->max_cart_amount && $amount > $this->max_cart_amount) {
            return false;
        }

        return true;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(related: Category::class);
    }

    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Order::class, 'coupon_code', 'code');
    }
    protected static function booted(): void
    {
        static::updating(function ($home) {
            if (auth()->check()) {
                $home->updated_by = auth()->id();
            }
        });
        static::creating(function ($post) {
            $post->updated_by = auth()->id();
        });
    }
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}