<?php

namespace App\Services\Notification;

use Illuminate\Support\Facades\Redis;

class RateLimiter
{
    private const REDIS_PREFIX = 'rate_limit:';

    public function attempt(string $channel, string $recipient): bool
    {
        // Глобальный лимит канала
        $channelLimit = config("notifications.channels.{$channel}.rate_limit.per_second", 10);

        if (!$this->checkLimit("channel:{$channel}", $channelLimit)) {
            return false;
        }

        // Лимит на получателя
        if (!$this->checkLimit("recipient:{$channel}:{$recipient}", 1)) {
            return false;
        }

        $this->increment($channel, $recipient);

        return true;
    }

    private function checkLimit(string $key, int $limit): bool
    {
        $current = Redis::get(self::REDIS_PREFIX . $key) ?? 0;
        return $current < $limit;
    }

    private function increment(string $channel, string $recipient): void
    {
        $pipeline = Redis::pipeline();

        $pipeline->incr(self::REDIS_PREFIX . "channel:{$channel}");
        $pipeline->expire(self::REDIS_PREFIX . "channel:{$channel}", 1);

        $pipeline->incr(self::REDIS_PREFIX . "recipient:{$channel}:{$recipient}");
        $pipeline->expire(self::REDIS_PREFIX . "recipient:{$channel}:{$recipient}", 1);

        $pipeline->exec();
    }
}
