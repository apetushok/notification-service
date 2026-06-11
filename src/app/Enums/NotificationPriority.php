<?php

namespace App\Enums;

enum NotificationPriority: string
{
    case TRANSACTIONAL = 'transactional';
    case HIGH = 'high';
    case NORMAL = 'normal';
    case LOW = 'low';

    public function label(): string
    {
        return match($this) {
            self::TRANSACTIONAL => 'Транзакционное',
            self::HIGH => 'Высокий',
            self::NORMAL => 'Обычный',
            self::LOW => 'Низкий',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
