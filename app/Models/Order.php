<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;


class Order extends Model
{
    //
    use HasFactory, SoftDeletes;

    protected static function booted()
    {
        static::creating(function ($order) {
            if (empty($order->order_key)) {
                $order->order_key = 'wc_order_' . \Illuminate\Support\Str::random(20);
            }
        });
    }

    public function getOrderKeyAttribute($value)
    {
        if (!empty($value)) {
            return $value;
        }
        $key = 'wc_order_' . \Illuminate\Support\Str::random(20);
        if ($this->exists) {
            $this->attributes['order_key'] = $key;
            $this->saveQuietly();
        }
        return $key;
    }

    protected $fillable = [
        'source_id',
        'order_number',
        'order_key',
        'email',
        'first_name',
        'last_name',
        'user_id',
        'session_id',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'shipping_amount',
        'delivery_amount',
        'total_amount',
        'currency',
        'exchange_rate',
        'currency_amount',
        'sar_amount',
        'payment_method',
        'razorpay_order_id',
        'razorpay_payment_id',
        'payment_status',
        'status',
        'shipping_method',
        'delivery_date',
        'delivery_time',
        'delivery_message',
        'sender_name',
        'recipient_name',
        'recipient_phone',
        'song_link',
        'location_link',
        'order_language',
        'address',
        'address_2',
        'coupon_code',
        'customer_phone',
        'notes',
        'cancellation_reason',
        'cancelled_by',
        'failed_order_reason',
        'admin_remark',
        'customer_ip',
        'invoice_number',
        'zip_code',
        'city',
        'district',
        'state',
        'country',
        'tracking_id',
        'courier_partner',
        'date_paid',
        'ordered_at',
        'meta_data',
        'odoo_sync_status',
        'odoo_synced_at',
        'odoo_invoice_pdf_path',
        'odoo_invoice_pdf_url',
        'odoo_error',
        'subscription_id',
        'subscription_delivery_id',
        'is_recurring_order',
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'date_paid' => 'datetime',
        'ordered_at' => 'datetime',
        'odoo_synced_at' => 'datetime',
        'meta_data' => 'array',
        'is_recurring_order' => 'boolean',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'delivery_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function subscriptionDelivery(): BelongsTo
    {
        return $this->belongsTo(SubscriptionDelivery::class);
    }

    public function isPaid(): bool
    {
        return in_array(strtolower((string)$this->payment_status), ['paid', 'completed']);
    }

    /**
     * Resolve comprehensive payment method information (Gateway, Specific Brand, Card info).
     */
    public function getPaymentDetails(): array
    {
        $rawMethod = strtolower(trim((string)($this->payment_method ?? '')));
        $meta = is_array($this->meta_data) ? $this->meta_data : [];

        $brand = $meta['payment_brand'] ?? $meta['brand'] ?? null;
        $gateway = $meta['payment_gateway'] ?? null;
        $card = $meta['payment_card'] ?? null;

        // If not in meta, try to inspect latest successful PaymentLog
        if (!$brand || !$card) {
            $log = \App\Models\PaymentLog::where('order_id', $this->id)
                ->where('status', 'success')
                ->latest('id')
                ->first();

            if ($log && is_array($log->payload)) {
                $payload = $log->payload;
                if (!$brand) {
                    $brand = $payload['paymentBrand'] ?? $payload['brand'] ?? null;
                }
                if (!$gateway && !empty($log->gateway)) {
                    $gateway = $log->gateway;
                }
                if (!$card && !empty($payload['card'])) {
                    $card = [
                        'last4' => $payload['card']['last4Digits'] ?? null,
                        'holder' => $payload['card']['holder'] ?? null,
                        'bank' => $payload['card']['issuer']['bank'] ?? null,
                        'type' => $payload['card']['type'] ?? null,
                    ];
                }
            }
        }

        // Determine Brand Label & Normalized Method
        $normalized = strtolower(trim((string)($brand ?: $rawMethod)));

        if (str_contains($normalized, 'mada')) {
            $title = 'Mada Card';
            $badgeColor = 'success';
            $icon = 'heroicon-o-credit-card';
            $methodCode = 'mada';
        } elseif (str_contains($normalized, 'apple') || $normalized === 'applepay') {
            $title = 'Apple Pay';
            $badgeColor = 'gray';
            $icon = 'heroicon-o-device-phone-mobile';
            $methodCode = 'apple_pay';
        } elseif (str_contains($normalized, 'stc') || $normalized === 'stcpay') {
            $title = 'STC Pay';
            $badgeColor = 'purple';
            $icon = 'heroicon-o-device-phone-mobile';
            $methodCode = 'stc_pay';
        } elseif (str_contains($normalized, 'visa')) {
            $title = 'Visa Card';
            $badgeColor = 'info';
            $icon = 'heroicon-o-credit-card';
            $methodCode = 'visa';
        } elseif (str_contains($normalized, 'master')) {
            $title = 'Mastercard';
            $badgeColor = 'warning';
            $icon = 'heroicon-o-credit-card';
            $methodCode = 'mastercard';
        } elseif (str_contains($normalized, 'amex')) {
            $title = 'American Express';
            $badgeColor = 'info';
            $icon = 'heroicon-o-credit-card';
            $methodCode = 'amex';
        } elseif (str_contains($normalized, 'tabby')) {
            $title = 'Tabby';
            $badgeColor = 'emerald';
            $icon = 'heroicon-o-calendar-days';
            $methodCode = 'tabby';
        } elseif (str_contains($normalized, 'tamara')) {
            $title = 'Tamara';
            $badgeColor = 'warning';
            $icon = 'heroicon-o-calendar-days';
            $methodCode = 'tamara';
        } elseif (str_contains($normalized, 'paypal') || str_contains($normalized, 'ppcp')) {
            $title = 'PayPal';
            $badgeColor = 'info';
            $icon = 'heroicon-o-globe-alt';
            $methodCode = 'paypal';
        } elseif (str_contains($normalized, 'cod') || str_contains($normalized, 'cash')) {
            $title = 'Cash on Delivery';
            $badgeColor = 'warning';
            $icon = 'heroicon-o-banknotes';
            $methodCode = 'cod';
        } elseif (str_contains($normalized, 'razorpay')) {
            $title = 'Razorpay';
            $badgeColor = 'info';
            $icon = 'heroicon-o-credit-card';
            $methodCode = 'razorpay';
        } elseif (str_contains($normalized, 'card')) {
            $title = 'Credit Card';
            $badgeColor = 'info';
            $icon = 'heroicon-o-credit-card';
            $methodCode = 'card';
        } else {
            $title = ucfirst($rawMethod ?: 'N/A');
            $badgeColor = 'gray';
            $icon = 'heroicon-o-credit-card';
            $methodCode = $rawMethod ?: 'unknown';
        }

        return [
            'method_code' => $methodCode,
            'title' => $title,
            'badge_color' => $badgeColor,
            'icon' => $icon,
            'gateway' => $gateway ? ucfirst($gateway) : null,
            'card_last4' => $card['last4'] ?? null,
            'card_holder' => $card['holder'] ?? null,
            'card_bank' => $card['bank'] ?? null,
            'card_type' => $card['type'] ?? null,
        ];
    }

    public function getPaymentMethodTitleAttribute(): string
    {
        return $this->getPaymentDetails()['title'];
    }
    

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'user_id');
    }
    public function customername()
    {
        return $this->belongsTo(Customer::class, 'user_id');
    }

    public function recoveredCarts(): HasMany
    {
        return $this->hasMany(Cart::class, 'recovered_order_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(OrderActivity::class)->orderBy('id', 'desc');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(NotificationLog::class)->orderBy('id', 'desc');
    }

    public function odooSyncLogs(): HasMany
    {
        return $this->hasMany(OdooSyncLog::class)->orderBy('id', 'desc');
    }

    public function items(): HasMany
    {
        return $this->hasMany(related: OrderItem::class);
    }
    public function orderItems(): HasMany
    {
        return $this->hasMany(related: OrderItem::class);
    }
    public function shippingDetail()
    {
        return $this->hasOne(ShippingDetail::class);
    }
    public function getFullNameAttribute()
    {
    return $this->first_name . ' ' . $this->last_name;
    }
    public function product()
    {
    return $this->belongsTo(Product::class);
    }
    public function vendor()
    {
    return $this->belongsTo(Vendor::class);
    }

    public function vendorItems()
    {
        return $this->items()->where('vendor_id', $vendorId);
    }

    public function paymentLogs(): HasMany
    {
        return $this->hasMany(PaymentLog::class)->latest();
    }

    /**
     * Normalize and format any phone number to standard +91 XXXXXXXXXX format.
     */
    public static function formatPhoneNumber(?string $phone): string
    {
        if (empty($phone)) {
            return 'N/A';
        }

        $digits = preg_replace('/\D/', '', $phone);
        if (str_starts_with($digits, '91') && strlen($digits) > 10) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0') && strlen($digits) > 10) {
            $digits = substr($digits, 1);
        }
        $digits = substr($digits, -10);

        return !empty($digits) ? '+91 ' . $digits : $phone;
    }

    /**
     * Accessor for formatted phone number with +91 prefix.
     */
    public function getFormattedPhoneAttribute(): string
    {
        return self::formatPhoneNumber($this->customer_phone);
    }
}
