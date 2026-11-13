<?php

declare(strict_types=1);

namespace TTDF;

use RuntimeException;
use TTDF\Config\Config;
use TTDF\Config\ConfigLoader;
use TTDF\Diagnostics\ErrorHandler;
use TTDF\Hooks\Hooks;
use TTDF\Hooks\TypechoBridge;
use TTDF\Http\Rest\Router as RestRouter;
use TTDF\Routing\AutoRouter;

final class Framework
{
    public const VERSION = '5.0.0-next';

    public const CORE_RELATIVE_PATH = 'vendor/yuinijika/ttdf/core';

    public const STATIC_RELATIVE_PATH = 'vendor/yuinijika/ttdf/static';

    private static bool $booted = false;

    private static string $root = '';

    public static function boot(string $root): void
    {
        if (self::$booted) {
            return;
        }

        self::$root = $root;
        Config::load(ConfigLoader::load($root));

        ErrorHandler::register(
            (bool) Config::get('debug', false),
            $root . '/logs/error.log'
        );

        TypechoBridge::register();
        Hooks::doAction('framework.boot');

        RestRouter::syncRoutes();
        RestRouter::handleIfRequested($root);
        AutoRouter::boot($root);

        self::$booted = true;
    }

    public static function root(): string
    {
        return self::$root !== '' ? self::$root : Autoloader::themeRoot();
    }

    public static function corePath(): string
    {
        return __DIR__;
    }

    public static function staticPath(): string
    {
        return self::root() . '/' . self::STATIC_RELATIVE_PATH;
    }

    public static function version(): string
    {
        return self::VERSION;
    }

    public static function reset(): void
    {
        self::$booted = false;
        self::$root = '';
    }
}
