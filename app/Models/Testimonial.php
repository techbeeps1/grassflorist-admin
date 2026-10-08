<?php

namespace App\Models;

use App\Traits\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Testimonial extends Model
{
    use HasFactory, HasTranslations;

    protected $fillable = [
        'author_name',
        'city',
        'occasion_tag',
        'content',
        'rating',
        'avatar',
        'is_verified',
        'sort_order',
        'is_active',
        'updated_by',
    ];

    protected array $translatable = [
        'author_name',
        'city',
        'occasion_tag',
        'content',
    ];

    protected $casts = [
        'rating' => 'float',
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });
        static::creating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('id', 'desc');
    }
}
