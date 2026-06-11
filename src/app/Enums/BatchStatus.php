<?php

namespace App\Enums;

enum BatchStatus: string
{
    case PENDING = 'pending';
    case PROCESSED = 'processed';
    case FAILED = 'failed';

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Ожидает',
            self::PROCESSED => 'Завершен',
            self::FAILED => 'Ошибка',
        };
    }
}
