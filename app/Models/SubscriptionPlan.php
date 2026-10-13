<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $table = 'subscription_plans';

    protected $guarded = ['id'];

    protected $casts = [
        'name' => 'array',
        'badge' => 'array',
        'description' => 'array',
        'details_content' => 'array',
        'gallery' => 'array',
        'features' => 'array',
        'allowed_frequencies' => 'array',
        'duration_discounts' => 'array',
        'starting_price' => 'float',
        'original_price' => 'float',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Localized Name
     */
    public function getLocalizedName(string $locale = 'ar'): string
    {
        if (is_array($this->name)) {
            return $this->name[$locale] ?? $this->name['en'] ?? reset($this->name);
        }
        return (string)$this->name;
    }

    /**
     * Localized Description
     */
    public function getLocalizedDescription(string $locale = 'ar'): string
    {
        if (is_array($this->description)) {
            return $this->description[$locale] ?? $this->description['en'] ?? reset($this->description);
        }
        return (string)$this->description;
    }

    /**
     * Localized Details Content
     */
    public function getLocalizedDetails(string $locale = 'ar'): string
    {
        if (is_array($this->details_content)) {
            return $this->details_content[$locale] ?? $this->details_content['en'] ?? reset($this->details_content);
        }
        return (string)($this->details_content ?: $this->getLocalizedDescription($locale));
    }

    /**
     * Localized Badge
     */
    public function getLocalizedBadge(string $locale = 'ar'): ?string
    {
        if (is_array($this->badge)) {
            return $this->badge[$locale] ?? $this->badge['en'] ?? reset($this->badge);
        }
        return $this->badge ? (string)$this->badge : null;
    }

    /**
     * Image URL with remote storage fallback
     */
    public function getImageUrlAttribute(): string
    {
        if (!empty($this->image)) {
            return function_exists('storage_media_url') ? storage_media_url($this->image) : asset('storage/' . ltrim($this->image, '/'));
        }
        if ($this->product && !empty($this->product->image_url)) {
            return $this->product->image_url;
        }
        return 'https://api.grassflorist.com/storage/products/g01720flower20delivery20jeddah202-600x600-1.jpg';
    }

    /**
     * Resolved Gallery URLs
     */
    public function getGalleryUrlsAttribute(): array
    {
        $gallery = $this->gallery;
        if (empty($gallery) && $this->product) {
            $gallery = $this->product->gallery_urls;
        }
        if (empty($gallery)) {
            return [$this->image_url];
        }
        return array_map(function ($item) {
            return function_exists('storage_media_url') ? storage_media_url($item) : asset('storage/' . ltrim($item, '/'));
        }, (array)$gallery);
    }
}
