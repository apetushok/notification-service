<?php

namespace App\Actions;

use App\DTO\SendNotificationDTO;
use App\Repositories\OutboxRepository;
use App\Repositories\NotificationBatchRepository;
use App\Services\IdempotencyService;
use App\Exceptions\IdempotencyConflictException;

class SendBatchNotificationAction
{
    public function __construct(
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function execute(SendNotificationDTO $dto): array
    {
        // Проверка идемпотентности
        if ($dto->idempotencyKey) {
            if ($this->idempotency->isProcessed($dto->idempotencyKey)) {
                $result = $this->idempotency->getResult($dto->idempotencyKey);
                return [
                    'batch_id' => $result['batch_id'],
                    'idempotent' => true,
                ];
            }

            if (!$this->idempotency->lock($dto->idempotencyKey)) {
                throw new IdempotencyConflictException();
            }
        }

        // Сохраняем в outbox таблицу (одна запись)
        $batchId = (string) \Illuminate\Support\Str::uuid();

        $outbox = $this->outbox->create([
            'batch_id' => $batchId,
            'channel' => $dto->channel->value,
            'priority' => $dto->priority->value,
            'content' => $dto->content,
            'recipients' => $dto->recipients,
            'metadata' => $dto->metadata,
        ]);

        // Сохраняем результат идемпотентности
        if ($dto->idempotencyKey) {
            $this->idempotency->storeResult($dto->idempotencyKey, [
                'batch_id' => $batchId,
                'status' => 'pending',
            ]);
        }

        return [
            'batch_id' => $batchId,
            'idempotent' => false,
        ];
    }
}
