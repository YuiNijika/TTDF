<?php

declare(strict_types=1);

namespace TTDF\Cache;

/**
 * Redis-backed store. Keys arrive fully qualified, so clearing is constrained
 * to the caller's namespace and never touches another application's keys.
 */
final class RedisStore implements Store
{
    public function __construct(private readonly RedisClient $client)
    {
    }

    public function get(string $key): ?string
    {
        $value = $this->client->get($key);

        return is_string($value) ? $value : null;
    }

    public function put(string $key, string $value, int $ttl = 0): bool
    {
        return $ttl > 0
            ? $this->client->setEx($key, $ttl, $value)
            : $this->client->set($key, $value);
    }

    public function has(string $key): bool
    {
        return $this->client->exists($key);
    }

    public function forget(string $key): bool
    {
        return $this->client->del($key) > 0;
    }

    public function increment(string $key, int $by = 1, int $ttl = 0): int|false
    {
        $value = $this->client->incrBy($key, $by);
        if ($value === false) {
            return false;
        }

        if ($ttl > 0 && $value === $by) {
            $this->client->expire($key, $ttl);
        }

        return $value;
    }

    public function clear(string $prefix = ''): int
    {
        $pattern = $prefix === '' ? '*' : $prefix . '*';
        $deleted = 0;
        $iterator = null;
        $batches = 0;

        do {
            $keys = $this->client->scan($iterator, $pattern, 500);
            if ($keys === false) {
                break;
            }

            $keys = array_values(array_filter($keys, static fn (string $key): bool => $key !== ''));
            if ($keys !== []) {
                $deleted += $this->client->del(...$keys);
            }

            $batches++;
        } while ((int) $iterator !== 0 && $batches < 10_000);

        return $deleted;
    }

    public function ping(): bool
    {
        return $this->client->ping();
    }

    public function lastError(): ?string
    {
        return $this->client->lastError();
    }
}
