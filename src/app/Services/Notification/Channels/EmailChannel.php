<?php

namespace App\Services\Notification\Channels;

class EmailChannel implements NotificationChannelInterface
{
    public function __construct(
        private readonly array $providers
    ) {}

    public function getChannelName(): string
    {
        return 'email';
    }

    public function getProviders(): array
    {
        return $this->providers;
    }

    public function getRetryDelay(int $attempt): int
    {
        return match($attempt) {
            1 => 60000,
            2 => 300000,
            3 => 900000,
            default => 3600000,
        };
    }
}
