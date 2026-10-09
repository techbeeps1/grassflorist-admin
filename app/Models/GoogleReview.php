<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoogleReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'google_review_id',
        'author_name',
        'author_photo_url',
        'rating',
        'comment',
        'language',
        'relative_time_description',
        'published_at',
        'is_visible',
        'is_featured',
        'sort_order',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_visible' => 'boolean',
        'is_featured' => 'boolean',
        'published_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function ($model) {
            if (empty($model->language)) {
                $model->language = preg_match('/\p{Arabic}/u', $model->comment ?? '') ? 'ar' : 'en';
            }
        });
    }

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function getAvatarUrlAttribute(): string
    {
        if ($this->author_photo_url) {
            return $this->author_photo_url;
        }

        $encodedName = urlencode($this->author_name ?: 'Guest');
        return "https://ui-avatars.com/api/?name={$encodedName}&background=1b3d2f&color=ffffff&bold=true&rounded=true";
    }
}
