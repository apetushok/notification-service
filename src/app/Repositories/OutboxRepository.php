<?php

namespace App\Repositories;

use App\Enums\BatchStatus;
use App\Models\NotificationBatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OutboxRepository
{
    public function create(array $data): NotificationBatch
    {
        return NotificationBatch::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'batch_number' => $this->generateBatchNumber(),
            'channel' => $data['channel'],
            'priority' => $data['priority'],
            'content' => $data['content'],
            'recipients' => $data['recipients'],
            'metadata' => $data['metadata'] ?? null,
            'status' => BatchStatus::PENDING->value,
        ]);
    }

    private function generateBatchNumber(): string
    {
        return 'BATCH-' . now()->format('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    }

    public function getPending(int $limit = 50): Collection
    {
        return NotificationBatch::where('status', BatchStatus::PENDING->value)
            ->orderBy('created_at')
            ->limit($limit)
            ->get();
    }

    public function markAsProcessed(string $id): void
    {
        NotificationBatch::where('id', $id)->update([
            'status' => BatchStatus::PROCESSED->value,
            'processed_at' => now(),
        ]);
    }

    public function markAsFailed(string $id, string $error): void
    {
        NotificationBatch::where('id', $id)->update([
            'status' => BatchStatus::FAILED->value,
            'error_message' => $error,
            'attempt_count' => DB::raw('attempt_count + 1'),
        ]);
    }

    public function find(string $id): ?NotificationBatch
    {
        return NotificationBatch::find($id);
    }
}
