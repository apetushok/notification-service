<?php

return [
    'brokers' => env('KAFKA_BROKERS', 'kafka1:9092'),

    'producer' => [
        'compression' => 'lz4',
        'acks' => 'all',
        'max_retries' => 3,
        'retry_backoff_ms' => 100,
        'socket_timeout_ms' => 10000,
    ],

    'consumer' => [
        'group_id' => 'notification-service',
        'auto_commit' => true,
        'auto_commit_interval_ms' => 5000,
        'session_timeout_ms' => 30000,
    ],
];
