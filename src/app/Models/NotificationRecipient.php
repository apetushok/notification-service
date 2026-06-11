<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotificationRecipient extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'notification_recipients';

    protected $fillable = [
        'recipient',
        'user_id',
        'channel',
        'channels',
        'status',
        'status_reason',
        'is_valid',
        'last_validated_at',
        'validation_method',
        'total_sent',
        'total_delivered',
        'total_failed',
        'last_notification_at',
        'preferences',
        'metadata',
    ];

    protected $casts = [
        'channels' => 'array',
        'preferences' => 'array',
        'metadata' => 'array',
        'is_valid' => 'boolean',
        'total_sent' => 'integer',
        'total_delivered' => 'integer',
        'total_failed' => 'integer',
        'last_validated_at' => 'datetime',
        'last_notification_at' => 'datetime',
    ];
}
