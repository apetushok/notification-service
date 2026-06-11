<?php

namespace App\Services\Notification\Providers;

use App\Models\Notification;
use App\Services\Notification\SendResult;

interface ProviderInterface
{
    public function getName(): string;

    public function send(Notification $notification): SendResult;

    public function getMaxAttempts(): int;

    public function getRetryDelay(int $attempt): int;

    public function isAvailable(): bool;
}
