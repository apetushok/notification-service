<?php

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class IdempotencyConflictException extends \RuntimeException
{
    public function __construct(string $message = 'Request is already in progress')
    {
        parent::__construct($message, Response::HTTP_CONFLICT);
    }

    public function render(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'error' => 'idempotency_conflict',
            'message' => $this->getMessage(),
            'retry_after' => config('notifications.idempotency.retry_after', 1),
        ], Response::HTTP_CONFLICT);
    }
}
