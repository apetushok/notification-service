<?php

namespace App\Models;

use App\Enums\NotificationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Notification extends Model
{
    use HasUuids;

    protected $table = 'notifications';

    protected $fillable = [
        'batch_id',
        'channel',
        'priority',
        'recipient',
        'recipient_id',
        'content',
        'content_variables',
        'metadata',
        'status',
        'status_reason',
        'status_history',
        'provider',
        'provider_message_id',
        'provider_response',
        'idempotency_key',
        'attempt_count',
        'max_attempts',
        'queued_at',
        'sending_at',
        'sent_at',
        'delivered_at',
        'failed_at',
        'next_attempt_at',
    ];

    protected $casts = [
        'content_variables' => 'array',
        'metadata' => 'array',
        'status_history' => 'array',
        'provider_response' => 'array',
        'attempt_count' => 'integer',
        'max_attempts' => 'integer',
        'queued_at' => 'datetime',
        'sending_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
        'next_attempt_at' => 'datetime',
    ];

    public function batch()
    {
        return $this->belongsTo(NotificationBatch::class, 'batch_id');
    }

    public function attempts()
    {
        return $this->hasMany(NotificationAttempt::class);
    }
}
