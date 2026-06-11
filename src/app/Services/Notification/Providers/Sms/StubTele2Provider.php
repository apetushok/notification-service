<?php

namespace App\Services\Notification\Providers\Sms;

use App\Models\Notification;
use App\Services\Notification\SendResult;
use App\Services\Notification\Providers\ProviderInterface;
use Illuminate\Support\Facades\Log;

class StubTele2Provider implements ProviderInterface
{
    public function getName(): string
    {
        return 'tele2';
    }

    public function send(Notification $notification): SendResult
    {
        $delay = random_int(50, 250);
        usleep($delay * 1000);

        $random = mt_rand() / mt_getrandmax();

        // 87% успех
        if ($random < 0.87) {
            $messageId = 'T2-' . strtoupper(substr(uniqid(), -8));

            Log::info('Tele2: SMS sent successfully', [
                'notification_id' => $notification->id,
                'message_id' => $messageId,
                'delay_ms' => $delay,
            ]);

            return SendResult::success('tele2', $messageId, [
                'msgid' => $messageId,
                'status' => 'ok',
                'cost' => round(mt_rand(3, 40) / 100, 2),
            ]);
        }

        $errors = [
            [
                'code' => 'ERR-001',
                'message' => 'Service temporarily unavailable',
                'retryable' => true,
            ],
            [
                'code' => 'ERR-002',
                'message' => 'Invalid destination address',
                'retryable' => false,
            ],
            [
                'code' => 'ERR-003',
                'message' => 'Account expired',
                'retryable' => false,
            ],
            [
                'code' => 'ERR-004',
                'message' => 'Message too long',
                'retryable' => false,
            ],
            [
                'code' => 'ERR-005',
                'message' => 'Rate limit exceeded',
                'retryable' => true,
            ],
        ];

        $error = $errors[array_rand($errors)];

        Log::warning('Tele2: Failed to send SMS', [
            'notification_id' => $notification->id,
            'error_code' => $error['code'],
            'error' => $error['message'],
            'retryable' => $error['retryable'],
        ]);

        return SendResult::failure(
            "Tele2 {$error['code']}: {$error['message']}",
            retryable: $error['retryable']
        );
    }

    public function getMaxAttempts(): int
    {
        return 3;
    }

    public function getRetryDelay(int $attempt): int
    {
        return match($attempt) {
            1 => 1000,
            2 => 5000,
            3 => 15000,
            default => 30000,
        };
    }

    public function isAvailable(): bool
    {
        return (mt_rand() / mt_getrandmax()) > 0.04;
    }
}
