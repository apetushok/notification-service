<?php

namespace App\Models;

use App\Enums\BatchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotificationBatch extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'notification_batches';

    protected $fillable = [
        'batch_number',
        'channel',
        'priority',
        'content',
        'recipients',
        'metadata',
        'status',
        'total_recipients',
        'processed_count',
        'success_count',
        'failed_count',
        'created_by',
        'scheduled_at',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'recipients' => 'array',
        'metadata' => 'array',
        'total_recipients' => 'integer',
        'processed_count' => 'integer',
        'success_count' => 'integer',
        'failed_count' => 'integer',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'batch_id');
    }

    public function isCompleted(): bool
    {
        return $this->status === BatchStatus::COMPLETED->value
            || $this->status === BatchStatus::PARTIALLY_COMPLETED->value;
    }
}
