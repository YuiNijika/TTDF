<?php

declare(strict_types=1);

/**
 * TTDF Next theme entry point.
 *
 * Typecho loads this file in the global scope, so only the framework bootstrap
 * and Typecho-required callbacks live here.
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

if (PHP_VERSION_ID < 80200) {
    throw new RuntimeException('TTDF Next requires PHP 8.2 or newer.');
}

(static function (): void {
    $autoloader = __DIR__ . '/vendor/yuinijika/ttdf/core/Autoloader.php';
    if (!is_file($autoloader)) {
        throw new RuntimeException('TTDF core is missing. Restore vendor/yuinijika/ttdf/ from the release package.');
    }

    require_once $autoloader;
})();

\TTDF\Autoloader::register(__DIR__);
\TTDF\Framework::boot(__DIR__);

if (!function_exists('themeConfig')) {
    function themeConfig($form): void
    {
        if (is_object($form)) {
            \TTDF\Theme\Settings::config($form);
        }
    }
}

if (!function_exists('themeFields')) {
    function themeFields($layout): void
    {
        if (is_object($layout)) {
            \TTDF\Theme\Settings::fields($layout);
        }
    }
}
