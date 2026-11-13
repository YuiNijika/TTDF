<?php

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

return [
    'version' => '5.0.0-next',
    'debug' => false,

    'rest' => [
        'enabled' => false,
        'prefix' => 'ttdf/v1',
        'cors' => [
            'allowed_origins' => [],
        ],
        'token_env' => 'TTDF_REST_TOKEN',
    ],

    'router' => [
        'enabled' => true,
        'pages_dir' => 'app/pages',
    ],

    'assets' => [
        'cdn' => '',
    ],

    'settings' => [
        'table' => 'ttdf',
        'schema_version' => 2,
    ],
];
