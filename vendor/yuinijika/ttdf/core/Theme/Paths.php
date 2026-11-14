<?php

declare(strict_types=1);

namespace TTDF\Theme;

use TTDF\Support\Path;

final class Paths
{
    public const APP_DIR = 'app';

    public const CONFIG_DIR = 'app/config';

    public const ROUTER_DIR = 'app/router';

    public const APP_CONFIG = 'app/config/app.php';

    public const SETUP = 'app/config/setup.php';

    public const THEME_FIELDS = 'app/config/theme.php';

    public static function app(string $root): string
    {
        return Path::join($root, self::APP_DIR);
    }

    public static function appConfig(string $root): string
    {
        return Path::join($root, self::APP_CONFIG);
    }

    public static function setup(string $root): string
    {
        return Path::join($root, self::SETUP);
    }

    public static function themeFields(string $root): string
    {
        return Path::join($root, self::THEME_FIELDS);
    }

    public static function router(string $root, string $relative = 'router'): string
    {
        $relative = trim(str_replace('\\', '/', $relative), '/');

        return $relative === ''
            ? Path::join($root, self::ROUTER_DIR)
            : Path::join(self::app($root), $relative);
    }
}
