<?php

namespace App\Services\Notification\Providers\Sms;

use App\Models\Notification;
use App\Services\Notification\SendResult;
use App\Services\Notification\Providers\ProviderInterface;
use Illuminate\Support\Facades\Log;

class StubVonageProvider implements ProviderInterface
{
    public function getName(): string
    {
        return 'vonage';
    }

    public function send(Notification $notification): SendResult
    {
        $delay = random_int(80, 300);
        usleep($delay * 1000);

        $random = mt_rand() / mt_getrandmax();

        // 85% успех
        if ($random < 0.85) {
            $messageId = 'VON-' . strtoupper(substr(uniqid(), -8));

            Log::info('Vonage: SMS sent successfully', [
                'notification_id' => $notification->id,
                'message_id' => $messageId,
                'delay_ms' => $delay,
            ]);

            return SendResult::success('vonage', $messageId, [
                'message-id' => $messageId,
                'status' => '0',
                'remaining-balance' => number_format(mt_rand(100, 10000) / 100, 2),
            ]);
        }

        // Специфичные ошибки Vonage
        $errors = [
            [
                'code' => 1,
                'message' => 'Throttled - Too many requests',
                'retryable' => true,
            ],
            [
                'code' => 2,
                'message' => 'Missing params',
                'retryable' => false,
            ],
            [
                'code' => 3,
                'message' => 'Invalid credentials',
                'retryable' => false,
            ],
            [
                'code' => 4,
                'message' => 'Invalid number',
                'retryable' => false,
            ],
            [
                'code' => 5,
                'message' => 'Invalid from address',
                'retryable' => false,
            ],
            [
                'code' => 6,
                'message' => 'Unroutable request',
                'retryable' => false,
            ],
            [
                'code' => 7,
                'message' => 'Number barred',
                'retryable' => false,
            ],
            [
                'code' => 8,
                'message' => 'Partner account barred',
                'retryable' => false,
            ],
            [
                'code' => 10,
                'message' => 'Temporary system error',
                'retryable' => true,
            ],
            [
                'code' => 15,
                'message' => 'Invalid message body',
                'retryable' => false,
            ],
        ];

        $error = $errors[array_rand($errors)];

        Log::warning('Vonage: Failed to send SMS', [
            'notification_id' => $notification->id,
            'vonage_code' => $error['code'],
            'error' => $error['message'],
            'retryable' => $error['retryable'],
        ]);

        return SendResult::failure(
            "Vonage error {$error['code']}: {$error['message']}",
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
        return (mt_rand() / mt_getrandmax()) > 0.03;
    }
}
