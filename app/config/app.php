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
        'pages_dir' => 'router',
    ],

    'assets' => [
        'cdn' => '',
    ],

    'settings' => [
        // 自定义设置表名；Typecho 会自动加上站点表前缀。
        'table' => 'ttdf',
        // 当前主题设置的 schema 版本，迁移器会升级到这个版本。
        'schema_version' => 2,
    ],
];
