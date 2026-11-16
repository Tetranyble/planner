<?php

return [
    'connection' => env('UNIPOTENCE_DB_CONNECTION') ?: env('DB_CONNECTION'),
    'lease_seconds' => (int) env('UNIPOTENCE_LEASE_SECONDS', 300),
    'max_request_seconds' => (int) env('UNIPOTENCE_MAX_REQUEST_SECONDS', 120),
    'retention_days' => (int) env('UNIPOTENCE_RETENTION_DAYS', 30),
    'indeterminate_retention_days' => (int) env('UNIPOTENCE_INDETERMINATE_RETENTION_DAYS', 90),
    'max_key_length' => (int) env('UNIPOTENCE_MAX_KEY_LENGTH', 255),
    'max_response_bytes' => (int) env('UNIPOTENCE_MAX_RESPONSE_BYTES', 1_048_576),
    'replay_headers' => [
        'Idempotency-Key',
        'Content-Type',
        'Location',
        'Deprecation',
        'Sunset',
    ],
];
