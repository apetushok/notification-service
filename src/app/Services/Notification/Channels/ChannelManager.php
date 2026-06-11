<?php

namespace App\Services\Notification\Channels;

use App\Enums\NotificationChannel;

class ChannelManager
{
    private array $channels = [];

    public function register(string $name, NotificationChannelInterface $channel): void
    {
        $this->channels[$name] = $channel;
    }

    public function get(string $name): NotificationChannelInterface
    {
        if (!isset($this->channels[$name])) {
            throw new \InvalidArgumentException("Channel not found: {$name}");
        }

        return $this->channels[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->channels[$name]);
    }
}
