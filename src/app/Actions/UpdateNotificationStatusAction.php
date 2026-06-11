<?php

namespace App\Actions;

use App\Models\Notification;
use App\Enums\NotificationStatus;
use App\Repositories\NotificationRepository;
use App\Repositories\OutboxRepository;
use InvalidArgumentException;

class UpdateNotificationStatusAction
{
    public function __construct(
        private readonly NotificationRepository $notifications,
        private readonly OutboxRepository $batches,
    ) {}

    public function markAsSending(Notification $notification): void
    {
        $this->transition($notification, NotificationStatus::SENDING, [
            'sending_at' => now(),
        ]);
    }

    public function markAsSent(
        Notification $notification,
        string $provider,
        string $providerMessageId,
        array $providerResponse = []
    ): void {
        $this->transition($notification, NotificationStatus::SENT, [
            'sent_at' => now(),
            'provider' => $provider,
            'provider_message_id' => $providerMessageId,
            'provider_response' => $providerResponse,
        ]);
    }

    public function markAsDelivered(Notification $notification): void
    {
        $this->transition($notification, NotificationStatus::DELIVERED, [
            'delivered_at' => now(),
        ]);

        if ($notification->batch_id) {
            $this->batches->incrementSuccess($notification->batch_id);
        }
    }

    public function markAsFailed(
        Notification $notification,
        string $reason,
        ?int $retryAfterSeconds = null
    ): void {
        $newAttemptCount = $notification->attempt_count + 1;

        $data = [
            'status_reason' => $reason,
            'attempt_count' => $newAttemptCount,
            'failed_at' => now(),
        ];

        if ($newAttemptCount >= $notification->max_attempts) {
            $data['status'] = NotificationStatus::DISCARDED->value;
            if ($notification->batch_id) {
                $this->batches->incrementFailed($notification->batch_id);
            }
        } else {
            $data['status'] = NotificationStatus::FAILED->value;
            if ($retryAfterSeconds) {
                $data['next_attempt_at'] = now()->addSeconds($retryAfterSeconds);
            }
        }

        $this->notifications->update($notification->id, $data);
    }

    private function transition(
        Notification $notification,
        NotificationStatus $newStatus,
        array $data = []
    ): void {
        $statusHistory = $notification->status_history ?? [];
        $statusHistory[] = [
            'from' => $notification->status,
            'to' => $newStatus->value,
            'timestamp' => now()->toIso8601String(),
        ];

        $this->notifications->update($notification->id, array_merge($data, [
            'status' => $newStatus->value,
            'status_history' => $statusHistory,
        ]));
    }
}
