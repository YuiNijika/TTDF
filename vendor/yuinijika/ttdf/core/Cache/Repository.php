<?php

declare(strict_types=1);

namespace TTDF\Cache;

use InvalidArgumentException;
use TTDF\Support\Json;

/**
 * Serializes values into a small JSON envelope and keeps every key inside the
 * active namespace. Corrupt payloads are evicted instead of failing the
 * request.
 */
final class Repository
{
    public const KEY_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._:-]{0,191}$/';

    public const PREFIX_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._:-]{0,190}$/';

    public const MAX_TTL = 31_536_000;

    private readonly string $namespace;

    private readonly int $defaultTtl;

    public function __construct(
        private readonly Store $store,
        string $namespace = '',
        int $defaultTtl = 300
    ) {
        $namespace = trim($namespace);
        if ($namespace !== '' && preg_match('/^[A-Za-z0-9._:-]{1,180}$/', $namespace) !== 1) {
            throw new InvalidArgumentException('Invalid cache namespace');
        }

        $this->namespace = $namespace;
        $this->defaultTtl = self::clampTtl($defaultTtl);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        [$found, $value] = $this->read($key);

        return $found ? $value : $default;
    }

    public function put(string $key, mixed $value, ?int $ttl = null): bool
    {
        $fullKey = $this->key($key);

        try {
            $payload = Json::encode(['v' => $value]);
        } catch (\Throwable) {
            return false;
        }

        return $this->store->put($fullKey, $payload, $this->ttl($ttl));
    }

    public function has(string $key): bool
    {
        return $this->store->has($this->key($key));
    }

    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        [$found, $value] = $this->read($key);
        if ($found) {
            return $value;
        }

        $value = $callback();
        $this->put($key, $value, $ttl);

        return $value;
    }

    public function forget(string $key): bool
    {
        return $this->store->forget($this->key($key));
    }

    public function increment(string $key, int $by = 1, ?int $ttl = null): int|false
    {
        return $this->store->increment($this->key($key), $by, $this->ttl($ttl));
    }

    public function flush(string $prefix = ''): int
    {
        $prefix = trim($prefix);
        if ($prefix !== '' && preg_match(self::PREFIX_PATTERN, $prefix) !== 1) {
            throw new InvalidArgumentException('Invalid cache key prefix');
        }

        if ($this->namespace === '' && $prefix === '') {
            throw new InvalidArgumentException('Refusing to flush an empty cache namespace');
        }

        return $this->store->clear($this->namespace . $prefix);
    }

    public function ping(): bool
    {
        return $this->store->ping();
    }

    public function namespace(): string
    {
        return $this->namespace;
    }

    public function defaultTtl(): int
    {
        return $this->defaultTtl;
    }

    /**
     * @return array{0: bool, 1: mixed}
     */
    private function read(string $key): array
    {
        $fullKey = $this->key($key);
        $raw = $this->store->get($fullKey);
        if ($raw === null) {
            return [false, null];
        }

        $payload = Json::decode($raw);
        if (!is_array($payload) || !array_key_exists('v', $payload)) {
            $this->store->forget($fullKey);

            return [false, null];
        }

        return [true, $payload['v']];
    }

    private function key(string $key): string
    {
        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw new InvalidArgumentException('Invalid cache key');
        }

        return $this->namespace . $key;
    }

    private function ttl(?int $ttl): int
    {
        return $ttl === null ? $this->defaultTtl : self::clampTtl($ttl);
    }

    private static function clampTtl(int $ttl): int
    {
        return max(0, min(self::MAX_TTL, $ttl));
    }
}
