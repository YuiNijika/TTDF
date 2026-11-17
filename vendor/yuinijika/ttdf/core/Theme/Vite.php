<?php

declare(strict_types=1);

namespace TTDF\Theme;

use TTDF\Config\Config;
use TTDF\Config\ConfigLoader;
use TTDF\Diagnostics\Logger;
use TTDF\Framework;
use TTDF\Support\Escaper;
use TTDF\Support\Path;

/**
 * Optional Vite bridge.
 *
 * The framework only injects the dev client or the built manifest assets. It
 * does not generate Vue/React/Svelte entry files, so a theme can use any
 * frontend stack as long as the build writes a standard Vite manifest.
 */
final class Vite
{
    private const DEFAULT_ORIGIN = 'http://localhost:5173';

    private const DEFAULT_ROOT = 'vite';

    private const DEFAULT_ENTRY = 'src/main.ts';

    private const DEFAULT_OUT_DIR = 'assets/dist';

    private const DEFAULT_MANIFEST = '.vite/manifest.json';

    private const MAX_MANIFEST_BYTES = 2_097_152;

    public static function enabled(): bool
    {
        return self::toBool(Config::get('vite.enabled', false));
    }

    public static function dev(): bool
    {
        return self::enabled() && self::toBool(Config::get('vite.dev', false));
    }

    public static function origin(): ?string
    {
        $origin = Config::get('vite.origin', self::DEFAULT_ORIGIN);

        return is_string($origin) ? ConfigLoader::normalizeOrigin($origin) : null;
    }

    public static function root(): string
    {
        $root = Config::get('vite.root', self::DEFAULT_ROOT);

        return is_string($root) && self::isSafeRelativePath($root)
            ? self::normalizeRelative($root)
            : self::DEFAULT_ROOT;
    }

    public static function entry(): string
    {
        $entry = Config::get('vite.entry', self::DEFAULT_ENTRY);

        return is_string($entry) && self::isSafeRelativePath($entry)
            ? self::normalizeRelative($entry)
            : self::DEFAULT_ENTRY;
    }

    /**
     * Tags for `<head>`. Development emits the Vite client and entry module;
     * production emits styles, modulepreload links and the entry script.
     */
    public static function head(): string
    {
        if (!self::enabled()) {
            return '';
        }

        return self::dev() ? self::devHead() : self::buildHead();
    }

    public static function printHead(): void
    {
        echo self::head();
    }

    /**
     * @return array<string, mixed>
     */
    public static function manifest(): array
    {
        $themeRoot = Framework::root();
        if ($themeRoot === '') {
            return [];
        }

        $root = realpath($themeRoot);
        if ($root === false) {
            return [];
        }

        $relative = Path::join(self::outDir(), self::manifestFile());
        $file = Path::join($themeRoot, $relative);

        if (!is_file($file) && self::manifestFile() === self::DEFAULT_MANIFEST) {
            // Vite 4 wrote manifest.json at the output root.
            $relative = Path::join(self::outDir(), 'manifest.json');
            $file = Path::join($themeRoot, $relative);
        }

        $real = is_file($file) ? realpath($file) : false;
        if ($real === false || !Path::contains($themeRoot, $real)) {
            self::report('manifest not found at ' . $relative);
            return [];
        }

        $size = @filesize($real);
        if ($size === false || $size > self::MAX_MANIFEST_BYTES) {
            self::report('manifest is empty or larger than 2 MiB');
            return [];
        }

        $json = @file_get_contents($real);
        if ($json === false) {
            self::report('manifest could not be read');
            return [];
        }

        try {
            $manifest = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            self::report('manifest is not valid JSON');
            return [];
        }

        if (!is_array($manifest)) {
            self::report('manifest must decode to an object');
            return [];
        }

        return $manifest;
    }

    private static function devHead(): string
    {
        $origin = self::origin();
        if ($origin === null) {
            self::report('invalid vite.origin');
            return '';
        }

        $client = $origin . '/@vite/client';
        $entry = $origin . '/' . self::entry();

        return '<script type="module" src="' . Escaper::url($client) . '"></script>' . PHP_EOL
            . '<script type="module" src="' . Escaper::url($entry) . '"></script>' . PHP_EOL;
    }

