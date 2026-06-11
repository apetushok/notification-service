<?php

namespace App\Services\Notification;

use App\Models\Notification;
use App\Actions\UpdateNotificationStatusAction;
use App\Services\Notification\Channels\ChannelManager;
use App\Services\Notification\RateLimiter;
use App\Services\Notification\CircuitBreaker;
use Illuminate\Support\Facades\Log;

class NotificationOrchestrator
{
    public function __construct(
        private readonly ChannelManager $channels,
        private readonly UpdateNotificationStatusAction $updateStatus,
        private readonly RateLimiter $rateLimiter,
        private readonly CircuitBreaker $circuitBreaker,
    ) {}

    public function process(Notification $notification): void
    {
        $this->updateStatus->markAsSending($notification);

        try {
            if ($this->circuitBreaker->isOpen($notification->channel)) {
                $this->handleCircuitBreakerOpen($notification);
                return;
            }

            if (!$this->rateLimiter->attempt($notification->channel, $notification->recipient)) {
                $this->handleRateLimited($notification);
                return;
            }

            $channel = $this->channels->get($notification->channel);

            $result = $this->sendWithProviders($notification, $channel);

            if ($result->isSuccess()) {
                $this->updateStatus->markAsSent(
                    $notification,
                    $result->provider,
                    $result->messageId,
                    $result->response
                );

                $this->scheduleDeliveryCheck($notification, $result);
            } else {
                $this->handleFailedAttempt($notification, $channel);
            }

        } catch (\Exception $e) {
            Log::error('Orchestrator error', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);

            $this->handleFailedAttempt($notification, $channel ?? null);
        }
    }

    private function sendWithProviders(Notification $notification, $channel): SendResult
    {
        $providers = $channel->getProviders();
        $lastException = null;

        foreach ($providers as $provider) {
            if ($this->circuitBreaker->isOpen("{$notification->channel}:{$provider->getName()}")) {
                Log::info('Provider skipped (circuit breaker open)', [
                    'provider' => $provider->getName(),
                    'notification_id' => $notification->id,
                ]);
                continue;
            }

            for ($attempt = 1; $attempt <= $provider->getMaxAttempts(); $attempt++) {
                try {
                    $result = $provider->send($notification);

                    if ($result->isSuccess()) {
                        $this->circuitBreaker->reset("{$notification->channel}:{$provider->getName()}");

                        return $result;
                    }

                    $this->logAttempt($notification, $provider->getName(), $attempt, $result->error);

                    if (!$result->isRetryable()) {
                        break;
                    }

                    // Ждем перед следующей попыткой
                    if ($attempt < $provider->getMaxAttempts()) {
                        usleep($provider->getRetryDelay($attempt) * 1000);
                    }

                } catch (\Exception $e) {
                    $lastException = $e;

                    $this->logAttempt($notification, $provider->getName(), $attempt, $e->getMessage());

                    if ($attempt === $provider->getMaxAttempts()) {
                        $this->circuitBreaker->recordFailure(
                            "{$notification->channel}:{$provider->getName()}"
                        );
                        break;
                    }

                    usleep($provider->getRetryDelay($attempt) * 1000);
                }
            }
        }

        throw new AllProvidersFailedException(
            "All providers failed for notification {$notification->id}",
            $lastException
        );
    }

    private function handleFailedAttempt(Notification $notification, $channel = null): void
    {
        $retryDelay = $channel ? $channel->getRetryDelay($notification->attempt_count) : null;

        $this->updateStatus->markAsFailed(
            $notification,
            'Failed to send notification',
            $retryDelay
        );

        // Если исчерпаны все попытки - отправляем в DLQ
        if ($notification->attempt_count >= $notification->max_attempts) {
            $this->sendToDLQ($notification);
        }
    }

    private function handleCircuitBreakerOpen(Notification $notification): void
    {
        Log::warning('Circuit breaker open, retrying later', [
            'notification_id' => $notification->id,
            'channel' => $notification->channel,
        ]);

        $this->updateStatus->markAsFailed(
            $notification,
            'Circuit breaker is open',
            60 // Повторить через минуту
        );
    }

    private function handleRateLimited(Notification $notification): void
    {
        Log::warning('Rate limit reached, retrying later', [
            'notification_id' => $notification->id,
            'channel' => $notification->channel,
        ]);

        $this->updateStatus->markAsFailed(
            $notification,
            'Rate limit exceeded',
            5 // Повторить через 5 секунд
        );
    }

    private function sendToDLQ(Notification $notification): void
    {
        Log::error('Sending to DLQ', [
            'notification_id' => $notification->id,
            'attempts' => $notification->attempt_count,
        ]);

        \App\Models\DeadLetterQueue::create([
            'notification_id' => $notification->id,
            'topic' => config("notifications.priorities.{$notification->priority}.topic"),
            'payload' => json_encode($notification->toArray()),
            'error_type' => 'all_providers_failed',
            'error_message' => "Failed after {$notification->attempt_count} attempts",
            'status' => 'pending',
        ]);

        // Обрабатываем вручную, можно отправить в Kafka DLQ топик для обработки таких случаев
    }

    private function scheduleDeliveryCheck(Notification $notification, SendResult $result): void
    {
        // @todo: Implement async delivery check
    }

    private function logAttempt(Notification $notification, string $provider, int $attempt, ?string $error): void
    {
        \App\Models\NotificationAttempt::create([
            'notification_id' => $notification->id,
            'attempt_number' => $attempt,
            'provider' => $provider,
            'status' => $error ? 'failed' : 'success',
            'error_message' => $error,
            'attempted_at' => now(),
        ]);
    }
}
