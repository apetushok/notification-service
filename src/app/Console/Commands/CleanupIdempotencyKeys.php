<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\IdempotencyService;

class CleanupIdempotencyKeys extends Command
{
    protected $signature = 'idempotency:cleanup';
    protected $description = 'Cleanup expired idempotency keys';

    public function handle(IdempotencyService $idempotency): int
    {
        $result = $idempotency->cleanup();

        $this->info("Deleted {$result['db_deleted']} expired keys from database");

        return self::SUCCESS;
    }
}
