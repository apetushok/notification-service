<?php

namespace App\DTO;

use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;
use App\Http\Requests\SendNotificationRequest;

readonly class SendNotificationDTO
{
    public function __construct(
        public NotificationChannel $channel,
        public string $content,
        public array $recipients,
        public NotificationPriority $priority = NotificationPriority::NORMAL,
        public ?array $metadata = null,
        public ?string $idempotencyKey = null,
    ) {}

    public static function fromRequest(SendNotificationRequest $request): self
    {
        return new self(
            channel: NotificationChannel::from($request->input('channel')),
            content: $request->input('content'),
            recipients: $request->input('recipients'),
            priority: NotificationPriority::from($request->input('priority', 'normal')),
            metadata: $request->input('metadata'),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        );
    }

    public function toArray(): array
    {
        return [
            'channel' => $this->channel->value,
            'content' => $this->content,
            'recipients' => $this->recipients,
            'priority' => $this->priority->value,
            'metadata' => $this->metadata,
            'idempotency_key' => $this->idempotencyKey,
        ];
    }
}
