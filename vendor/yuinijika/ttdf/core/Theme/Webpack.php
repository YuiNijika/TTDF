<?php

declare(strict_types=1);

namespace TTDF\Theme;

use TTDF\Config\Config;
use TTDF\Config\ConfigLoader;
use TTDF\Support\Escaper;

/**
 * Optional webpack bridge.
 *
 * The framework injects the dev-server bundle or the production
 * webpack-assets-manifest files. It does not own entry generation, so Vue,
 * React, Svelte and plain TypeScript are all supported.
 */
final class Webpack
{
    private const DEFAULT_ORIGIN = 'http://localhost:8090';

    private const DEFAULT_ROOT = 'webpack';

    private const DEFAULT_ENTRY = 'src/main.ts';

    private const DEFAULT_ENTRY_NAME = 'main';

    private const DEFAULT_OUT_DIR = 'assets/dist';

    private const DEFAULT_MANIFEST = 'manifest.json';

    public static function enabled(): bool
    {
        return BuildManifest::toBool(Config::get('webpack.enabled', false));
    }

    public static function dev(): bool
    {
        return self::enabled() && BuildManifest::toBool(Config::get('webpack.dev', false));
    }

    public static function origin(): ?string
    {
        $origin = Config::get('webpack.origin', self::DEFAULT_ORIGIN);

        return is_string($origin) ? ConfigLoader::normalizeOrigin($origin) : null;
    }

    public static function root(): string
    {
        $root = Config::get('webpack.root', self::DEFAULT_ROOT);

        return is_string($root) && BuildManifest::isSafePath($root)
            ? BuildManifest::normalize($root)
            : self::DEFAULT_ROOT;
    }

    public static function entry(): string
    {
        $entry = Config::get('webpack.entry', self::DEFAULT_ENTRY);

        return is_string($entry) && BuildManifest::isSafePath($entry)
            ? BuildManifest::normalize($entry)
            : self::DEFAULT_ENTRY;
    }

    public static function entryName(): string
    {
        $name = Config::get('webpack.entry_name', self::DEFAULT_ENTRY_NAME);

        return is_string($name) && preg_match('/^[A-Za-z0-9_-]+$/', $name) === 1
            ? $name
            : self::DEFAULT_ENTRY_NAME;
    }

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
        return BuildManifest::read(self::outDir(), self::manifestFile(), '', 'Webpack');
    }

    private static function devHead(): string
    {
        $origin = self::origin();
        if ($origin === null) {
            self::report('invalid webpack.origin');
            return '';
        }

        $bundle = $origin . '/' . self::entryName() . '.js';

        return '<script defer src="' . Escaper::url($bundle) . '"></script>' . PHP_EOL;
    }

    private static function buildHead(): string
    {
        $manifest = self::manifest();
        if ($manifest === []) {
            return '';
        }

        [$scripts, $styles] = self::entryAssets($manifest);
        if ($scripts === []) {
            self::report('entry "' . self::entryName() . '" has no JavaScript asset in the manifest');
            return '';
        }

        $html = '';
        foreach ($styles as $style) {
            $url = BuildManifest::assetUrl(self::outDir(), $style);
            if ($url !== null) {
                $html .= '<link rel="stylesheet" href="' . $url . '">' . PHP_EOL;
            }
        }

        foreach ($scripts as $script) {
            $url = BuildManifest::assetUrl(self::outDir(), $script);
            if ($url !== null) {
                $html .= '<script defer src="' . $url . '"></script>' . PHP_EOL;
            }
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $manifest
     * @return array{0: list<string>, 1: list<string>}
     */
    private static function entryAssets(array $manifest): array
    {
        $scripts = [];
        $styles = [];
        $name = self::entryName();

        $entrypoints = $manifest['entrypoints'] ?? null;
        if (is_array($entrypoints)) {
            $entry = $entrypoints[$name] ?? null;
            if (is_array($entry)) {
                $assets = $entry['assets'] ?? null;
                if (is_array($assets)) {
                    $scripts = self::stringList($assets['js'] ?? null);
                    $styles = self::stringList($assets['css'] ?? null);

                    if ($scripts === [] && $styles === []) {
                        foreach (self::stringList($assets) as $asset) {
                            self::classify($asset, $scripts, $styles);
                        }
                    }
                }

                foreach (self::stringList($entry) as $asset) {
                    self::classify($asset, $scripts, $styles);
                }
            }
        }

        $assetMap = $manifest['assets'] ?? null;
        foreach (self::candidateKeys($name) as $key) {
            if (isset($manifest[$key]) && is_string($manifest[$key])) {
                self::classify($manifest[$key], $scripts, $styles);
            }

            if (is_array($assetMap) && isset($assetMap[$key]) && is_string($assetMap[$key])) {
                self::classify($assetMap[$key], $scripts, $styles);
            }
        }

        return [
            array_values(array_unique($scripts)),
            array_values(array_unique($styles)),
        ];
    }

    /**
     * @return list<string>
     */
    private static function candidateKeys(string $name): array
    {
        return array_values(array_unique([
            $name . '.js',
            $name . '.css',
            $name,
            self::entry(),
        ]));
    }

    /**
     * @param list<string> $scripts
     * @param list<string> $styles
     */
    private static function classify(string $asset, array &$scripts, array &$styles): void
    {
        $lower = strtolower($asset);
        if (str_ends_with($lower, '.css')) {
            $styles[] = $asset;
            return;
        }

        if (preg_match('/\.m?js$/', $lower) === 1) {
            $scripts[] = $asset;
        }
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

    private static function outDir(): string
    {
        $dir = Config::get('webpack.out_dir', self::DEFAULT_OUT_DIR);
        if (!is_string($dir) || !BuildManifest::isSafePath($dir)) {
            return self::DEFAULT_OUT_DIR;
        }

        return BuildManifest::normalize($dir);
    }

    private static function manifestFile(): string
    {
        $manifest = Config::get('webpack.manifest', self::DEFAULT_MANIFEST);
        if (!is_string($manifest) || !BuildManifest::isSafePath($manifest)) {
            return self::DEFAULT_MANIFEST;
        }

        return BuildManifest::normalize($manifest);
    }

    private static function report(string $message): void
    {
        BuildManifest::report('Webpack', $message);
    }
}
