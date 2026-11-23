<?php

declare(strict_types=1);

namespace TTDF\Cache;

/**
 * Lazily connects to Redis on first use. Connection errors never escape: the
 * caller sees `false` and the site keeps working without cache.
 */
final class PhpRedisClient implements RedisClient
{
    private ?\Redis $connection = null;

    private bool $attempted = false;

    private ?string $error = null;

    public function __construct(private readonly RedisConfig $config)
    {
    }

    public function get(string $key): string|false
    {
        $redis = $this->connection();
        if ($redis === null) {
            return false;
        }

        try {
            $value = $redis->get($key);
        } catch (\Throwable $exception) {
            $this->fail($exception);

            return false;
        }

        return is_string($value) ? $value : false;
    }

    public function set(string $key, string $value): bool
    {
        $redis = $this->connection();
        if ($redis === null) {
            return false;
        }

        try {
            return (bool) $redis->set($key, $value);
        } catch (\Throwable $exception) {
            $this->fail($exception);

            return false;
        }
    }

    public function setEx(string $key, int $ttl, string $value): bool
    {
        $redis = $this->connection();
        if ($redis === null) {
            return false;
        }

        try {
            return (bool) $redis->setEx($key, $ttl, $value);
        } catch (\Throwable $exception) {
            $this->fail($exception);

            return false;
        }
    }

    public function del(string ...$keys): int
    {
        $redis = $this->connection();
        if ($redis === null || $keys === []) {
            return 0;
        }

        try {
            return (int) $redis->del(...$keys);
        } catch (\Throwable $exception) {
            $this->fail($exception);

            return 0;
        }
    }

    public function exists(string $key): bool
    {
        $redis = $this->connection();
        if ($redis === null) {
            return false;
        }

        try {
            return (int) $redis->exists($key) > 0;
        } catch (\Throwable $exception) {
            $this->fail($exception);

            return false;
        }
    }

    public function incrBy(string $key, int $by): int|false
    {
        $redis = $this->connection();
        if ($redis === null) {
            return false;
        }

        try {
            $value = $redis->incrBy($key, $by);
        } catch (\Throwable $exception) {
            $this->fail($exception);

            return false;
        }

        return is_int($value) ? $value : false;
    }

    public function expire(string $key, int $ttl): bool
    {
        $redis = $this->connection();
        if ($redis === null) {
            return false;
        }

        try {
            return (bool) $redis->expire($key, $ttl);
        } catch (\Throwable $exception) {
            $this->fail($exception);

            return false;
        }
    }

    public function scan(?int &$iterator, string $pattern, int $count): array|false
    {
        $redis = $this->connection();
        if ($redis === null) {
            return false;
        }

        try {
            $keys = $redis->scan($iterator, $pattern, $count);
        } catch (\Throwable $exception) {
            $this->fail($exception);

            return false;
        }

        if (!is_array($keys)) {
            return false;
        }

        return array_values(array_filter($keys, 'is_string'));
    }

    public function ping(): bool
    {
        $redis = $this->connection();
        if ($redis === null) {
            return false;
        }

        try {
            $result = $redis->ping();
        } catch (\Throwable $exception) {
            $this->fail($exception);

            return false;
        }

        return $result !== false;
    }

    public function close(): void
    {
        if ($this->connection !== null) {
            $this->closeConnection($this->connection);
        }

        $this->connection = null;
        $this->attempted = false;
        $this->error = null;
    }

    public function lastError(): ?string
    {
        return $this->error;
    }

    private function connection(): ?\Redis
    {
        if ($this->attempted) {
            return $this->connection;
        }

        $this->attempted = true;

        if (!extension_loaded('redis') || !class_exists('\Redis')) {
            $this->error = 'Redis extension is not installed.';

            return null;
        }

        $redis = new \Redis();

        try {
            $connected = @$redis->connect($this->config->host(), $this->config->port(), $this->config->timeout());
            if ($connected !== true) {
                $this->error = 'Unable to connect to Redis.';
                $this->closeConnection($redis);

                return null;
            }

            $readTimeout = defined('\Redis::OPT_READ_TIMEOUT')
                ? (int) constant('\Redis::OPT_READ_TIMEOUT')
                : null;
            if ($readTimeout !== null) {
                @$redis->setOption($readTimeout, $this->config->timeout());
            }

            $username = $this->config->username();
            $password = $this->config->password();
            if ($username !== '' || $password !== '') {
                $authenticated = $username !== ''
                    ? @$redis->auth(['user' => $username, 'pass' => $password])
                    : @$redis->auth($password);
                if ($authenticated !== true) {
                    $this->error = 'Redis authentication failed.';
                    $this->closeConnection($redis);

                    return null;
                }
            }

            if ($this->config->database() > 0 && @$redis->select($this->config->database()) !== true) {
                $this->error = 'Unable to select the Redis database.';
                $this->closeConnection($redis);

                return null;
            }

            $pong = @$redis->ping();
            if ($pong === false) {
                $this->error = 'Redis ping failed.';
                $this->closeConnection($redis);

                return null;
            }
        } catch (\Throwable $exception) {
            $this->error = $this->sanitizeError($exception->getMessage());
            $this->closeConnection($redis);

            return null;
        }

        $this->error = null;

        return $this->connection = $redis;
    }

    private function fail(\Throwable $exception): void
    {
        $this->error = $this->sanitizeError($exception->getMessage());
        if ($this->connection !== null) {
            $this->closeConnection($this->connection);
        }

        $this->connection = null;
        $this->attempted = true;
    }

    private function closeConnection(\Redis $redis): void
    {
        try {
            $redis->close();
        } catch (\Throwable) {
        }
    }

    private function sanitizeError(string $message): string
    {
        $message = trim((string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', $message));

        return $message === '' ? 'Redis operation failed.' : substr($message, 0, 200);
    }
}
