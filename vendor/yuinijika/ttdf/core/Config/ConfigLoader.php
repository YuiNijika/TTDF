<?php

declare(strict_types=1);

namespace TTDF\Config;

use RuntimeException;
use TTDF\Theme\Paths;

final class ConfigLoader
{
    /** @var array<string, mixed> */
    private const DEFAULTS = [
        'debug' => false,
        'rest' => [
            'enabled' => false,
            'v1_prefix' => 'ttdf/v1',
            'prefix' => [],
            'cors' => [
                'allowed_origins' => [],
            ],
            'token_enabled' => false,
            'token_env' => 'TTDF_REST_TOKEN',
            'v2_enabled' => false,
            'v2_prefix' => 'ttdf/v2',
            'v2_token_ttl' => 0,
        ],
        'router' => [
            'enabled' => true,
            'pages_dir' => 'router',
        ],
        'vite' => [
            'enabled' => false,
            'dev' => false,
            'origin' => 'http://localhost:5173',
            'root' => 'vite',
            'entry' => 'src/main.ts',
            'out_dir' => 'assets/dist',
            'manifest' => '.vite/manifest.json',
        ],
        // Internal storage default. Themes may override the table name, but
        // app/config/app.php does not advertise it as a public option.
        'settings' => [
            'table' => 'ttdf',
        ],
    ];

