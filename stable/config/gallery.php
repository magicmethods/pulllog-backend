<?php

return [
    'disk' => env('GALLERY_DISK', 'private'),
    'base_dir' => env('GALLERY_BASE_DIR', 'gallery'),
    'thumb' => [
        'small' => [
            'max' => 256,
            'quality' => 82,
        ],
        'large' => [
            'max' => 1024,
            'quality' => 82,
        ],
    ],
    'estimate_ratio' => [
        'small' => 0.08,
        'large' => 0.20,
    ],
    'allowed_mimes' => [
        'image/jpeg',
        'image/png',
        'image/webp',
    ],
    'signed_url_ttl' => (int) env('GALLERY_SIGNED_URL_TTL', 120),
    'upload_ticket_ttl' => (int) env('GALLERY_UPLOAD_TICKET_TTL', 60),
    'bootstrap_slow_log_ms' => (int) env('GALLERY_BOOTSTRAP_SLOW_LOG_MS', 2000),
    'bootstrap_hard_timeout_ms' => (int) env('GALLERY_BOOTSTRAP_HARD_TIMEOUT_MS', 8000),
    'public_host' => env('GALLERY_PUBLIC_HOST', 'https://img.pulllog.net'),
];