    private static function buildHead(): string
    {
        $manifest = self::manifest();
        if ($manifest === []) {
            return '';
        }

        $entryKey = self::findEntryKey($manifest);
        if ($entryKey === null) {
            self::report('entry "' . self::entry() . '" is missing from the manifest');
            return '';
        }

        $seen = [];
        $styles = [];
        $preloads = [];
        $collect = function (string $key, bool $isEntry) use (
            &$collect,
            &$seen,
            &$styles,
            &$preloads,
            $manifest
        ): void {
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;

            $chunk = $manifest[$key] ?? null;
            if (!is_array($chunk)) {
                return;
            }

            foreach (self::stringList($chunk['imports'] ?? null) as $import) {
                $collect($import, false);
            }

            // Imported styles come first so the entry stylesheet can override
            // shared component styles in the cascade.
            foreach (self::stringList($chunk['css'] ?? null) as $css) {
                if (self::isSafeRelativePath($css)) {
                    $styles[] = self::normalizeRelative($css);
                }
            }

            if (!$isEntry && isset($chunk['file']) && is_string($chunk['file'])) {
                if (self::isSafeRelativePath($chunk['file'])) {
                    $preloads[] = self::normalizeRelative($chunk['file']);
                }
            }
        };
        $collect($entryKey, true);

        $entryChunk = $manifest[$entryKey];
        $entryFile = isset($entryChunk['file']) && is_string($entryChunk['file'])
            ? self::normalizeRelative($entryChunk['file'])
            : '';
        if ($entryFile === '' || !self::isSafeRelativePath($entryFile)) {
            self::report('entry chunk has no valid file');
            return '';
        }

        $html = '';
        foreach (array_values(array_unique($styles)) as $style) {
            $url = self::assetUrl($style);
            if ($url !== null) {
                $html .= '<link rel="stylesheet" href="' . $url . '">' . PHP_EOL;
            }
        }

        foreach (array_values(array_unique($preloads)) as $preload) {
            if ($preload === $entryFile) {
                continue;
            }
            $url = self::assetUrl($preload);
            if ($url !== null) {
                $html .= '<link rel="modulepreload" href="' . $url . '" crossorigin>' . PHP_EOL;
            }
        }

        $entryUrl = self::assetUrl($entryFile);
        if ($entryUrl === null) {
            self::report('entry URL is invalid');
            return '';
        }

        return $html
            . '<script type="module" crossorigin src="' . $entryUrl . '"></script>'
            . PHP_EOL;
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private static function findEntryKey(array $manifest): ?string
    {
        $entry = self::normalizeRelative(self::entry());
        foreach (array_keys($manifest) as $key) {
            $normalized = self::normalizeRelative($key);
            if ($normalized === $entry || str_ends_with($normalized, '/' . $entry)) {
                return $key;
            }
        }

        return null;
    }

    private static function assetUrl(string $file): ?string
    {
        if (!self::isSafeRelativePath($file)) {
            return null;
        }

        return Assets::url(
            Path::join(self::outDir(), self::normalizeRelative($file)),
            ''
        );
    }

    private static function outDir(): string
    {
        $dir = Config::get('vite.out_dir', self::DEFAULT_OUT_DIR);
        if (!is_string($dir) || !self::isSafeRelativePath($dir)) {
            return self::DEFAULT_OUT_DIR;
        }

        return self::normalizeRelative($dir);
    }

    private static function manifestFile(): string
    {
        $manifest = Config::get('vite.manifest', self::DEFAULT_MANIFEST);
        if (!is_string($manifest) || !self::isSafeRelativePath($manifest)) {
            return self::DEFAULT_MANIFEST;
        }

        return self::normalizeRelative($manifest);
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_string'));
    }

    private static function isSafeRelativePath(string $path): bool
    {
        $path = self::normalizeRelative($path);
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

    private static function normalizeRelative(string $path): string
    {
        return trim(str_replace('\\', '/', trim($path)), '/');
    }

    private static function toBool(mixed $value): bool
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

    private static function report(string $message): void
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
                ->write('warning', 'Vite: ' . $message);
        } catch (\Throwable) {
            // Diagnostics must never break the page.
        }
    }
}
