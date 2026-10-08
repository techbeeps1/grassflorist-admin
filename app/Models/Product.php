<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    use HasFactory, \App\Traits\HasTranslations;

    protected $fillable = [
        'source_id',
        'production_id',
        'name',
        'slug',
        'slug_ar',
        'sub_title',
        'sku',
        'category_id',
        'sub_category_id',
        'child_category_id',
        'description',
        'meta_tag_title',
        'meta_tag_description',
        'meta_tag_keywords',
        'image',
        'gallery',
        'model',
        'author',
        'year',
        'mrp',
        'number_of_pages',
        'book_language',
        'weight',
        'isbn',
        'isbn10',
        'isbn13',
        'quantity',
        'price',
        'is_visible',
        'type',
        'meta_data',
        'updated_by',
        'published_at',
        'vendor_id'
    ];

    protected array $translatable = [
        'name',
        'description',
        'sub_title',
        'meta_tag_title',
        'meta_tag_description',
        'meta_tag_keywords',
    ];

    protected $casts = [
        'gallery' => 'array',
        'category_id' => 'array',
        'meta_data' => 'array',
        'is_visible' => 'boolean',  
        'published_at' => 'datetime', 
    ];

    protected static function boot()
    {
        parent::boot();
        // Your existing boot logic
    }

    // Vendor Relationship - Already exists ✅
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    //  User (Admin) who updated the product
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    //  Production (Publication) Relationship
    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }

    //  Category Relationship (Single category)
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    // Categories Relationship (Multiple categories)
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    // Order Items Relationship
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    // Scope: Get products by vendor
    public function scopeForVendor($query, $vendorId)
    {
        return $query->where('vendor_id', $vendorId);
    }

    //Scope: Get admin products (no vendor)
    public function scopeAdminProducts($query)
    {
        return $query->whereNull('vendor_id');
    }

    //Scope: Get vendor products
    public function scopeVendorProducts($query)
    {
        return $query->whereNotNull('vendor_id');
    }

    //Scope: Get active products
    public function scopeActive($query)
    {
        return $query->where('is_visible', 1);
    }

    /**
     * Scope a query to only include products visible to website customers:
     * 1. Product itself must be active (is_visible = 1)
     * 2. If vendor product: vendor must be approved AND vendor user must be active
     */
    public function scopeVisibleToCustomers($query)
    {
        return $query->where('is_visible', 1)
            ->where(function ($q) {
                $q->whereNull('vendor_id')
                  ->orWhereHas('vendor', function ($vendorQuery) {
                      $vendorQuery->where('approval_status', 'approved')
                                  ->whereHas('user', function ($userQuery) {
                                      $userQuery->where('is_active', 1);
                                  });
                  });
            });
    }

    //Helper: Check if product has vendor
    public function hasVendor(): bool
    {
        return !is_null($this->vendor_id);
    }

    //Helper: Get vendor name or "Admin Product"
    public function getVendorNameAttribute(): string
    {
        if ($this->vendor) {
            return $this->vendor->vendor_name;
        }
        return 'Admin Product 🏪';
    }

    //Helper: Check if product is active
    public function isActive(): bool
    {
        return $this->is_visible == 1;
    }

    // Helper: Full Public Image URL
    public function getImageUrlAttribute(): ?string
    {
        if (empty($this->image)) {
            return null;
        }
        return str_starts_with($this->image, 'http')
            ? $this->image
            : asset('storage/' . $this->image);
    }

    // Helper: Full Gallery Images URLs
    public function getGalleryUrlsAttribute(): array
    {
        $gallery = $this->gallery ?? [];
        if (!is_array($gallery)) {
            return [];
        }
        return array_map(function ($img) {
            return str_starts_with($img, 'http') ? $img : asset('storage/' . $img);
        }, $gallery);
    }

    // Boot Method - Auto set updated_by
    protected static function booted(): void
    {
        static::updating(function ($product) {
            if (auth()->check()) {
                $product->updated_by = auth()->id();
            }
        });

        static::creating(function ($product) {
            if (auth()->check()) {
                $product->updated_by = auth()->id();
            }
        });
    }
}