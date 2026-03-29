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
    'public_host' => env('GALLERY_PUBLIC_HOST', 'https://img.pulllog.net'),
];
