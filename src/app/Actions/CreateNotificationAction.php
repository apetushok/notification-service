<?php

namespace App\Actions;

use App\Enums\NotificationStatus;
use App\Enums\NotificationPriority;
use App\Exceptions\IdempotencyConflictException;
use App\Repositories\NotificationRepository;
use App\Services\IdempotencyService;

class CreateNotificationAction
{
    public function __construct(
        private readonly NotificationRepository $notifications,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function execute(array $data): array
    {
        $idempotencyKey = $data['idempotency_key'] ?? null;

        if ($idempotencyKey) {
            if ($this->idempotency->isProcessed($idempotencyKey)) {
                $result = $this->idempotency->getResult($idempotencyKey);
                return [
                    'notification' => $this->notifications->findByIdempotencyKey($idempotencyKey),
                    'idempotent' => true,
                    'cached_result' => $result,
                ];
            }

            if (!$this->idempotency->lock($idempotencyKey)) {
                throw new IdempotencyConflictException(
                    "Request with idempotency key '{$idempotencyKey}' is already in progress"
                );
            }
        }

        $notification = $this->notifications->create([
            'batch_id' => $data['batch_id'] ?? null,
            'channel' => $data['channel'],
            'priority' => $data['priority'] ?? NotificationPriority::NORMAL->value,
            'recipient' => $data['recipient'],
            'recipient_id' => $data['recipient_id'] ?? null,
            'content' => $data['content'],
            'content_variables' => $data['content_variables'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'status' => NotificationStatus::QUEUED->value,
            'idempotency_key' => $idempotencyKey,
            'max_attempts' => $this->getMaxAttempts($data['channel'], $data['priority']),
            'queued_at' => now(),
        ]);

        if ($idempotencyKey) {
            $this->idempotency->storeResult($idempotencyKey, [
                'notification_id' => $notification->id,
                'status' => $notification->status,
                'created_at' => $notification->created_at->toIso8601String(),
            ]);
        }

        return [
            'notification' => $notification,
            'idempotent' => false,
        ];
    }

    private function getMaxAttempts(string $channel, string $priority): int
    {
        $channelAttempts = config("notifications.channels.{$channel}.max_attempts", 3);
        $priorityAttempts = config("notifications.priorities.{$priority}.max_attempts", 3);

        return max($channelAttempts, $priorityAttempts);
    }
}
