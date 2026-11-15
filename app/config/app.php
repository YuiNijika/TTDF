<?php

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

return [
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
        'pages_dir' => 'router',
    ],

];
