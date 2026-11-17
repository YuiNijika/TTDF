<?php

declare(strict_types=1);

namespace TTDF;

use InvalidArgumentException;

/**
 * Framework-owned PSR-4 autoloader.
 *
 * Production installs must work from a plain theme zip, so Composer is never
 * required at runtime. Theme-owned classes opt in with their own root
 * namespace; directories below `app/` are resolved in lowercase.
 */
final class Autoloader
{
    private static string $themeRoot = '';

    private static string $themeNamespace = '';

    private static bool $registered = false;

    public static function register(?string $themeRoot = null, ?string $themeNamespace = null): void
    {
        if ($themeRoot !== null && $themeRoot !== '') {
            $resolved = realpath($themeRoot);
            self::$themeRoot = $resolved !== false
                ? str_replace('\\', '/', $resolved)
                : rtrim(str_replace('\\', '/', $themeRoot), '/');
        }

        if ($themeNamespace !== null) {
            $themeNamespace = trim($themeNamespace, " \t\n\r\0\x0B\\");
            if (
                $themeNamespace === ''
                || preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', $themeNamespace) !== 1
            ) {
                throw new InvalidArgumentException('Invalid theme namespace');
            }

            if ($themeNamespace === 'TTDF' || str_starts_with($themeNamespace, 'TTDF\\')) {
                throw new InvalidArgumentException('Theme namespace must not shadow TTDF');
            }

            self::$themeNamespace = $themeNamespace;
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

    public static function themeNamespace(): string
    {
        return self::$themeNamespace;
    }

    public static function load(string $class): void
    {
        if (self::$themeNamespace !== '') {
            $themePrefix = self::$themeNamespace . '\\';
            if (str_starts_with($class, $themePrefix)) {
                $relative = substr($class, strlen($themePrefix));
                $file = self::themeFile($relative);
                if ($file !== null && is_file($file)) {
                    require $file;
                    return;
                }
            }
        }

        $prefix = 'TTDF\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        if ($relative === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_\\\\]*$/', $relative) !== 1) {
            return;
        }

        $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }

    /**
     * Theme directories are lowercase; the class file keeps its PSR-4 name.
     */
    private static function themeFile(string $relative): ?string
    {
        if ($relative === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_\\\\]*$/', $relative) !== 1) {
            return null;
        }

        $segments = explode('\\', $relative);
        $class = array_pop($segments);
        if ($class === '') {
            return null;
        }

        $directory = array_map(
            static fn (string $segment): string => strtolower($segment),
            $segments
        );
        $prefix = $directory === [] ? '' : implode('/', $directory) . '/';

        return self::themeRoot() . '/app/' . $prefix . $class . '.php';
    }
}
