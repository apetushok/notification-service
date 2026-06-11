<?php
// config/notifications.php

return [
    /*
    |--------------------------------------------------------------------------
    | Настройки приоритетов
    |--------------------------------------------------------------------------
    */
    'priorities' => [
        'transactional' => [
            'weight' => 100,
            'topic' => 'notifications.transactional',
            'max_workers' => 4,
            'max_attempts' => 5,
            'retry_delays' => [10, 30, 60, 300, 900],
        ],
        'high' => [
            'weight' => 75,
            'topic' => 'notifications.high',
            'max_workers' => 3,
            'max_attempts' => 4,
            'retry_delays' => [30, 120, 600, 1800],
        ],
        'normal' => [
            'weight' => 50,
            'topic' => 'notifications.normal',
            'max_workers' => 6,
            'max_attempts' => 3,
            'retry_delays' => [60, 300, 900],
        ],
        'low' => [
            'weight' => 25,
            'topic' => 'notifications.low',
            'max_workers' => 2,
            'max_attempts' => 2,
            'retry_delays' => [300, 900],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Настройки каналов
    |--------------------------------------------------------------------------
    */
    'channels' => [
        'sms' => [
            'max_attempts' => 3,
            'retry_delays' => [30, 60, 120],
            'default_provider' => 'twilio',
            'rate_limit' => [
                'per_second' => 10,
                'per_minute' => 500,
            ],
        ],
        'email' => [
            'max_attempts' => 4,
            'retry_delays' => [60, 300, 900, 3600],
            'default_provider' => 'sendgrid',
            'rate_limit' => [
                'per_second' => 50,
                'per_minute' => 2000,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Идемпотентность
    |--------------------------------------------------------------------------
    */
    'idempotency' => [
        'ttl' => 86400, // 24 часа
        'retry_after' => 1, // через сколько секунд можно повторить запрос
        'cleanup_after_hours' => 48,
    ],

    /*
    |--------------------------------------------------------------------------
    | Dead Letter Queue - ручное упровеление
    |--------------------------------------------------------------------------
    */
    'dlq' => [
        'topic' => 'notifications.dlq',
    ],
];
