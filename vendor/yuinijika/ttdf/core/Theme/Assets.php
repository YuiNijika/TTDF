<?php

declare(strict_types=1);

namespace TTDF\Theme;

use TTDF\Compat\Typecho;
use TTDF\Framework;
use TTDF\Support\Escaper;
use TTDF\Support\Path;

final class Assets
{
    /**
     * Default directory for `url()` so `Assets::url('css/app.css')` resolves to
     * `assets/css/app.css` without repeating the folder name.
     */
    public const DEFAULT_BASE = 'assets';

    /**
     * Build a theme asset URL.
     *
     * The second argument is the base directory. A path that already starts
     * with it is left untouched, so both `Assets::url('css/app.css')` and the
     * legacy `Assets::url('assets/css/app.css')` keep working.
     */
    public static function url(string $path, string $base = self::DEFAULT_BASE): string
    {
        return self::buildUrl(self::applyBase($path, $base), self::themeVersion());
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

    /**
     * Theme assets follow the theme's own `@version`. Framework statics use
     * `Framework::VERSION` so upgrading either layer invalidates its own
     * browser cache without forcing the other layer to change.
     */
    private static function themeVersion(): string
    {
        return Typecho::themeVersion();
    }

    private static function applyBase(string $path, string $base): string
    {
        $path = ltrim(str_replace('\\', '/', trim($path)), '/');
        $base = self::normalizeBase($base);

        if ($base === '' || $path === '') {
            return $path;
        }

        if ($path === $base || str_starts_with($path, $base . '/')) {
            return $path;
        }

        return $base . '/' . $path;
    }

    /**
     * Only relative directory segments are accepted; anything else is ignored
     * so a bad base cannot escape the theme directory.
     */
    private static function normalizeBase(string $base): string
    {
        $base = trim(str_replace('\\', '/', $base), '/');
        if ($base === '' || str_contains($base, '..')) {
            return '';
        }

        return preg_match('#^[A-Za-z0-9_.-]+(?:/[A-Za-z0-9_.-]+)*$#', $base) === 1 ? $base : '';
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
        $query = '';
        if ($version !== '') {
            $separator = str_contains($path, '?') ? '&' : '?';
            $query = $separator . 'v=' . rawurlencode($version);
        }

        $url = $base . '/' . $path . $query;
        if ($fragment !== '') {
            $url .= '#' . $fragment;
        }

        return Escaper::url($url);
    }

    /**
     * @param list<string> $paths
     */
    public static function printStyles(array $paths, string $base = self::DEFAULT_BASE): void
    {
        foreach ($paths as $path) {
            echo '<link rel="stylesheet" href="' . self::url($path, $base) . '">' . PHP_EOL;
        }
    }

    /**
     * @param list<string> $paths
     */
    public static function printScripts(array $paths, string $base = self::DEFAULT_BASE): void
    {
        foreach ($paths as $path) {
            echo '<script src="' . self::url($path, $base) . '"></script>' . PHP_EOL;
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
