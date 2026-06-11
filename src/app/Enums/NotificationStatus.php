<?php

namespace App\Enums;

enum NotificationStatus: string
{
    case QUEUED = 'queued';
    case SENDING = 'sending';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';
    case DISCARDED = 'discarded';

    public function isFinal(): bool
    {
        return in_array($this, [self::DELIVERED, self::DISCARDED]);
    }

    public function canTransitionTo(self $newStatus): bool
    {
        return match($this) {
            self::QUEUED => in_array($newStatus, [self::SENDING, self::DISCARDED]),
            self::SENDING => in_array($newStatus, [self::SENT, self::FAILED]),
            self::SENT => in_array($newStatus, [self::DELIVERED, self::FAILED]),
            self::FAILED => in_array($newStatus, [self::QUEUED, self::SENDING, self::DISCARDED]),
            self::DELIVERED, self::DISCARDED => false,
        };
    }

    public function label(): string
    {
        return match($this) {
            self::QUEUED => 'В очереди',
            self::SENDING => 'Отправляется',
            self::SENT => 'Отправлено',
            self::DELIVERED => 'Доставлено',
            self::FAILED => 'Ошибка',
            self::DISCARDED => 'Отброшено',
        };
    }
}
