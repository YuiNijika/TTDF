<?php

declare(strict_types=1);

namespace TTDF\Http\Rest;

use TTDF\Config\Config;
use TTDF\Http\Request;

/**
 * REST mount points.
 *
 * `rest.v1_prefix` and `rest.v2_prefix` are the primary prefixes. `rest.prefix`
 * is an optional map of extra prefixes so a site can expose the same API on
 * more than one path, for example `mobile => v2`. A plain string is still read
 * as the legacy v1 prefix when `rest.v1_prefix` is absent.
 */
final class Prefixes
{
    public const V1 = 'v1';

    public const V2 = 'v2';

    private const DEFAULT_V1 = 'ttdf/v1';

    private const DEFAULT_V2 = 'ttdf/v2';

    private const MAX_LENGTH = 180;

    public static function v1(): string
    {
        $configured = Config::get('rest.v1_prefix', null);
        if (is_string($configured) && trim($configured) !== '') {
            return self::normalize($configured) ?? self::DEFAULT_V1;
        }

        // Backwards compatibility: older themes used `rest.prefix` for v1.
        $legacy = Config::get('rest.prefix', null);
        if (is_string($legacy) && trim($legacy) !== '') {
            return self::normalize($legacy) ?? self::DEFAULT_V1;
        }

        return self::DEFAULT_V1;
    }

    public static function v2(): string
    {
        $configured = Config::get('rest.v2_prefix', null);
        if (is_string($configured) && trim($configured) !== '') {
            return self::normalize($configured) ?? self::DEFAULT_V2;
        }

        return self::DEFAULT_V2;
    }

    /**
     * Every mount, primary prefixes first. Invalid and duplicate entries are
     * ignored so a broken config cannot shadow a working mount.
     *
     * @return list<array{prefix: string, api: string}>
     */
    public static function all(): array
    {
        $mounts = [];
        $seen = [];

        foreach ([
            ['prefix' => self::v1(), 'api' => self::V1],
            ['prefix' => self::v2(), 'api' => self::V2],
        ] as $mount) {
            if (isset($seen[$mount['prefix']])) {
                continue;
            }

            $seen[$mount['prefix']] = true;
            $mounts[] = $mount;
        }

        foreach (self::extra() as $mount) {
            if (!in_array($mount['api'], [self::V1, self::V2], true)) {
                continue;
            }

            if (isset($seen[$mount['prefix']])) {
                continue;
            }

            $seen[$mount['prefix']] = true;
            $mounts[] = $mount;
        }

        return $mounts;
    }

    /**
     * @return list<array{prefix: string, api: string}>
     */
    public static function for(string $api): array
    {
        return array_values(array_filter(
            self::all(),
            static fn (array $mount): bool => $mount['api'] === $api
        ));
    }

    /**
     * The prefix that currently matches the request for one API surface.
     * Longest match wins so `api/v2` is not swallowed by `api`.
     */
    public static function match(string $api): ?string
    {
        $path = Request::path();
        $matches = self::for($api);
        usort(
            $matches,
            static fn (array $left, array $right): int => strlen($right['prefix']) <=> strlen($left['prefix'])
        );

        foreach ($matches as $mount) {
            if ($path === $mount['prefix'] || str_starts_with($path, $mount['prefix'] . '/')) {
                return $mount['prefix'];
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function strings(): array
    {
        return array_map(
            static fn (array $mount): string => $mount['prefix'],
            self::all()
        );
    }

    public static function normalize(mixed $prefix): ?string
    {
        if (!is_string($prefix)) {
            return null;
        }

        $prefix = trim($prefix);
        $prefix = trim($prefix, '/');
        if ($prefix === '' || strlen($prefix) > self::MAX_LENGTH) {
            return null;
        }

        if (str_contains($prefix, '..') || str_contains($prefix, '\\') || str_contains($prefix, "\0")) {
            return null;
        }

        if (preg_match('#^[A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+)*$#', $prefix) !== 1) {
            return null;
        }

        return $prefix;
    }

    /**
     * @return list<array{prefix: string, api: string}>
     */
    private static function extra(): array
    {
        $configured = Config::get('rest.prefix', []);
        if (!is_array($configured)) {
            return [];
        }

        // A single mount written as ['prefix' => 'api', 'api' => 'v2'].
        // A bare ['api' => 'v2'] stays a map entry for a prefix named "api".
        if (array_key_exists('prefix', $configured)) {
            $configured = [$configured];
        }

        $mounts = [];
        foreach ($configured as $key => $value) {
            $mount = self::parseMount($key, $value);
            if ($mount !== null) {
                $mounts[] = $mount;
            }
        }

        return $mounts;
    }

    /**
     * @return array{prefix: string, api: string}|null
     */
    private static function parseMount(int|string $key, mixed $value): ?array
    {
        if (is_string($value)) {
            if (is_int($key)) {
                return null;
            }

            return self::mount((string) $key, $value);
        }

        if (!is_array($value)) {
            return null;
        }

        if (($value['enabled'] ?? true) === false) {
            return null;
        }

        $prefix = $value['prefix'] ?? (is_string($key) ? $key : '');
        $api = $value['api'] ?? $value['version'] ?? '';

        return self::mount((string) $prefix, (string) $api);
    }

    /**
     * @return array{prefix: string, api: string}|null
     */
    private static function mount(string $prefix, string $api): ?array
    {
        $normalized = self::normalize($prefix);
        if ($normalized === null) {
            return null;
        }

        $api = strtolower(trim($api));
        if (!in_array($api, [self::V1, self::V2], true)) {
            return null;
        }

        return ['prefix' => $normalized, 'api' => $api];
    }
}
