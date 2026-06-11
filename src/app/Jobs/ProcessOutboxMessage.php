<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\DTO\OutboxMessageDTO;
use App\Services\Outbox\OutboxProcessor;

class ProcessOutboxMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private array $data,
    ) {
        $this->onQueue('outbox');
    }

    public function handle(OutboxProcessor $processor): void
    {
        $this->data['metadata']['source'] = 'redis_queue';

        $dto = OutboxMessageDTO::fromArray($this->data);
        $processor->process($dto);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Outbox job failed', [
            'data' => $this->data,
            'error' => $exception->getMessage(),
        ]);
    }
}
