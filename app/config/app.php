<?php

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

return [
    'debug' => false,

    'rest' => [
        'enabled' => false,
        'v1_prefix' => 'ttdf/v1',
        'prefix' => [
            // Extra mounts: prefix => v1|v2, or ['prefix' => 'api', 'api' => 'v2'].
            // 'mobile' => 'v2',
        ],
        'cors' => [
            'allowed_origins' => [],
        ],
        'token_enabled' => false,
        'token_env' => 'TTDF_REST_TOKEN',
        'v2_enabled' => false,
        'v2_prefix' => 'ttdf/v2',
        'v2_token_ttl' => 0,
    ],

    'router' => [
        'enabled' => true,
        'pages_dir' => 'router',
    ],

    'vite' => [
        'enabled' => false,
        'dev' => false,
        'origin' => 'http://localhost:5173',
        'root' => 'vite',
        'entry' => 'src/main.ts',
        'out_dir' => 'assets/dist',
        'manifest' => '.vite/manifest.json',
    ],

];
