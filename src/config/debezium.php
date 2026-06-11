<?php

return [
    'connect_url' => env('DEBEZIUM_CONNECT_URL', 'http://kafka-connect:8083'),
    'connector_name' => env('DEBEZIUM_CONNECTOR_NAME', 'notification-outbox-connector'),
    'max_event_age' => env('DEBEZIUM_MAX_EVENT_AGE', 120),
    'fallback_delay' => env('DEBEZIUM_FALLBACK_DELAY', 30),
];
