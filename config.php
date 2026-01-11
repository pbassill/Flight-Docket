<?php

declare(strict_types=1);

return [
    'app_name' => 'OTR Aviation Flight Docket',
    'timezone' => 'Europe/Madrid',

    'paths' => [
        'storage'   => __DIR__ . '/storage',
        'uploads'   => __DIR__ . '/storage/uploads',
        'dockets'   => __DIR__ . '/storage/dockets',
        'generated' => __DIR__ . '/storage/generated',
        'logo'      => __DIR__ . '/public/assets/otr-logo.png',
    ],

    'uploads' => [
        'max_bytes' => 30 * 1024 * 1024, // 30 MB per file
        'allowed_mime' => [
            'application/pdf',
        ],
        'allowed_ext' => [
            'pdf',
        ],
    ],
];
