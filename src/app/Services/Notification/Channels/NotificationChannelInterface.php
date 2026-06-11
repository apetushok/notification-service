<?php

namespace App\Services\Notification\Channels;

use App\Models\Notification;

interface NotificationChannelInterface
{
    public function getChannelName(): string;

    /**
     * @return ProviderInterface[] Список провайдеров в порядке приоритета
     */
    public function getProviders(): array;

    public function getRetryDelay(int $attempt): int;
}
