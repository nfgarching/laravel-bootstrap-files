<?php

return [
    'channels' => [
        'files' => [
            'driver' => 'daily',
            'path' => storage_path('logs/files.log'),
            'level' => 'debug',
            'days' => 14,
            'replace_placeholders' => true,
        ],
    ],
];
