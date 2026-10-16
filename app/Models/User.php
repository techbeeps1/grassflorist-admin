<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'role',
        'permissions',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'permissions' => 'array',
        ];
    }

    protected static function booted()
    {
        static::created(function ($user) {
            try {
                \App\Services\Webhooks\WebhookService::dispatch(
                    \App\Services\Webhooks\WebhookService::EVENT_USER_REGISTERED,
                    [
                        'user_id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                        'registered_at' => now()->toIso8601String(),
                    ],
                    (string)$user->id,
                    'App\Models\User'
                );
            } catch (\Throwable $e) {
                logger()->error('Webhook user.registered error: ' . $e->getMessage());
            }
        });

        static::updated(function ($user) {
            try {
                if ($user->wasChanged('password')) {
                    \App\Services\Webhooks\WebhookService::dispatch(
                        \App\Services\Webhooks\WebhookService::EVENT_USER_PASSWORD_CHANGED,
                        [
                            'user_id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                            'role' => $user->role,
                            'updated_at' => now()->toIso8601String(),
                        ],
                        (string)$user->id,
                        'App\Models\User'
                    );
                }
            } catch (\Throwable $e) {
                logger()->error('Webhook user.password_changed error: ' . $e->getMessage());
            }
        });
    }



    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->map(fn (string $name) => Str::of($name)->substr(0, 1))
            ->implode('');
    }
   
        public function vendor()
        {
        return $this->hasOne(\App\Models\Vendor::class);
        }

       

        public function isAdmin(): bool
        {
            return $this->role === 'admin';
        }

        public function isVendor(): bool
        {
            return $this->role === 'vendor';
        }

        public function isTeam(): bool
        {
            return $this->role === 'team';
        }

        /**
         * Check if user has permission to access a section/resource
         */
        public function hasPermission(string $permission): bool
        {
            if ($this->isAdmin()) {
                return true;
            }

            if (! $this->isTeam()) {
                return false;
            }

            $perms = $this->permissions ?? [];
            return in_array($permission, $perms, true);
        }

        /**
         * Check if user has any of the given permissions
         */
        public function hasAnyPermission(array $permissions): bool
        {
            if ($this->isAdmin()) {
                return true;
            }

            if (! $this->isTeam()) {
                return false;
            }

            $perms = $this->permissions ?? [];
            foreach ($permissions as $p) {
                if (in_array($p, $perms, true)) {
                    return true;
                }
            }

            return false;
        }

        // Relationship to vendor profile if you still want separate vendor details
        public function vendorProfile()
        {
        return $this->hasOne(Vendor::class);
        }
         public function getVendorNameAttribute()
    {
        return $this->vendor?->name ?? 'N/A';
    }

    /**
     * Determine if the user can access the given Filament panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // 1. Account must be active
        if (! $this->is_active) {
            return false;
        }

        // 2. Admin can access if active
        if ($this->isAdmin()) {
            return true;
        }

        // 3. Vendor can only access if active AND approved
        if ($this->isVendor()) {
            return $this->vendor && $this->vendor->approval_status === 'approved';
        }

        // 4. Team member can access if active
        if ($this->isTeam()) {
            return true;
        }

        return false;
    }

    /**
     * Helper to check if vendor is fully approved and active.
     */
    public function isApprovedVendor(): bool
    {
        return $this->isVendor() && $this->is_active && $this->vendor?->approval_status === 'approved';
    }
}
