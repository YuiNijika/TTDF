<?php

declare(strict_types=1);

namespace TTDF;

/**
 * Framework-owned PSR-4 autoloader.
 *
 * Production installs must work from a plain theme zip, so Composer is never
 * required at runtime.
 */
final class Autoloader
{
    private static string $themeRoot = '';

    private static bool $registered = false;

    public static function register(?string $themeRoot = null): void
    {
        if ($themeRoot !== null && $themeRoot !== '') {
            $resolved = realpath($themeRoot);
            self::$themeRoot = $resolved !== false
                ? str_replace('\\', '/', $resolved)
                : rtrim(str_replace('\\', '/', $themeRoot), '/');
        }

        if (self::$registered) {
            return;
        }

        spl_autoload_register([self::class, 'load']);
        self::$registered = true;
    }

    public static function themeRoot(): string
    {
        return self::$themeRoot !== '' ? self::$themeRoot : dirname(__DIR__, 4);
    }

    public static function load(string $class): void
    {
        $prefixes = [
            'TTDF\\App\\' => self::themeRoot() . '/app/',
            'TTDF\\' => __DIR__ . '/',
        ];

        foreach ($prefixes as $prefix => $directory) {
            if (!str_starts_with($class, $prefix)) {
                continue;
            }

            $relative = substr($class, strlen($prefix));
            if ($relative === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_\\\\]*$/', $relative) !== 1) {
                return;
            }

            $file = $directory . str_replace('\\', '/', $relative) . '.php';
            if (is_file($file)) {
                require $file;
                return;
            }
        }
    }
}
