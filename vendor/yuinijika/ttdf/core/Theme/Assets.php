<?php

declare(strict_types=1);

namespace TTDF\Theme;

use TTDF\Compat\Typecho;
use TTDF\Framework;
use TTDF\Support\Escaper;
use TTDF\Support\Path;

final class Assets
{
    public static function url(string $path): string
    {
        return self::buildUrl($path, self::themeVersion());
    }

    public static function staticUrl(string $path): string
    {
        $relative = Path::relative(Framework::root(), Framework::staticPath());
        if ($relative === '') {
            $relative = Framework::STATIC_RELATIVE_PATH;
        }

        return self::buildUrl(
            $relative . '/' . ltrim(str_replace('\\', '/', $path), '/'),
            Framework::VERSION
        );
    }

    private static function themeVersion(): string
    {
        $version = Typecho::themeVersion();

        return $version !== '' ? $version : Framework::VERSION;
    }

    private static function buildUrl(string $path, string $version): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $cdn = ThemeConfig::assetsCdn();
        $base = $cdn !== '' && preg_match('#^https?://#i', $cdn) === 1
            ? rtrim($cdn, '/')
            : rtrim(Typecho::themeUrl(), '/');
        $fragment = '';
        if (str_contains($path, '#')) {
            [$path, $fragment] = explode('#', $path, 2);
        }
        $separator = str_contains($path, '?') ? '&' : '?';
        $url = $base . '/' . $path . $separator . 'v=' . rawurlencode($version);
        if ($fragment !== '') {
            $url .= '#' . $fragment;
        }

        return Escaper::url($url);
    }

    /**
     * @param list<string> $paths
     */
    public static function printStyles(array $paths): void
    {
        foreach ($paths as $path) {
            echo '<link rel="stylesheet" href="' . self::url($path) . '">' . PHP_EOL;
        }
    }

    /**
     * @param list<string> $paths
     */
    public static function printScripts(array $paths): void
    {
        foreach ($paths as $path) {
            echo '<script src="' . self::url($path) . '"></script>' . PHP_EOL;
        }
    }

    /**
     * @param list<string> $paths
     */
    public static function printStaticStyles(array $paths): void
    {
        foreach ($paths as $path) {
            echo '<link rel="stylesheet" href="' . self::staticUrl($path) . '">' . PHP_EOL;
        }
    }

    /**
     * @param list<string> $paths
     */
    public static function printStaticScripts(array $paths): void
    {
        foreach ($paths as $path) {
            echo '<script src="' . self::staticUrl($path) . '"></script>' . PHP_EOL;
        }
    }
}
