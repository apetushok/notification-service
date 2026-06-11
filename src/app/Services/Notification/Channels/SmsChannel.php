<?php

namespace App\Services\Notification\Channels;

class SmsChannel implements NotificationChannelInterface
{
    public function __construct(
        private readonly array $providers
    ) {}

    public function getChannelName(): string
    {
        return 'sms';
    }

    public function getProviders(): array
    {
        return $this->providers;
    }

    public function getRetryDelay(int $attempt): int
    {
        return match($attempt) {
            1 => 30000,
            2 => 60000,
            3 => 120000,
            default => 300000,
        };
    }
}
