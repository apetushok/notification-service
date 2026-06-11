<?php

namespace App\Services\Notification\Providers\Email;

use App\Models\Notification;
use App\Services\Notification\SendResult;
use App\Services\Notification\Providers\ProviderInterface;
use Illuminate\Support\Facades\Log;

class StubAmazonSESProvider implements ProviderInterface
{
    public function getName(): string
    {
        return 'amazon-ses';
    }

    public function send(Notification $notification): SendResult
    {
        // SES самый быстрый - 30-150ms
        $delay = random_int(30, 150);
        usleep($delay * 1000);

        $random = mt_rand() / mt_getrandmax();

        // 90% успех
        if ($random < 0.90) {
            $messageId = 'ses-' . uniqid() . '@stub.local';

            Log::info('Amazon SES: Email sent successfully', [
                'notification_id' => $notification->id,
                'message_id' => $messageId,
                'delay_ms' => $delay,
            ]);

            return SendResult::success(
                'amazon-ses',
                $messageId,
                ['MessageId' => $messageId, 'RequestId' => uniqid()]
            );
        }

        // Специфичные ошибки Amazon SES
        $errors = [
            [
                'code' => 'ThrottlingException',
                'message' => 'Rate exceeded',
                'retryable' => true,
            ],
            [
                'code' => 'ServiceUnavailable',
                'message' => 'Service is temporarily unavailable',
                'retryable' => true,
            ],
            [
                'code' => 'MessageRejected',
                'message' => 'Email address is not verified',
                'retryable' => false,
            ],
            [
                'code' => 'MailFromDomainNotVerified',
                'message' => 'Sending domain not verified',
                'retryable' => false,
            ],
            [
                'code' => 'ConfigurationSetDoesNotExist',
                'message' => 'Configuration set not found',
                'retryable' => false,
            ],
        ];

        $error = $errors[array_rand($errors)];

        Log::warning('Amazon SES: Failed to send email', [
            'notification_id' => $notification->id,
            'error_code' => $error['code'],
            'error' => $error['message'],
            'retryable' => $error['retryable'],
        ]);

        return SendResult::failure(
            "SES {$error['code']}: {$error['message']}",
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
        return (mt_rand() / mt_getrandmax()) > 0.01; // 99% доступность
    }
}
