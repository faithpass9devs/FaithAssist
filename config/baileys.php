<?php

return [
    'enabled' => (bool) env('BAILEYS_ENABLED', false),
    'base_url' => rtrim((string) env('BAILEYS_BASE_URL', 'http://127.0.0.1:3001'), '/'),
    'internal_token' => (string) env('BAILEYS_INTERNAL_TOKEN', ''),
    'session_name' => (string) env('BAILEYS_SESSION_NAME', 'faithassist'),
    'auth_dir' => (string) env('BAILEYS_AUTH_DIR', 'storage/app/baileys/auth'),
    'queue' => [
        'batch_size' => (int) env('BAILEYS_BATCH_SIZE', 50),
        'pause_between_messages' => (int) env('BAILEYS_PAUSE_BETWEEN_MESSAGES', 3),
        'pause_between_batches' => (int) env('BAILEYS_PAUSE_BETWEEN_BATCHES', 60),
        'daily_limit' => (int) env('BAILEYS_DAILY_LIMIT', 500),
    ],
    'retry' => [
        'max_retries' => (int) env('BAILEYS_MAX_RETRIES', 3),
        'delay' => (int) env('BAILEYS_RETRY_DELAY', 60),
    ],
    'legend' => (string) env(
        'BAILEYS_LEGEND',
        'Canal unicamente informativo. No respondas a este WhatsApp.'
    ),
];
