<?php

namespace App\Services\Notification\Providers\Email;

use App\Models\Notification;
use App\Services\Notification\SendResult;
use App\Services\Notification\Providers\ProviderInterface;
use Illuminate\Support\Facades\Log;

class StubMailgunProvider implements ProviderInterface
{
    public function getName(): string
    {
        return 'mailgun';
    }

    public function send(Notification $notification): SendResult
    {
        // Mailgun обычно быстрее - 50-200ms
        $delay = random_int(50, 200);
        usleep($delay * 1000);

        $random = mt_rand() / mt_getrandmax();

        // 88% успех
        if ($random < 0.88) {
            $messageId = 'mailgun-' . uniqid() . '@stub.local';

            Log::info('Mailgun: Email sent successfully', [
                'notification_id' => $notification->id,
                'message_id' => $messageId,
                'delay_ms' => $delay,
            ]);

            return SendResult::success(
                'mailgun',
                $messageId,
                ['id' => $messageId, 'message' => 'Queued. Thank you.']
            );
        }

        // Специфичные ошибки Mailgun
        $errors = [
            [
                'code' => 400,
                'message' => 'Bad Request - Invalid parameters',
                'retryable' => false,
            ],
            [
                'code' => 401,
                'message' => 'Unauthorized - Invalid credentials',
                'retryable' => false,
            ],
            [
                'code' => 402,
                'message' => 'Request Failed - Domain not verified',
                'retryable' => false,
            ],
            [
                'code' => 429,
                'message' => 'Too Many Requests - Rate limit exceeded',
                'retryable' => true,
            ],
            [
                'code' => 500,
                'message' => 'Internal Server Error',
                'retryable' => true,
            ],
            [
                'code' => 502,
                'message' => 'Bad Gateway',
                'retryable' => true,
            ],
        ];

        $error = $errors[array_rand($errors)];

        Log::warning('Mailgun: Failed to send email', [
            'notification_id' => $notification->id,
            'http_code' => $error['code'],
            'error' => $error['message'],
            'retryable' => $error['retryable'],
        ]);

        return SendResult::failure(
            "Mailgun HTTP {$error['code']}: {$error['message']}",
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
        return (mt_rand() / mt_getrandmax()) > 0.02;
    }
}
