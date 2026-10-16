<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'headers' => 'array',
        'response_headers' => 'array',
        'attempts' => 'integer',
        'duration_ms' => 'integer',
        'response_status' => 'integer',
    ];

    /**
     * Scope for successful deliveries (HTTP 2xx)
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope for failed deliveries
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Get pretty JSON payload
     */
    public function getFormattedPayloadAttribute(): string
    {
        if (empty($this->payload)) {
            return '{}';
        }

        $decoded = json_decode($this->payload, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return $this->payload;
    }

    /**
     * Get pretty JSON response
     */
    public function getFormattedResponseAttribute(): string
    {
        if (empty($this->response_body)) {
            return '';
        }

        $decoded = json_decode($this->response_body, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return $this->response_body;
    }
}
