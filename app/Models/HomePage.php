<?php
// app/Models/HomePage.php

namespace App\Models;

use App\Traits\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class HomePage extends Model
{
    use HasTranslations;

    protected $guarded = [];

    protected array $translatable = [
        'page_title',
        'popular_title',
        'popular_subtitle',
        'featured_products_title',
        'best_sellers_title',
        'best_sellers_subtitle',
        'latest_products_title',
        'blog_title',
        'blog_subtitle',
        'testimonials_title',
        'testimonials_subtitle',
        'faq_title',
        'faq_subtitle',
        'banner_title',
        'banner_description',
        'banner_button_title',
        'cat_sec_title',
        'cat_sec_description',
        'cat_tab_title',
        'cat_tab_subtitle',
        'cat_tab_description',
        'feature_title',
        'feature_description',
        'meta_tag_title',
        'meta_tag_description',
        'meta_tag_keywords',
    ];
    
    protected $casts = [
        'slider_section' => 'array',
        'mslider_section' => 'array',
        'popular_category' => 'array', 
        'banner_images' => 'array',
        'category_sections' => 'array',
        'cat_tabs' => 'array',
        'testimonial_sections' => 'array',
        'featured_products' => 'array',
        'best_sellers' => 'array',
        'categories' => 'array',
        'custom_sections' => 'array',
        'benefits_section' => 'array',
        'events_section' => 'array',
    ];
    
    public function featuredProducts()
    {
        return $this->belongsToMany(Product::class, 'home_page_featured_products');
    }
    
    public function bestSellers()
    {
        return $this->belongsToMany(Product::class, 'home_page_best_sellers');
    }
    
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'home_page_categories');
    }
    
    // Accessor for slider images with full URLs
    protected function sliderImages(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => json_decode($value, true) ? array_map(function($image) {
                return asset('storage/'.$image);
            }, json_decode($value, true)) : [],
        );
    }
    protected static function booted(): void
    {
        static::updating(function ($home) {
            if (auth()->check()) {
                $home->updated_by = auth()->id();
            }
        });
        static::creating(function ($post) {
            $post->updated_by = auth()->id();
        });
    }
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}