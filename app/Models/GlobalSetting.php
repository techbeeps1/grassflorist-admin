<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class GlobalSetting extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'topbar_enabled' => 'boolean',
        'sitemap_enabled' => 'boolean',
        'sitemap_include_products' => 'boolean',
        'sitemap_include_categories' => 'boolean',
        'sitemap_include_static_pages' => 'boolean',
        'sitemap_include_blog' => 'boolean',
        'llms_enabled' => 'boolean',
    ];

    protected $appends = [
        'site_logo_url',
        'site_logo_dark_url',
        'site_favicon_url',
    ];

    protected static function booted(): void
    {
        static::saving(function ($setting) {
            if (auth()->check()) {
                $setting->updated_by = auth()->id();
            }
        });

        static::saved(function () {
            Cache::forget('global_site_settings');
        });

        static::deleted(function () {
            Cache::forget('global_site_settings');
        });
    }

    /**
     * Singleton accessor for global settings with persistent caching
     */
    public static function current(): self
    {
        return Cache::rememberForever('global_site_settings', function () {
            return static::firstOrCreate(
                ['id' => 1],
                [
                    'site_name' => 'Grass Florist',
                    'site_tagline' => 'Online Flower & Gifts Delivery',
                    'footer_copyright' => '© ' . date('Y') . ' Grass Florist. All rights reserved.',
                ]
            );
        });
    }

    public function getSiteLogoUrlAttribute(): ?string
    {
        if (! $this->site_logo) {
            return null;
        }

        if (str_starts_with($this->site_logo, 'http')) {
            return $this->site_logo;
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->url($this->site_logo);
    }

    public function getSiteLogoDarkUrlAttribute(): ?string
    {
        if (! $this->site_logo_dark) {
            return null;
        }

        if (str_starts_with($this->site_logo_dark, 'http')) {
            return $this->site_logo_dark;
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->url($this->site_logo_dark);
    }

    public function getSiteFaviconUrlAttribute(): ?string
    {
        if (! $this->site_favicon) {
            return null;
        }

        if (str_starts_with($this->site_favicon, 'http')) {
            return $this->site_favicon;
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->url($this->site_favicon);
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
