<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Customer extends Authenticatable implements CanResetPasswordContract, JWTSubject
{
    use HasFactory, Notifiable, CanResetPassword;

    protected $fillable = [
        'source_id',
        'username',
        'first_name',
        'last_name',
        'email',
        'phone',
        'date_of_birth',
        'address',
        'zip_code',
        'city',
        'district',
        'state',
        'country',
        'address_2',
        'password',
        'meta_data',
        'registered_at',
        'email_verified_at',
        'email_verification_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'email_verification_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'registered_at' => 'datetime',
            'meta_data' => 'array',
            'password' => 'hashed',
        ];
    }

    public function getNameAttribute(): string
    {
        $full = trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
        return !empty($full) ? $full : ($this->username ?: 'Customer');
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }

    /**
     * Send password reset notification using Grass Florist branded template & SMS
     */
    public function sendPasswordResetNotification($token): void
    {
        $locale = request()?->header('X-Locale')
            ?: (request()?->input('locale')
            ?: (request()?->input('lang')
            ?: (app()->getLocale() ?: 'ar')));
        $locale = in_array(strtolower(substr((string)$locale, 0, 2)), ['en', 'ar'], true) ? strtolower(substr((string)$locale, 0, 2)) : 'ar';

        $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:3000'), '/');
        $langPrefix = $locale === 'en' ? '/en' : '';
        $resetUrl = "{$frontendUrl}{$langPrefix}/reset-password?token={$token}&email=" . urlencode($this->email);

        /** @var \App\Services\Notifications\NotificationService $notificationService */
        $notificationService = app(\App\Services\Notifications\NotificationService::class);
        $notificationService->sendPasswordResetNotification($this, $resetUrl, (string)$token, $locale);
    }

    public function subscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Subscription::class, 'customer_id');
    }

    public function activeSubscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Subscription::class, 'customer_id')->where('status', 'active');
    }
}