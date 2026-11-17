<?php

declare(strict_types=1);

namespace TTDF\Theme;

use TTDF\Diagnostics\Logger;
use TTDF\Framework;
use TTDF\Support\Path;

/**
 * Shared manifest and build-path helpers for the optional frontend bundlers.
 *
 * @internal
 */
final class BuildManifest
{
    private const MAX_BYTES = 2_097_152;

    /**
     * @return array<string, mixed>
     */
    public static function read(
        string $outDir,
        string $manifestFile,
        string $fallback = '',
        string $label = 'Bundler'
    ): array
    {
        $themeRoot = Framework::root();
        if ($themeRoot === '') {
            return [];
        }

        $root = realpath($themeRoot);
        if ($root === false) {
            return [];
        }

        $relative = Path::join($outDir, $manifestFile);
        $file = Path::join($themeRoot, $relative);

        if (!is_file($file) && $fallback !== '') {
            $relative = Path::join($outDir, $fallback);
            $file = Path::join($themeRoot, $relative);
        }

        $real = is_file($file) ? realpath($file) : false;
        if ($real === false || !Path::contains($themeRoot, $real)) {
            self::report($label, 'manifest not found at ' . $relative);
            return [];
        }

        $size = @filesize($real);
        if ($size === false || $size > self::MAX_BYTES) {
            self::report($label, 'manifest is empty or larger than 2 MiB');
            return [];
        }

        $json = @file_get_contents($real);
        if ($json === false) {
            self::report($label, 'manifest could not be read');
            return [];
        }

        try {
            $manifest = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            self::report($label, 'manifest is not valid JSON');
            return [];
        }

        if (!is_array($manifest)) {
            self::report($label, 'manifest must decode to an object');
            return [];
        }

        return $manifest;
    }

    public static function assetUrl(string $outDir, string $file): ?string
    {
        if (!self::isSafePath($file)) {
            return null;
        }

        return Assets::url(Path::join($outDir, self::normalize($file)), '');
    }

    public static function isSafePath(string $path): bool
    {
        $path = self::normalize($path);
        if ($path === '' || str_starts_with($path, '/') || preg_match('#^[A-Za-z]:#', $path) === 1) {
            return false;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }

            if (preg_match('/^[A-Za-z0-9_.-]+$/', $segment) !== 1) {
                return false;
            }
        }

        return true;
    }

    public static function normalize(string $path): string
    {
        return trim(str_replace('\\', '/', trim($path)), '/');
    }

    public static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return $value != 0;
        }

        return is_string($value)
            && in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }

    public static function report(string $label, string $message): void
    {
        if (!ThemeConfig::debug()) {
            return;
        }

        $root = Framework::root();
        if ($root === '') {
            return;
        }

        try {
            (new Logger($root . '/logs/error.log'))
                ->write('warning', $label . ': ' . $message);
        } catch (\Throwable) {
            // Diagnostics must never break the page.
        }
    }
}
