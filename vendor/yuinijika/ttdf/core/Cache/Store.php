<?php

declare(strict_types=1);

namespace TTDF\Cache;

/**
 * Minimal key/value contract used by the cache repository.
 *
 * Implementations receive fully-qualified keys. Namespacing, JSON
 * serialization and default TTL resolution are handled by Repository so the
 * same store can back Redis, an array-based test double or a future driver.
 */
interface Store
{
    public function get(string $key): ?string;

    public function put(string $key, string $value, int $ttl = 0): bool;

    public function has(string $key): bool;

    public function forget(string $key): bool;

    public function increment(string $key, int $by = 1, int $ttl = 0): int|false;

    public function clear(string $prefix = ''): int;

    public function ping(): bool;
}
