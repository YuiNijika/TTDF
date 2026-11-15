<?php

declare(strict_types=1);

namespace TTDF\Diagnostics;

use TTDF\Compat\Typecho;
use TTDF\Framework;
use TTDF\Hooks\Hooks;
use TTDF\Support\Escaper;

/**
 * Restores the v4 footer console banner as a framework-owned hook callback.
 */
final class RuntimeConsole
{
    private static ?float $startedAt = null;

    private static bool $printed = false;

    public static function start(?float $startedAt = null): void
    {
        self::$startedAt ??= $startedAt ?? microtime(true);
    }

    public static function register(): void
    {
        Hooks::addAction('theme.footer', [self::class, 'render'], 9999);
    }

    public static function render(mixed ...$args): void
    {
        if (self::$printed) {
            return;
        }

        self::$printed = true;

        $payload = [
            'version' => [
                'theme' => Typecho::themeVersion(),
                'typecho' => Typecho::version(),
                'framework' => Framework::VERSION,
            ],
            'loadtime' => self::elapsedLabel(),
        ];

        echo '<script type="text/javascript">' . PHP_EOL;
        echo 'window.frameWorkConfig = ' . Escaper::js($payload) . ';' . PHP_EOL;
        echo 'console.log("\n %c %s \n", "color: #fff; background: #34495e; padding:5px 0;", "TTDF v" + window.frameWorkConfig.version.framework);' . PHP_EOL;
        echo "console.log('页面加载耗时 ' + window.frameWorkConfig.loadtime);" . PHP_EOL;
        echo '</script>' . PHP_EOL;
    }

    public static function elapsedLabel(int $precision = 3): string
    {
        if (self::$startedAt === null) {
            return '0.000 ms';
        }

        $elapsed = max(0.0, microtime(true) - self::$startedAt);

        return $elapsed < 1
            ? number_format($elapsed * 1000, $precision) . ' ms'
            : number_format($elapsed, $precision) . ' s';
    }

    public static function reset(): void
    {
        self::$startedAt = null;
        self::$printed = false;
    }
}
