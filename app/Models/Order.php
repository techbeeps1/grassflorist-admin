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

    protected $fillable = [
        'source_id',
        'order_number',
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
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'date_paid' => 'datetime',
        'ordered_at' => 'datetime',
        'meta_data' => 'array',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'delivery_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];
    

    public function customer(): BelongsTo
    {
        return $this->belongsTo(related: Customer::class);
    }
    public function customername()
    {
        return $this->belongsTo(Customer::class, 'user_id');
    }

    public function recoveredCarts(): HasMany
    {
        return $this->hasMany(Cart::class, 'recovered_order_id');
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
