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
            'prefix' => 'ttdf/v1',
            'cors' => [
                'allowed_origins' => [],
            ],
            'token_enabled' => false,
            'token_env' => 'TTDF_REST_TOKEN',
        ],
        'router' => [
            'enabled' => true,
            'pages_dir' => 'router',
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
        foreach (['rest', 'router', 'settings'] as $section) {
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
    private static function validate(array $config): void
    {
        $prefix = (string) ($config['rest']['prefix'] ?? '');
        if ($prefix === '' || preg_match('/^[A-Za-z0-9_\-\/]+$/', $prefix) !== 1) {
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
