<?php

return [
    'key' => env('BILLPLZ_API_KEY'),
    'version' => env('BILLPLZ_VERSION', 'v3'),
    'x-signature' => env('BILLPLZ_X_SIGNATURE'),
    'x_signature' => env('BILLPLZ_X_SIGNATURE'),
    'sandbox' => env('BILLPLZ_SANDBOX', false),
    'collection_id' => env('BILLPLZ_COLLECTION_ID'),
    'timeout_seconds' => (int) env('BILLPLZ_TIMEOUT_SECONDS', 10),
    'retry_times' => (int) env('BILLPLZ_RETRY_TIMES', 1),
    'retry_sleep_ms' => (int) env('BILLPLZ_RETRY_SLEEP_MS', 200),
    'user_agent' => env('BILLPLZ_USER_AGENT', 'billplz-laravel-client'),
];
