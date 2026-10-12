<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_key',
        'name',
        'message_en',
        'message_ar',
        'recipient_type',
        'notification_phones',
        'allowed_shortcodes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Render SMS message with provided dynamic placeholders
     */
    public function render(array $data, string $locale = 'ar'): string
    {
        $message = ($locale === 'ar' && !empty($this->message_ar)) ? $this->message_ar : $this->message_en;

        foreach ($data as $key => $value) {
            if (is_scalar($value) || is_null($value)) {
                $message = str_replace('{' . $key . '}', (string)$value, $message);
            }
        }

        return trim($message);
    }
}
