<?php

namespace App\Services\Notification\Providers\Email;

use App\Models\Notification;
use App\Services\Notification\SendResult;
use App\Services\Notification\Providers\ProviderInterface;
use Illuminate\Support\Facades\Log;

class StubSendGridProvider implements ProviderInterface
{
    public function getName(): string
    {
        return 'sendgrid';
    }

    public function send(Notification $notification): SendResult
    {
        // SendGrid обычно отвечает 100-300ms
        $delay = random_int(80, 300);
        usleep($delay * 1000);

        $random = mt_rand() / mt_getrandmax();

        // 85% успех
        if ($random < 0.85) {
            $messageId = 'sendgrid-' . uniqid() . '@stub.local';

            Log::info('SendGrid: Email sent successfully', [
                'notification_id' => $notification->id,
                'message_id' => $messageId,
                'delay_ms' => $delay,
            ]);

            return SendResult::success(
                'sendgrid',
                $messageId,
                ['status' => 'delivered', 'timestamp' => now()->toIso8601String()]
            );
        }

        // Специфичные ошибки SendGrid
        $errors = [
            [
                'code' => 429,
                'message' => 'Too Many Requests',
                'retryable' => true,
            ],
            [
                'code' => 500,
                'message' => 'Internal Server Error',
                'retryable' => true,
            ],
            [
                'code' => 503,
                'message' => 'Service Unavailable',
                'retryable' => true,
            ],
            [
                'code' => 401,
                'message' => 'Unauthorized - Invalid API key',
                'retryable' => false,
            ],
            [
                'code' => 403,
                'message' => 'Forbidden - IP not whitelisted',
                'retryable' => false,
            ],
        ];

        $error = $errors[array_rand($errors)];

        Log::warning('SendGrid: Failed to send email', [
            'notification_id' => $notification->id,
            'http_code' => $error['code'],
            'error' => $error['message'],
            'retryable' => $error['retryable'],
        ]);

        return SendResult::failure(
            "SendGrid HTTP {$error['code']}: {$error['message']}",
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
            1 => 1000,   // 1 сек
            2 => 5000,   // 5 сек
            3 => 15000,  // 15 сек
            default => 30000,
        };
    }

    public function isAvailable(): bool
    {
        return (mt_rand() / mt_getrandmax()) > 0.02; // 98% доступность
    }
}
