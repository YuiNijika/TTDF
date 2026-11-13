<?php

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) {
    define('__TYPECHO_ROOT_DIR__', dirname(__DIR__));
}

require_once dirname(__DIR__) . '/vendor/yuinijika/ttdf/core/Autoloader.php';
\TTDF\Autoloader::register(dirname(__DIR__));

require_once __DIR__ . '/Stubs/Typecho.php';
require_once __DIR__ . '/Unit/InMemoryOptionsStore.php';

date_default_timezone_set('Asia/Shanghai');
