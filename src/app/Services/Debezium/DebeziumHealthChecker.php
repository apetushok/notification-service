<?php

namespace App\Services\Debezium;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DebeziumHealthChecker
{
    private const HEALTH_CACHE_KEY = 'debezium:health:status';
    private const LAST_EVENT_KEY = 'debezium:health:last_event';
    private const HEALTHY = 'healthy';
    private const UNHEALTHY = 'unhealthy';
    private const MAX_EVENT_AGE = 120;

    public function isHealthy(): bool
    {
        $cachedStatus = Cache::get(self::HEALTH_CACHE_KEY);

        if ($cachedStatus === self::HEALTHY) {
            return true;
        }

        $lastEvent = Cache::get(self::LAST_EVENT_KEY);

        if ($lastEvent && (time() - $lastEvent) < self::MAX_EVENT_AGE) {
            $this->markAsHealthy();
            return true;
        }

        if ($this->checkConnectorStatus()) {
            $this->markAsHealthy();
            return true;
        }

        return false;
    }

    public function heartbeat(): void
    {
        Cache::put(self::HEALTH_CACHE_KEY, self::HEALTHY, 300);
        Cache::put(self::LAST_EVENT_KEY, time(), 300);
    }

    public function markAsUnhealthy(): void
    {
        Cache::put(self::HEALTH_CACHE_KEY, self::UNHEALTHY, 300);
        Log::warning('Debezium marked as unhealthy');
    }

    private function markAsHealthy(): void
    {
        Cache::put(self::HEALTH_CACHE_KEY, self::HEALTHY, 300);
    }

    private function checkConnectorStatus(): bool
    {
        try {
            $url = config('debezium.connect_url') . '/connectors/notification-outbox-connector/status';

            $response = Http::timeout(5)->get($url);

            if ($response->successful()) {
                $status = $response->json();
                return ($status['connector']['state'] ?? '') === 'RUNNING';
            }
        } catch (\Exception $e) {
            Log::error('Failed to check Debezium connector status', [
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }
}
