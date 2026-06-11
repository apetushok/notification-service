<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class NotificationAttempt extends Model
{
    use HasUuids;

    protected $table = 'notification_attempts';

    protected $fillable = [
        'notification_id',
        'attempt_number',
        'provider',
        'status',
        'http_code',
        'error_message',
        'error_code',
        'request_payload',
        'response_payload',
        'response_time_ms',
        'provider_message_id',
        'attempted_at',
    ];

    protected $casts = [
        'attempt_number' => 'integer',
        'http_code' => 'integer',
        'request_payload' => 'array',
        'response_payload' => 'array',
        'response_time_ms' => 'float',
        'attempted_at' => 'datetime',
    ];

    public function notification()
    {
        return $this->belongsTo(Notification::class);
    }
}
