<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'type',
        'title',
        'description',
        'properties',
        'user_id',
        'user_name',
        'ip_address',
    ];

    protected $casts = [
        'properties' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Easy helper method to record an activity event
     */
    public static function log(
        int|Order $order,
        string $type,
        string $title,
        ?string $description = null,
        ?array $properties = null,
        ?int $userId = null,
        ?string $userName = null
    ): self {
        $orderId = $order instanceof Order ? $order->id : $order;
        $currentUser = auth()->user();

        return static::create([
            'order_id' => $orderId,
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'properties' => $properties,
            'user_id' => $userId ?? $currentUser?->id,
            'user_name' => $userName ?? ($currentUser?->name ?? 'System'),
            'ip_address' => request()?->ip(),
        ]);
    }
}
