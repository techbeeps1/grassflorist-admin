<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_key',
        'name',
        'subject_en',
        'subject_ar',
        'body_en',
        'body_ar',
        'recipient_type',
        'notification_emails',
        'allowed_shortcodes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Render template with provided dynamic placeholders
     */
    public function render(array $data, string $locale = 'en'): array
    {
        $subject = ($locale === 'ar' && !empty($this->subject_ar)) ? $this->subject_ar : $this->subject_en;
        $body = ($locale === 'ar' && !empty($this->body_ar)) ? $this->body_ar : $this->body_en;

        foreach ($data as $key => $value) {
            $replacement = '';
            if (is_array($value)) {
                $replacement = ($locale === 'ar' && !empty($value['ar']))
                    ? $value['ar']
                    : ($value['en'] ?? ($value['ar'] ?? (is_scalar(reset($value)) ? reset($value) : '')));
            } elseif (is_scalar($value) || is_null($value)) {
                $replacement = (string)$value;
            } else {
                continue;
            }

            $subject = str_replace('{' . $key . '}', (string)$replacement, $subject);
            $body = str_replace('{' . $key . '}', (string)$replacement, $body);
        }

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }
}
