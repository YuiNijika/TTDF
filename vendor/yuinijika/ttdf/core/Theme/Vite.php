<?php

declare(strict_types=1);

namespace TTDF\Theme;

use TTDF\Config\Config;
use TTDF\Config\ConfigLoader;
use TTDF\Support\Escaper;

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

    public static function enabled(): bool
    {
        return BuildManifest::toBool(Config::get('vite.enabled', false));
    }

    public static function dev(): bool
    {
        return self::enabled() && BuildManifest::toBool(Config::get('vite.dev', false));
    }

    public static function origin(): ?string
    {
        $origin = Config::get('vite.origin', self::DEFAULT_ORIGIN);

        return is_string($origin) ? ConfigLoader::normalizeOrigin($origin) : null;
    }

    public static function root(): string
    {
        $root = Config::get('vite.root', self::DEFAULT_ROOT);

        return is_string($root) && BuildManifest::isSafePath($root)
            ? BuildManifest::normalize($root)
            : self::DEFAULT_ROOT;
    }

    public static function entry(): string
    {
        $entry = Config::get('vite.entry', self::DEFAULT_ENTRY);

        return is_string($entry) && BuildManifest::isSafePath($entry)
            ? BuildManifest::normalize($entry)
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
        $fallback = self::manifestFile() === self::DEFAULT_MANIFEST ? 'manifest.json' : '';

        return BuildManifest::read(self::outDir(), self::manifestFile(), $fallback, 'Vite');
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
                if (BuildManifest::isSafePath($css)) {
                    $styles[] = BuildManifest::normalize($css);
                }
            }

            if (!$isEntry && isset($chunk['file']) && is_string($chunk['file'])) {
                if (BuildManifest::isSafePath($chunk['file'])) {
                    $preloads[] = BuildManifest::normalize($chunk['file']);
                }
            }
        };
        $collect($entryKey, true);

        $entryChunk = $manifest[$entryKey];
        $entryFile = isset($entryChunk['file']) && is_string($entryChunk['file'])
            ? BuildManifest::normalize($entryChunk['file'])
            : '';
        if ($entryFile === '' || !BuildManifest::isSafePath($entryFile)) {
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
        $entry = BuildManifest::normalize(self::entry());
        foreach (array_keys($manifest) as $key) {
            $normalized = BuildManifest::normalize($key);
            if ($normalized === $entry || str_ends_with($normalized, '/' . $entry)) {
                return $key;
            }
        }

        return null;
    }

    private static function assetUrl(string $file): ?string
    {
        return BuildManifest::assetUrl(self::outDir(), $file);
    }

    private static function outDir(): string
    {
        $dir = Config::get('vite.out_dir', self::DEFAULT_OUT_DIR);
        if (!is_string($dir) || !BuildManifest::isSafePath($dir)) {
            return self::DEFAULT_OUT_DIR;
        }

        return BuildManifest::normalize($dir);
    }

    private static function manifestFile(): string
    {
        $manifest = Config::get('vite.manifest', self::DEFAULT_MANIFEST);
        if (!is_string($manifest) || !BuildManifest::isSafePath($manifest)) {
            return self::DEFAULT_MANIFEST;
        }

        return BuildManifest::normalize($manifest);
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

    private static function report(string $message): void
    {
        BuildManifest::report('Vite', $message);
    }
}
