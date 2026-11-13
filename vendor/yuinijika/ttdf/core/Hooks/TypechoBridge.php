<?php

declare(strict_types=1);

namespace TTDF\Hooks;

final class TypechoBridge
{
    private static bool $registered = false;

    /**
     * @var array<string, array<string, string>>
     */
    private const HOOKS = [
        'Widget\\Archive' => [
            'header' => 'typecho.archive.header',
            'footer' => 'typecho.archive.footer',
            'beforeRender' => 'typecho.archive.before_render',
            'afterRender' => 'typecho.archive.after_render',
        ],
    ];

    public static function register(): void
    {
        if (self::$registered || !class_exists('Typecho\\Plugin')) {
            return;
        }

        foreach (self::HOOKS as $component => $hooks) {
            foreach ($hooks as $typechoHook => $ttdfHook) {
                self::registerHook($component, $typechoHook, $ttdfHook);
            }
        }

        self::$registered = true;
    }

    private static function registerHook(string $component, string $hook, string $ttdfHook): void
    {
        try {
            $plugin = \Typecho\Plugin::factory($component);
            $plugin->{$hook} = static function (...$args) use ($ttdfHook): void {
                Hooks::doAction($ttdfHook, ...$args);
            };
        } catch (\Throwable) {
            // A hook that is unavailable in the installed Typecho version is skipped.
        }
    }

    public static function reset(): void
    {
        self::$registered = false;
    }
}
