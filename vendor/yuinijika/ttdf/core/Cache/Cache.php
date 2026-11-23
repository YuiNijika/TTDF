<?php

declare(strict_types=1);

namespace TTDF\Cache;

use TTDF\Compat\Typecho;
use TTDF\Theme\CacheSettings;

/**
 * Theme-facing cache facade.
 *
 * The Redis connection is created lazily on first use and every failure falls
 * back to running the caller's code directly. `remember()` therefore behaves
 * like an uncached call when Redis is disabled or unreachable.
 *
 *     Cache::remember('home:posts', static fn (): array => loadPosts(), 600);
 */
final class Cache
{
    private static ?Repository $repository = null;

    private static ?RedisConfig $config = null;

    private static ?Store $store = null;

    private static ?PhpRedisClient $client = null;

    /** @var array<string, mixed> */
    private static array $overrides = [];

    private static bool $resolved = false;

    /**
     * Override theme settings for this request. Keys use the short names
     * `enabled`, `host`, `port`, `username`, `password`, `database`, `prefix`,
     * `timeout` and `ttl`; `Cache_Redis_*` field names are also accepted.
     *
     * @param array<string, mixed> $overrides
     */
    public static function configure(array $overrides): void
    {
        self::$overrides = $overrides;
        self::resetState();
    }

    /**
     * Replace the backend, mainly for tests and advanced integrations.
     */
    public static function useStore(Store $store, ?RedisConfig $config = null): void
    {
        self::resetState();
        self::$store = $store;
        self::$config = $config ?? RedisConfig::fromArray(
            ['enabled' => true],
            self::siteUrl(),
            self::themeName()
        );
        self::$resolved = true;
        self::$repository = new Repository(
            $store,
            self::$config->namespace(),
            self::$config->ttl()
        );
    }

    public static function enabled(): bool
    {
        self::resolve();

        return self::$config?->enabled() === true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $repository = self::repository();

        return $repository === null ? $default : $repository->get($key, $default);
    }

    public static function put(string $key, mixed $value, ?int $ttl = null): bool
    {
        $repository = self::repository();

        return $repository !== null && $repository->put($key, $value, $ttl);
    }

    public static function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        $repository = self::repository();

        return $repository === null
            ? $callback()
            : $repository->remember($key, $callback, $ttl);
    }

    public static function forget(string $key): bool
    {
        $repository = self::repository();

        return $repository !== null && $repository->forget($key);
    }

    public static function increment(string $key, int $by = 1, ?int $ttl = null): int|false
    {
        $repository = self::repository();

        return $repository === null ? false : $repository->increment($key, $by, $ttl);
    }

    public static function flush(string $prefix = ''): int
    {
        $repository = self::repository();

        return $repository === null ? 0 : $repository->flush($prefix);
    }

    public static function repository(): ?Repository
    {
        self::resolve();

        return self::$repository;
    }

    public static function config(): ?RedisConfig
    {
        self::resolve();

        return self::$config;
    }

    /**
     * @return array{
     *     enabled: bool,
     *     extension: bool,
     *     connected: bool,
     *     host: string,
     *     port: int,
     *     database: int,
     *     namespace: string,
     *     error: string|null
     * }
     */
    public static function health(): array
    {
        self::resolve();

        $config = self::$config;
        $health = [
            'enabled' => $config?->enabled() ?? false,
            'extension' => extension_loaded('redis'),
            'connected' => false,
            'host' => $config?->host() ?? '127.0.0.1',
            'port' => $config?->port() ?? 6379,
            'database' => $config?->database() ?? 0,
            'namespace' => $config?->namespace() ?? '',
            'error' => null,
        ];

        if ($health['enabled'] !== true) {
            return $health;
        }

        if (self::$store === null && $health['extension'] !== true) {
            $health['error'] = 'Redis extension is not installed.';

            return $health;
        }

        $repository = self::$repository;
        if ($repository === null) {
            $health['error'] = 'Redis cache is not initialized.';

            return $health;
        }

        $health['connected'] = $repository->ping();
        if ($health['connected'] !== true) {
            $health['error'] = self::$client?->lastError() ?? 'Redis server is unreachable.';
        }

        return $health;
    }

    public static function reset(): void
    {
        self::resetState();
        self::$overrides = [];
    }

    private static function resolve(): void
    {
        if (self::$resolved) {
            return;
        }

        self::$resolved = true;

        if (self::$store !== null) {
            return;
        }

        $values = array_merge(CacheSettings::values(), self::$overrides);
        $config = RedisConfig::fromArray($values, self::siteUrl(), self::themeName());
        self::$config = $config;

        if (!$config->enabled()) {
            return;
        }

        $client = new PhpRedisClient($config);
        self::$client = $client;
        self::$repository = new Repository(
            new RedisStore($client),
            $config->namespace(),
            $config->ttl()
        );
    }

    private static function resetState(): void
    {
        if (self::$client !== null) {
            self::$client->close();
        }

        self::$client = null;
        self::$store = null;
        self::$repository = null;
        self::$config = null;
        self::$resolved = false;
    }

    private static function siteUrl(): string
    {
        try {
            return Typecho::siteUrl();
        } catch (\Throwable) {
            return '';
        }
    }

    private static function themeName(): string
    {
        try {
            return Typecho::themeName();
        } catch (\Throwable) {
            return '';
        }
    }
}
