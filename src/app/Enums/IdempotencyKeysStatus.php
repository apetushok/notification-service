<?php

namespace App\Enums;

enum IdempotencyKeysStatus: string
{
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
}
