<?php

namespace App\Repositories;

use App\Models\Notification;
use App\Enums\NotificationStatus;
use App\Enums\NotificationPriority;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NotificationRepository
{
    public function find(string $id): ?Notification
    {
        return Notification::find($id);
    }

    public function findByIdempotencyKey(string $key): ?Notification
    {
        return Notification::where('idempotency_key', $key)->first();
    }

    public function create(array $data): Notification
    {
        return Notification::create($data);
    }

    public function update(string $id, array $data): bool
    {
        return Notification::where('id', $id)->update($data) > 0;
    }

    public function getQueuedByPriority(NotificationPriority $priority, int $limit = 100): Collection
    {
        return Notification::where('status', NotificationStatus::QUEUED->value)
            ->where('priority', $priority->value)
            ->orderBy('created_at')
            ->limit($limit)
            ->get();
    }

    public function getReadyForRetry(int $limit = 100): Collection
    {
        return Notification::where('status', NotificationStatus::FAILED->value)
            ->whereColumn('attempt_count', '<', 'max_attempts')
            ->where('next_attempt_at', '<=', now())
            ->orderBy('priority', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getByRecipient(string $recipient, int $perPage = 20): LengthAwarePaginator
    {
        return Notification::where('recipient', $recipient)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function getByBatchId(string $batchId): Collection
    {
        return Notification::where('batch_id', $batchId)
            ->orderBy('created_at')
            ->get();
    }
}
