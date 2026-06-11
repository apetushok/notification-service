<?php

namespace App\Services\Notification\Providers\Sms;

use App\Models\Notification;
use App\Services\Notification\SendResult;
use App\Services\Notification\Providers\ProviderInterface;
use Illuminate\Support\Facades\Log;

class StubTwilioProvider implements ProviderInterface
{
    public function getName(): string
    {
        return 'twilio';
    }

    public function send(Notification $notification): SendResult
    {
        // Twilio API обычно 100-400ms
        $delay = random_int(100, 400);
        usleep($delay * 1000);

        $random = mt_rand() / mt_getrandmax();

        // 82% успех
        if ($random < 0.82) {
            $messageId = 'SM' . strtoupper(substr(uniqid(), -8));

            Log::info('Twilio: SMS sent successfully', [
                'notification_id' => $notification->id,
                'message_id' => $messageId,
                'delay_ms' => $delay,
                'recipient' => $notification->recipient,
            ]);

            return SendResult::success('twilio', $messageId, [
                'sid' => $messageId,
                'status' => 'queued',
                'price' => round(mt_rand(5, 50) / 100, 2),
                'segments' => 1,
            ]);
        }

        // Специфичные ошибки Twilio
        $errors = [
            [
                'code' => 30001,
                'message' => 'Queue overflow',
                'retryable' => true,
            ],
            [
                'code' => 30002,
                'message' => 'Account suspended',
                'retryable' => false,
            ],
            [
                'code' => 30003,
                'message' => 'Unreachable destination handset',
                'retryable' => false,
            ],
            [
                'code' => 30004,
                'message' => 'Message blocked',
                'retryable' => false,
            ],
            [
                'code' => 30005,
                'message' => 'Unknown destination handset',
                'retryable' => false,
            ],
            [
                'code' => 30006,
                'message' => 'Landline or unreachable carrier',
                'retryable' => false,
            ],
            [
                'code' => 30007,
                'message' => 'Carrier violation',
                'retryable' => false,
            ],
            [
                'code' => 30008,
                'message' => 'Unknown error',
                'retryable' => true,
            ],
            [
                'code' => 30009,
                'message' => 'Missing segment',
                'retryable' => false,
            ],
            [
                'code' => 20429,
                'message' => 'Too many requests',
                'retryable' => true,
            ],
        ];

        $error = $errors[array_rand($errors)];

        Log::warning('Twilio: Failed to send SMS', [
            'notification_id' => $notification->id,
            'twilio_code' => $error['code'],
            'error' => $error['message'],
            'retryable' => $error['retryable'],
        ]);

        return SendResult::failure(
            "Twilio error {$error['code']}: {$error['message']}",
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
