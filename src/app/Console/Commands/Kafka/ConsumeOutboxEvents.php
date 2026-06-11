<?php

namespace App\Console\Commands\Kafka;

use Illuminate\Console\Command;
use App\DTO\OutboxMessageDTO;
use App\Services\Outbox\OutboxProcessor;
use App\Services\Debezium\DebeziumHealthChecker;
use App\Services\Kafka\KafkaConsumer;
use Illuminate\Support\Facades\Log;

class ConsumeOutboxEvents extends Command
{
    protected $signature = 'kafka:consume-outbox';
    protected $description = 'Consume outbox events from Debezium (INSERT only)';

    public function handle(
        OutboxProcessor $processor,
        DebeziumHealthChecker $health,
    ): void {
        $topic = 'notification_service.public.notification_batches';
        $groupId = 'outbox-consumer';

        $this->info("Starting outbox consumer for topic: {$topic}");

        $consumer = new KafkaConsumer([$topic], $groupId);

        $consumer->consume(function (array $data) use ($processor, $health) {
            $health->heartbeat();

            if (($data['status'] ?? '') !== 'pending') {
                Log::info('Skipping non-pending outbox message', [
                    'id' => $data['id'] ?? null,
                    'status' => $data['status'] ?? null,
                ]);
                return;
            }

            $data['metadata']['source'] = 'debezium';

            $dto = OutboxMessageDTO::fromArray($data);

            try {
                $processor->process($dto);
            } catch (\Exception $e) {
                Log::error('Failed to process Debezium event', [
                    'message_id' => $data['id'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }
}
