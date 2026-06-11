<?php

namespace App\Services\Outbox;

use App\DTO\OutboxMessageDTO;
use App\Repositories\OutboxRepository;
use App\Repositories\NotificationRepository;
use App\Services\Kafka\KafkaProducer;
use Illuminate\Support\Facades\Log;

class OutboxProcessor
{
    private const CHUNK_SIZE = 500;

    public function __construct(
        private readonly OutboxRepository $outbox,
        private readonly NotificationRepository $notifications,
        private readonly KafkaProducer $kafka,
    ) {}

    public function process(OutboxMessageDTO $message): void
    {
        Log::info('Processing outbox message', [
            'message_id' => $message->id,
            'batch_id' => $message->batchId,
            'source' => $message->metadata['source'] ?? 'unknown',
        ]);

        try {
            if ($message->status !== 'pending') {
                Log::info('Message already processed', ['message_id' => $message->id]);
                return;
            }

            $chunks = array_chunk($message->recipients, self::CHUNK_SIZE);

            foreach ($chunks as $chunkIndex => $chunk) {
                $messages = $this->prepareMessages($message, $chunk, $chunkIndex);
                $this->kafka->sendBatch($message->priority, $messages);
            }

            $this->outbox->markAsProcessed($message->id);

            Log::info('Outbox message processed successfully', [
                'message_id' => $message->id,
                'total_recipients' => count($message->recipients),
                'chunks' => count($chunks),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process outbox message', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);

            $this->outbox->markAsFailed($message->id, $e->getMessage());

            throw $e;
        }
    }

    private function prepareMessages(OutboxMessageDTO $message, array $chunk, int $chunkIndex): array
    {
        $messages = [];

        foreach ($chunk as $recipientIndex => $recipient) {
            $notification = $this->notifications->create([
                'channel' => $message->channel,
                'priority' => $message->priority,
                'recipient' => $recipient,
                'content' => $message->content,
                'metadata' => $message->metadata,
                'status' => 'queued',
                'max_attempts' => config("notifications.priorities.{$message->priority}.max_attempts", 3),
                'queued_at' => now(),
            ]);

            $messages[] = [
                'payload' => [
                    'notification_id' => $notification->id,
                    'batch_id' => $message->id,
                    'channel' => $message->channel,
                    'priority' => $message->priority,
                    'recipient' => $recipient,
                    'content' => $message->content,
                ],
                'key' => $notification->id,
            ];
        }

        return $messages;
    }
}
