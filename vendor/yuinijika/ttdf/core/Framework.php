<?php

declare(strict_types=1);

namespace TTDF;

use RuntimeException;
use TTDF\Ajax\Endpoint as AjaxEndpoint;
use TTDF\Compat\Typecho as TypechoCompat;
use TTDF\Config\Config;
use TTDF\Config\ConfigLoader;
use TTDF\Diagnostics\ErrorHandler;
use TTDF\Diagnostics\RuntimeConsole;
use TTDF\Hooks\Hooks;
use TTDF\Hooks\TypechoBridge;
use TTDF\Http\Rest\Router as RestRouter;
use TTDF\Http\Rest\V2\Auth as RestV2Auth;
use TTDF\Http\Rest\V2\Router as RestV2Router;
use TTDF\Routing\AutoRouter;
use TTDF\Theme\ArchiveLayout;
use TTDF\Theme\FrontendAssets;
use TTDF\Theme\Options;
use TTDF\Theme\Schema;
use TTDF\Theme\ThemeConfig;

final class Framework
{
    public const VERSION = '5.0.4-next';

    public const CORE_RELATIVE_PATH = 'vendor/yuinijika/ttdf/core';

    public const STATIC_RELATIVE_PATH = 'vendor/yuinijika/ttdf/static';

    private static bool $booted = false;

    private static string $root = '';

    public static function boot(string $root): void
    {
        if (self::$booted) {
            return;
        }

        RuntimeConsole::start();
        self::$root = $root;
        Config::load(ConfigLoader::load($root));
        require_once __DIR__ . '/TypechoFunctions.php';

        ErrorHandler::register(
            ThemeConfig::debug(),
            $root . '/logs/error.log'
        );

        TypechoBridge::register();
        ArchiveLayout::register();
        FrontendAssets::register();
        RuntimeConsole::register();
        AjaxEndpoint::registerRoutes();
        Hooks::doAction('framework.boot');

        AjaxEndpoint::handleIfRequested();
        RestRouter::syncRoutes();
        RestRouter::handleIfRequested($root);
        RestV2Router::syncRoutes();
        RestV2Router::handleIfRequested($root);
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
        RestRouter::unregisterRoutes();
        RestV2Router::unregisterRoutes();
        TypechoCompat::removeRoute(AjaxEndpoint::ROUTE_NAME);
        self::$booted = false;
        self::$root = '';
        AjaxEndpoint::reset();
        ArchiveLayout::reset();
        FrontendAssets::reset();
        RuntimeConsole::reset();
        Hooks::reset();
        TypechoBridge::reset();
        Options::reset();
        Schema::reset();
        ErrorHandler::reset();
        Config::reset();
        TypechoCompat::resetRoutes();
        RestV2Auth::reset();
    }
}
