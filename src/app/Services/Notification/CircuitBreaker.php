<?php

namespace App\Services\Notification;

use Illuminate\Support\Facades\Cache;

class CircuitBreaker
{
    private const CACHE_PREFIX = 'circuit_breaker:';
    private const DEFAULT_THRESHOLD = 5;
    private const DEFAULT_TIMEOUT = 300; // 5 минут

    public function isOpen(string $key): bool
    {
        $failures = Cache::get(self::CACHE_PREFIX . $key, 0);
        $threshold = config("notifications.circuit_breaker.{$key}.threshold", self::DEFAULT_THRESHOLD);

        return $failures >= $threshold;
    }

    public function recordFailure(string $key): void
    {
        $ttl = config("notifications.circuit_breaker.{$key}.timeout", self::DEFAULT_TIMEOUT);

        $failures = Cache::increment(self::CACHE_PREFIX . $key);
        Cache::expire(self::CACHE_PREFIX . $key, $ttl);
    }

    public function reset(string $key): void
    {
        Cache::forget(self::CACHE_PREFIX . $key);
    }

    public function getFailures(string $key): int
    {
        return Cache::get(self::CACHE_PREFIX . $key, 0);
    }
}
