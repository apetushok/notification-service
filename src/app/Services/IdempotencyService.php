<?php

namespace App\Services;

use App\Enums\IdempotencyKeysStatus;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IdempotencyService
{
    private const REDIS_PREFIX = 'idempotency:';
    private const REDIS_TTL = 3600; // 1 час в Redis
    private const DB_TTL = 86400; // 24 часа в БД

    public function isProcessed(string $key): bool
    {
        if ($this->isProcessedInRedis($key)) {
            return true;
        }

        if ($this->isProcessedInDatabase($key)) {
            $this->restoreInRedis($key);
            return true;
        }

        return false;
    }

    public function lock(string $key): bool
    {
        // Фаза 1: Redis lock (быстрая)
        if (!$this->acquireRedisLock($key)) {
            return false;
        }

        // Фаза 2: Database lock (надежная)
        if (!$this->acquireDatabaseLock($key)) {
            $this->releaseRedisLock($key);
            return false;
        }

        return true;
    }

    public function storeResult(string $key, array $result): void
    {
        $data = json_encode($result);

        Redis::setex(
            self::REDIS_PREFIX . $key,
            self::REDIS_TTL,
            $data
        );

        DB::table('idempotency_keys')
            ->where('key', $key)
            ->update([
                'status' => IdempotencyKeysStatus::COMPLETED->value,
                'response_body' => $data,
                'updated_at' => now(),
            ]);
    }

    public function getResult(string $key): ?array
    {
        $cached = Redis::get(self::REDIS_PREFIX . $key);
        if ($cached) {
            return json_decode($cached, true);
        }

        $record = DB::table('idempotency_keys')
            ->where('key', $key)
            ->where('status', IdempotencyKeysStatus::COMPLETED->value)
            ->first();

        if ($record && $record->response_body) {
            $result = json_decode($record->response_body, true);
            // Кэш
            Redis::setex(
                self::REDIS_PREFIX . $key,
                self::REDIS_TTL,
                $record->response_body
            );
            return $result;
        }

        return null;
    }

    public function cleanup(): array
    {
        $dbDeleted = DB::table('idempotency_keys')
            ->whereNot('status', IdempotencyKeysStatus::PROCESSING->value)
            ->where('expires_at', '<', now())
            ->delete();

        return [
            'db_deleted' => $dbDeleted,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    private function isProcessedInRedis(string $key): bool
    {
        $status = Redis::get(self::REDIS_PREFIX . $key . ':status');
        return $status === IdempotencyKeysStatus::COMPLETED->value;
    }

    private function isProcessedInDatabase(string $key): bool
    {
        return DB::table('idempotency_keys')
            ->where('key', $key)
            ->where('status', IdempotencyKeysStatus::COMPLETED->value)
            ->exists();
    }

    private function acquireRedisLock(string $key): bool
    {
        // Используем SET NX EX для атомарного лока
        $acquired = Redis::set(
            self::REDIS_PREFIX . $key . ':status',
            IdempotencyKeysStatus::PROCESSING->value,
            'EX',
            self::REDIS_TTL,
            'NX'
        );

        return $acquired !== null;
    }

    private function acquireDatabaseLock(string $key): bool
    {
        try {
            DB::table('idempotency_keys')->insert([
                'id' => (string) Str::uuid(),
                'key' => $key,
                'endpoint' => 'notifications/send',
                'http_method' => 'POST',
                'request_hash' => json_encode(['key' => $key]),
                'status' => 'processing',
                'expires_at' => now()->addSeconds(config('notifications.idempotency.ttl', 86400)),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Ключ уже существует
            return false;
        }
    }

    private function releaseRedisLock(string $key): void
    {
        // Удаляем только если статус processing (Lua скрипт для атомарности)
        $script = "
            if redis.call('get', KEYS[1]) == ARGV[1] then
                return redis.call('del', KEYS[1])
            else
                return 0
            end
        ";

        Redis::eval($script, 1, self::REDIS_PREFIX . $key . ':status');
    }

    private function restoreInRedis(string $key): void
    {
        $record = DB::table('idempotency_keys')
            ->where('key', $key)
            ->first();

        if ($record) {
            Redis::setex(
                self::REDIS_PREFIX . $key . ':status',
                self::REDIS_TTL,
                $record->status
            );

            if ($record->response_body) {
                Redis::setex(
                    self::REDIS_PREFIX . $key,
                    self::REDIS_TTL,
                    $record->response_body
                );
            }
        }
    }
}
