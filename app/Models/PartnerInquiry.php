<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartnerInquiry extends Model
{
    use HasFactory;

    protected $fillable = [
        'country',
        'city',
        'company_name',
        'website',
        'category',
        'social_media',
        'first_name',
        'last_name',
        'contact_role',
        'email',
        'country_code',
        'phone',
        'company_profile_file',
        'product_list_file',
        'locale',
        'status',
        'admin_notes',
        'ip_address',
    ];

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getFullPhoneAttribute(): string
    {
        return trim("{$this->country_code} {$this->phone}");
    }

    public function getCompanyProfileUrlAttribute(): ?string
    {
        if (empty($this->company_profile_file)) {
            return null;
        }
        return str_starts_with($this->company_profile_file, 'http')
            ? $this->company_profile_file
            : asset('storage/' . ltrim($this->company_profile_file, '/'));
    }

    public function getProductListUrlAttribute(): ?string
    {
        if (empty($this->product_list_file)) {
            return null;
        }
        return str_starts_with($this->product_list_file, 'http')
            ? $this->product_list_file
            : asset('storage/' . ltrim($this->product_list_file, '/'));
    }
}
