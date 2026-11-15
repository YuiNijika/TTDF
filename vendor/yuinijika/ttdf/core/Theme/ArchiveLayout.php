<?php

declare(strict_types=1);

namespace TTDF\Theme;

use TTDF\Hooks\Hooks;

/**
 * Bridges theme lifecycle hooks to Typecho's native Archive header/footer.
 */
final class ArchiveLayout
{
    private static bool $registered = false;

    private static bool $headerRendered = false;

    private static bool $footerRendered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        Hooks::addAction('theme.head', [self::class, 'renderHeader'], 1);
        Hooks::addAction('theme.footer', [self::class, 'renderFooter'], 1);
        self::$registered = true;
    }

    public static function renderHeader(mixed ...$args): void
    {
        if (self::$headerRendered) {
            return;
        }

        $callback = self::findCallback($args, 'header');
        if ($callback === null) {
            return;
        }

        self::$headerRendered = true;
        $callback();
    }

    public static function renderFooter(mixed ...$args): void
    {
        if (self::$footerRendered) {
            return;
        }

        $callback = self::findCallback($args, 'footer');
        if ($callback === null) {
            return;
        }

        self::$footerRendered = true;
        $callback();
    }

    public static function reset(): void
    {
        self::$registered = false;
        self::$headerRendered = false;
        self::$footerRendered = false;
    }

    /**
     * @param array<int|string, mixed> $args
     */
    private static function findCallback(array $args, string $method): ?callable
    {
        foreach ($args as $arg) {
            if (is_object($arg) && is_callable([$arg, $method])) {
                return [$arg, $method];
            }
        }

        if (!class_exists('Widget\\Archive')) {
            return null;
        }

        try {
            $archive = \Widget\Archive::alloc();
            return is_object($archive) && is_callable([$archive, $method]) ? [$archive, $method] : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
