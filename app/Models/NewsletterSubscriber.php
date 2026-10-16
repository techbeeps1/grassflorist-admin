<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewsletterSubscriber extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'locale',
        'coupon_code',
        'status',
        'ip_address',
        'subscribed_at',
    ];

    protected $casts = [
        'subscribed_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::created(function ($subscriber) {
            try {
                \App\Services\Webhooks\WebhookService::dispatch(
                    \App\Services\Webhooks\WebhookService::EVENT_NEWSLETTER_SUBSCRIBED,
                    [
                        'subscriber_id' => $subscriber->id,
                        'email' => $subscriber->email,
                        'locale' => $subscriber->locale,
                        'status' => $subscriber->status,
                        'coupon_code' => $subscriber->coupon_code,
                        'subscribed_at' => $subscriber->subscribed_at?->toIso8601String() ?: now()->toIso8601String(),
                    ],
                    (string)$subscriber->id,
                    'App\Models\NewsletterSubscriber'
                );
            } catch (\Throwable $e) {
                logger()->error('Webhook newsletter.subscribed error: ' . $e->getMessage());
            }
        });
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
