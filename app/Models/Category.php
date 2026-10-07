<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory, \App\Traits\HasTranslations;

    protected $fillable = [
        'source_id',
        'name', 
        'slug', 
        'slug_ar',
        'parent_id',
        'cat_image',
        'is_visible', 
        'description',
        'meta_tag_title',
        'meta_tag_description',
        'meta_tag_keywords',
        'meta_data',
        'vendor_id',
        'updated_at',
        'updated_by',
    ];

    protected array $translatable = [
        'name',
        'description',
        'meta_tag_title',
        'meta_tag_description',
        'meta_tag_keywords',
    ];

    protected $casts = [
        'meta_data' => 'array',
        'is_visible' => 'boolean',
    ];  

    protected static function boot()
    {
            parent::boot();

            static::saving(function ($model) {
            // $postfix = $model->parent_id 
            // ? '-' . \App\Models\Category::find($model->parent_id)?->slug 
            // : '-books';

            // // Ensure slug ends with the suffix
            // if (!str_ends_with($model->slug, $postfix)) {
            //     $model->slug .= $postfix;
            // }
        });
    }

    public function parent(): BelongsTo
    {
            return $this->belongsTo(related:Category::class, foreignKey: 'parent_id');
    } 

    public function child(): HasMany
    {   
        //return $this->hasMany(related:Category::class, foreignKey: 'parent_id');
        return $this->hasMany(Category::class, 'parent_id')->with('child');
    }
    
  

    public function products(): BelongsToMany
    {   
        return $this->belongsToMany(related:Product::class);

    }

    protected static function booted(): void
    {
        static::updating(function ($category) {
            if (auth()->check()) {
                $category->updated_by = auth()->id();
            }
        });
        static::creating(function ($category) {
            if (auth()->check()) {
                $category->updated_by = auth()->id();
            }
        });
    }
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Helper: Full Public Category Image URL
    public function getImageUrlAttribute(): ?string
    {
        if (empty($this->cat_image)) {
            return null;
        }
        return str_starts_with($this->cat_image, 'http')
            ? $this->cat_image
            : asset('storage/' . $this->cat_image);
    }
}
