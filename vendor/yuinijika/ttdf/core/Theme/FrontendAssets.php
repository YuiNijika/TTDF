<?php

declare(strict_types=1);

namespace TTDF\Theme;

use TTDF\Hooks\Hooks;

/**
 * Registers optional frontend bundle assets on the theme head hook.
 */
final class FrontendAssets
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        Hooks::addAction('theme.head', [self::class, 'render'], 0);
        self::$registered = true;
    }

    public static function render(mixed ...$args): void
    {
        echo self::head();
    }

    public static function head(): string
    {
        return Vite::head() . Webpack::head();
    }

    public static function reset(): void
    {
        self::$registered = false;
    }
}
