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
