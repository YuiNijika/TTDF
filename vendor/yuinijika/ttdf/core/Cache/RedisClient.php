<?php

declare(strict_types=1);

namespace TTDF\Cache;

/**
 * Narrow phpredis surface. Keeping it behind an interface lets the store be
 * tested without a live Redis server.
 */
interface RedisClient
{
    public function get(string $key): string|false;

    public function set(string $key, string $value): bool;

    public function setEx(string $key, int $ttl, string $value): bool;

    public function del(string ...$keys): int;

    public function exists(string $key): bool;

    public function incrBy(string $key, int $by): int|false;

    public function expire(string $key, int $ttl): bool;

    /**
     * @return list<string>|false
     */
    public function scan(?int &$iterator, string $pattern, int $count): array|false;

    public function ping(): bool;

    public function close(): void;

    public function lastError(): ?string;
}
