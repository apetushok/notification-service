<?php

namespace App\DTO;

readonly class OutboxMessageDTO
{
    public function __construct(
        public string $id,
        public string $batchId,
        public string $channel,
        public string $priority,
        public string $content,
        public array $recipients,
        public ?array $metadata,
        public string $status,
        public int $attemptCount,
        public ?string $errorMessage,
        public ?string $processedAt,
        public string $createdAt,
        public string $updatedAt,
    ) {}

    public static function fromArray(array $data): self
    {
        $recipients = $data['recipients'] ?? [];
        if (is_string($recipients)) {
            $recipients = json_decode($recipients, true) ?? [];
        }

        $metadata = $data['metadata'] ?? [];
        if (is_string($metadata)) {
            $metadata = json_decode($metadata, true) ?? [];
        }
        if (!is_array($metadata)) {
            $metadata = [];
        }

        return new self(
            id: $data['id'],
            batchId: $data['batch_number'] ?? $data['batch_id'] ?? $data['id'],
            channel: $data['channel'],
            priority: $data['priority'],
            content: $data['content'],
            recipients: $recipients,
            metadata: $metadata,
            status: $data['status'],
            attemptCount: $data['attempt_count'] ?? 0,
            errorMessage: $data['error_message'] ?? null,
            processedAt: $data['processed_at'] ?? null,
            createdAt: $data['created_at'],
            updatedAt: $data['updated_at'],
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'batch_id' => $this->batchId,
            'channel' => $this->channel,
            'priority' => $this->priority,
            'content' => $this->content,
            'recipients' => $this->recipients,
            'metadata' => $this->metadata,
            'status' => $this->status,
            'attempt_count' => $this->attemptCount,
            'error_message' => $this->errorMessage,
            'processed_at' => $this->processedAt,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
