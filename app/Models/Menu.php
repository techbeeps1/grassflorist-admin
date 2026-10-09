<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'location',
        'is_active',
        'items',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'items' => 'array',
        'settings' => 'array',
    ];

    public function menuItems()
    {
        return $this->hasMany(MenuItem::class);
    }
}