<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_id',
        'status',
        'customer_name',
        'customer_email',
        'customer_phone',
        'recovery_token',
        'abandoned_at',
        'recovered_at',
        'recovered_order_id',
        'reminder_sent_count',
        'last_reminder_at',
        'last_reminder_channel',
        'admin_notes',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'abandoned_at' => 'datetime',
        'recovered_at' => 'datetime',
        'last_reminder_at' => 'datetime',
        'reminder_sent_count' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'user_id');
    }

    public function recoveredOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'recovered_order_id');
    }

    /**
     * Get or generate a unique recovery token
     */
    public function ensureRecoveryToken(): string
    {
        if (empty($this->recovery_token)) {
            $this->recovery_token = Str::random(40);
            $this->saveQuietly();
        }

        return $this->recovery_token;
    }

    /**
     * Get the full URL to recover this cart
     */
    public function getRecoveryUrl(): string
    {
        $token = $this->ensureRecoveryToken();
        $baseUrl = rtrim(config('app.frontend_url') ?: (env('FRONTEND_URL') ?: url('/')), '/');

        return "{$baseUrl}/cart/recover?token={$token}";
    }

    /**
     * Calculate total price of all items in cart
     */
    public function calculateTotal(): float
    {
        return (float) $this->items->sum(function ($item) {
            $price = $item->price ?? ($item->product?->price ?? 0);
            return (float) $price * (int) $item->quantity;
        });
    }

    /**
     * Total quantity of items in cart
     */
    public function getItemsCount(): int
    {
        return (int) $this->items->sum('quantity');
    }

    /**
     * Get customer full name from cart record or related customer profile
     */
    public function getEffectiveCustomerName(): string
    {
        if (!empty($this->customer_name)) {
            return trim($this->customer_name);
        }

        if ($this->customer) {
            $fullName = trim(($this->customer->first_name ?? '') . ' ' . ($this->customer->last_name ?? ''));
            if (!empty($fullName)) {
                return $fullName;
            }
        }

        return 'Customer';
    }

    /**
     * Get effective customer email
     */
    public function getEffectiveCustomerEmail(): ?string
    {
        return $this->customer_email ?: ($this->customer?->email ?: null);
    }

    /**
     * Get effective customer phone (cleaned)
     */
    public function getEffectiveCustomerPhone(): ?string
    {
        $phone = $this->customer_phone ?: ($this->customer?->phone ?: null);
        if (!$phone) {
            return null;
        }

        $clean = preg_replace('/\D/', '', (string) $phone);
        if (str_starts_with($clean, '91') && strlen($clean) > 10) {
            $clean = substr($clean, 2);
        } elseif (str_starts_with($clean, '0') && strlen($clean) > 10) {
            $clean = substr($clean, 1);
        }

        return $clean;
    }

    /**
     * Formats default reminder message text in English (without emojis/icons)
     */
    public function getReminderMessageText(): string
    {
        $name = $this->getEffectiveCustomerName();
        $total = number_format($this->calculateTotal(), 2);
        $recoveryUrl = $this->getRecoveryUrl();

        $itemsSummary = [];
        foreach ($this->items->take(3) as $item) {
            $productName = $item->product?->name ?? 'Product';
            if (strlen($productName) > 40) {
                $productName = substr($productName, 0, 37) . '...';
            }
            $itemsSummary[] = "- {$productName} (Qty: {$item->quantity})";
        }

        $itemsText = implode("\n", $itemsSummary);
        if ($this->items->count() > 3) {
            $remaining = $this->items->count() - 3;
            $itemsText .= "\n- and {$remaining} more item(s)";
        }

        $appName = config('app.name', 'Grass Florist');

        return "Dear {$name},\n\n" .
            "You have items waiting in your shopping bag on {$appName}:\n" .
            "{$itemsText}\n\n" .
            "Total Cart Value: {$total} SAR\n\n" .
            "Direct link to complete your order:\n" .
            "{$recoveryUrl}\n\n" .
            "If you need any assistance or have questions, please reply to this message. We are here to help.\n\n" .
            "Regards,\n" .
            "Team {$appName}";
    }

    public function getWhatsAppMessageText(): string
    {
        return $this->getReminderMessageText();
    }

    /**
     * Generate wa.me link for WhatsApp Web / App
     */
    public function getWhatsAppUrl(): ?string
    {
        $phone = $this->getEffectiveCustomerPhone();
        if (!$phone || strlen($phone) !== 10) {
            return null;
        }

        $text = rawurlencode($this->getWhatsAppMessageText());
        return "https://wa.me/91{$phone}?text={$text}";
    }

    /**
     * Scopes
     */
    public function scopeAbandoned($query)
    {
        return $query->where('status', 'abandoned');
    }

    public function scopeRecovered($query)
    {
        return $query->where('status', 'recovered');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}