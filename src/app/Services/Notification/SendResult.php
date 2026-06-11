<?php

namespace App\Services\Notification;

readonly class SendResult
{
    public function __construct(
        public bool $success,
        public ?string $provider = null,
        public ?string $messageId = null,
        public ?array $response = null,
        public ?string $error = null,
        public bool $retryable = true,
    ) {}

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }

    public static function success(string $provider, string $messageId, array $response = []): self
    {
        return new self(true, $provider, $messageId, $response);
    }

    public static function failure(string $error, bool $retryable = true): self
    {
        return new self(false, error: $error, retryable: $retryable);
    }
}