    /**
     * @return array<string, mixed>
     */
    public static function load(string $root): array
    {
        $file = Paths::appConfig($root);
        if (!is_file($file)) {
            throw new RuntimeException('TTDF theme configuration file not found: ' . $file);
        }

        $config = require $file;
        if (!is_array($config)) {
            throw new RuntimeException('TTDF configuration must return an array');
        }

        $config = array_replace_recursive(self::DEFAULTS, $config);
        unset($config['version'], $config['assets']);
        self::validateShape($config);
        self::applyEnvironment($config);
        self::normalizeBooleans($config);
        self::validate($config);

        return $config;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function validateShape(array $config): void
    {
        foreach (['rest', 'router', 'settings', 'vite'] as $section) {
            if (!is_array($config[$section] ?? null)) {
                throw new RuntimeException('TTDF configuration section must be an array: ' . $section);
            }
        }

        if (!is_array($config['rest']['cors'] ?? null)) {
            throw new RuntimeException('TTDF configuration section must be an array: rest.cors');
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function applyEnvironment(array &$config): void
    {
        $debug = getenv('TTDF_DEBUG');
        if ($debug !== false) {
            $config['debug'] = self::toBool($debug);
        }

        $restEnabled = getenv('TTDF_REST_ENABLED');
        if ($restEnabled !== false) {
            $config['rest']['enabled'] = self::toBool($restEnabled);
        }

        $tokenEnabled = getenv('TTDF_REST_TOKEN_ENABLED');
        if ($tokenEnabled !== false) {
            $config['rest']['token_enabled'] = self::toBool($tokenEnabled);
        }

        $origins = getenv('TTDF_REST_CORS_ORIGINS');
        if ($origins !== false) {
            $config['rest']['cors']['allowed_origins'] = array_values(array_filter(array_map(
                static fn (string $origin): string => trim($origin),
                explode(',', $origins)
            )));
        }

        $table = getenv('TTDF_SETTINGS_TABLE');
        if ($table !== false && preg_match('/^[A-Za-z0-9_]+$/', $table) === 1) {
            $config['settings']['table'] = $table;
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function validate(array &$config): void
    {
        foreach (['v1_prefix', 'v2_prefix'] as $key) {
            $prefix = $config['rest'][$key] ?? null;
            if ($prefix !== null && !is_string($prefix)) {
                throw new RuntimeException('Invalid REST prefix: ' . $key);
            }

            self::validatePrefix($prefix, 'Invalid REST prefix: ' . $key);
        }

        // `rest.prefix` is still accepted as the legacy v1 string, but the
        // current schema uses it as a map of extra mounts.
        $legacyPrefix = $config['rest']['prefix'] ?? null;
        if (is_string($legacyPrefix)) {
            self::validatePrefix($legacyPrefix, 'Invalid REST prefix');
        } elseif (!is_array($legacyPrefix)) {
            throw new RuntimeException('Invalid REST prefix');
        }

        $table = (string) ($config['settings']['table'] ?? '');
        if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) {
            throw new RuntimeException('Invalid settings table name');
        }

        $pagesDir = (string) ($config['router']['pages_dir'] ?? '');
        if (
            $pagesDir === ''
            || str_starts_with($pagesDir, '/')
            || str_contains($pagesDir, '..')
            || $pagesDir === 'app'
            || str_starts_with($pagesDir, 'app/')
            || preg_match('/^[A-Za-z0-9_-]+(?:\/[A-Za-z0-9_-]+)*$/', $pagesDir) !== 1
        ) {
            throw new RuntimeException('Invalid router pages_dir; expected a path relative to app/');
        }

        $origins = $config['rest']['cors']['allowed_origins'] ?? [];
        if (!is_array($origins)) {
            throw new RuntimeException('CORS allowed_origins must be an array');
        }

        $config['vite']['origin'] = self::validateVite($config['vite']);
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function normalizeBooleans(array &$config): void
    {
        $config['debug'] = self::toBool($config['debug'] ?? false);
        $config['rest']['enabled'] = self::toBool($config['rest']['enabled'] ?? false);
        $config['rest']['token_enabled'] = self::toBool($config['rest']['token_enabled'] ?? false);
        $config['router']['enabled'] = self::toBool($config['router']['enabled'] ?? true);
        $config['vite']['enabled'] = self::toBool($config['vite']['enabled'] ?? false);
        $config['vite']['dev'] = self::toBool($config['vite']['dev'] ?? false);
        $ttl = $config['rest']['v2_token_ttl'] ?? 0;
        $config['rest']['v2_token_ttl'] = is_numeric($ttl) ? (int) $ttl : 0;
    }

    private static function validatePrefix(?string $prefix, string $message): void
    {
        if ($prefix === null) {
            return;
        }

        $prefix = trim($prefix);
        if (
            $prefix === ''
            || str_contains($prefix, '..')
            || str_contains($prefix, '\\')
            || preg_match('#^[A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+)*$#', trim($prefix, '/')) !== 1
        ) {
            throw new RuntimeException($message);
        }
    }

    /**
     * @param array<string, mixed> $vite
     */
    private static function validateVite(array $vite): string
    {
        foreach (['root', 'entry', 'out_dir', 'manifest'] as $key) {
            $value = $vite[$key] ?? null;
            if (!is_string($value) || !self::isSafeRelativePath($value)) {
                throw new RuntimeException('Invalid vite.' . $key . '; expected a relative path');
            }
        }

        $root = self::normalizeRelative((string) $vite['root']);
        foreach (['app', 'vendor', 'logs'] as $protected) {
            if ($root === $protected || str_starts_with($root, $protected . '/')) {
                throw new RuntimeException('Invalid vite.root; source cannot live in a framework directory');
            }
        }

        $outDir = self::normalizeRelative((string) $vite['out_dir']);
        if ($outDir === $root || str_starts_with($outDir, $root . '/')) {
            throw new RuntimeException('Invalid vite.out_dir; the build output cannot live inside vite.root');
        }

        if (str_starts_with($root, $outDir . '/')) {
            throw new RuntimeException('Invalid vite.out_dir; vite.root cannot live inside the build output');
        }

        foreach (['app', 'vendor', 'logs', 'vite'] as $protected) {
            if ($outDir === $protected || str_starts_with($outDir, $protected . '/')) {
                throw new RuntimeException('Invalid vite.out_dir; the build output cannot replace a framework directory');
            }
        }

        // `assets/` itself contains style.css and img/; building into it would
        // make Vite's emptyOutDir remove those source assets.
        if ($outDir === 'assets') {
            throw new RuntimeException('Invalid vite.out_dir; choose a build subdirectory such as assets/dist');
        }

        $origin = $vite['origin'] ?? null;
        $normalized = is_string($origin) ? self::normalizeOrigin($origin) : null;
        if ($normalized === null) {
            throw new RuntimeException('Invalid vite.origin; expected an http(s) origin such as http://localhost:5173');
        }

        return $normalized;
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

    /**
     * Dev-server addresses are origins only. A path, query, fragment or
     * userinfo would turn the generated asset URL into something else.
     */
    public static function normalizeOrigin(string $origin): ?string
    {
        $origin = trim($origin);
        if ($origin === '' || str_contains($origin, '\\') || preg_match('/[\x00-\x20]/', $origin) === 1) {
            return null;
        }

        $parts = parse_url($origin);
        if (!is_array($parts)) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        foreach (['user', 'pass', 'query', 'fragment'] as $key) {
            if (isset($parts[$key]) && $parts[$key] !== '') {
                return null;
            }
        }

        if (isset($parts['path']) && $parts['path'] !== '' && $parts['path'] !== '/') {
            return null;
        }

        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
        $host = str_contains($host, ':') ? '[' . trim($host, '[]') . ']' : $host;

        return $scheme . '://' . $host . $port;
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
}
