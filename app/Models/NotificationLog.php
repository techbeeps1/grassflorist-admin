<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'channel',
        'event_key',
        'recipient',
        'subject_or_sender',
        'content',
        'status',
        'error_message',
        'response_payload',
        'ip_address',
    ];

    protected $casts = [
        'response_payload' => 'array',
    ];

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
