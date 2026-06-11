<?php

namespace App\Console\Commands;

use App\DTO\OutboxMessageDTO;
use App\Services\Outbox\OutboxProcessor;
use Illuminate\Console\Command;
use App\Repositories\OutboxRepository;
use App\Services\Debezium\DebeziumHealthChecker;
use App\Jobs\ProcessOutboxMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessPendingOutbox extends Command
{
    protected $signature = 'outbox:process-pending';
    protected $description = 'Process pending outbox messages (fallback when Debezium is down)';

    private const FALLBACK_ACTIVATION_DELAY = 30;

    public function handle(
        OutboxRepository $repository,
        OutboxProcessor $processor,
        DebeziumHealthChecker $health,
    ): int {
        if ($health->isHealthy()) {
            $this->info('Debezium is healthy, skipping fallback');
            return self::SUCCESS;
        }

        $this->warn('Debezium is unhealthy, activating fallback processing');

        $downtime = $this->getDebeziumDowntime();

        if ($downtime < self::FALLBACK_ACTIVATION_DELAY) {
            $this->info("Debezium downtime: {$downtime}s, waiting for recovery...");
            return self::SUCCESS;
        }

        Log::warning('Starting fallback outbox processing', [
            'downtime' => $downtime,
        ]);

        $messages = $repository->getPending(100);

        if ($messages->isEmpty()) {
            $this->info('No pending messages to process');
            return self::SUCCESS;
        }

        $this->info("Processing {$messages->count()} messages via Redis fallback");

        foreach ($messages as $message) {
            try {
                $data = $message->toArray();
                $data['metadata']['source'] = 'redis_queue';
                $dto = OutboxMessageDTO::fromArray($data);
                $processor->process($dto);
            } catch (\Exception $e) {
                Log::error('Failed to process outbox message', [
                    'message_id' => $message->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return self::SUCCESS;
    }

    private function getDebeziumDowntime(): int
    {
        $lastEvent = Cache::get('kafka-connect:health:last_event');

        if (!$lastEvent) {
            return PHP_INT_MAX; // Никогда не было событий
        }

        return time() - $lastEvent;
    }
}
