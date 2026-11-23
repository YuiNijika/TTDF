<?php

declare(strict_types=1);

namespace TTDF\Cache;

use TTDF\Theme\CacheSettings;

/**
 * Normalized Redis settings read from theme options. Secrets are kept out of
 * diagnostic arrays and are never trimmed or rewritten.
 */
final class RedisConfig
{
    private const DEFAULT_HOST = '127.0.0.1';

    private const DEFAULT_PORT = 6379;

    private const DEFAULT_TIMEOUT = 1.0;

    private const DEFAULT_TTL = 300;

    private const MAX_TTL = 31_536_000;

    private function __construct(
        private readonly bool $enabled,
        private readonly string $host,
        private readonly int $port,
        private readonly string $username,
        private readonly string $password,
        private readonly int $database,
        private readonly string $prefix,
        private readonly float $timeout,
        private readonly int $ttl,
        private readonly string $namespace
    ) {
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function fromArray(array $values, string $siteUrl = '', string $themeName = ''): self
    {
        $enabled = self::toBool(self::value($values, 'enabled', CacheSettings::ENABLED, false));
        $host = self::normalizeHost(self::value($values, 'host', CacheSettings::HOST, self::DEFAULT_HOST));
        $port = self::normalizeInt(
            self::value($values, 'port', CacheSettings::PORT, self::DEFAULT_PORT),
            self::DEFAULT_PORT,
            1,
            65535
        );
        $username = self::normalizeUsername(
            self::value($values, 'username', CacheSettings::USERNAME, '')
        );
        $password = self::normalizePassword(
            self::value($values, 'password', CacheSettings::PASSWORD, '')
        );
        $database = self::normalizeInt(
            self::value($values, 'database', CacheSettings::DATABASE, 0),
            0,
            0,
            255
        );
        $prefix = self::normalizePrefix(self::value($values, 'prefix', CacheSettings::PREFIX, ''));
        $timeout = self::normalizeTimeout(
            self::value($values, 'timeout', CacheSettings::TIMEOUT, self::DEFAULT_TIMEOUT)
        );
        $ttl = self::normalizeInt(
            self::value($values, 'ttl', CacheSettings::TTL, self::DEFAULT_TTL),
            self::DEFAULT_TTL,
            0,
            self::MAX_TTL
        );

        return new self(
            $enabled,
            $host,
            $port,
            $username,
            $password,
            $database,
            $prefix,
            $timeout,
            $ttl,
            self::buildNamespace($siteUrl, $themeName, $prefix)
        );
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function host(): string
    {
        return $this->host;
    }

    public function port(): int
    {
        return $this->port;
    }

    public function username(): string
    {
        return $this->username;
    }

    public function password(): string
    {
        return $this->password;
    }

    public function database(): int
    {
        return $this->database;
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    public function timeout(): float
    {
        return $this->timeout;
    }

    public function ttl(): int
    {
        return $this->ttl;
    }

    public function namespace(): string
    {
        return $this->namespace;
    }

    /**
     * Safe diagnostics payload. The password is intentionally excluded.
     *
     * @return array{enabled: bool, host: string, port: int, database: int, namespace: string}
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'host' => $this->host,
            'port' => $this->port,
            'database' => $this->database,
            'namespace' => $this->namespace,
        ];
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function value(
        array $values,
        string $key,
        string $fieldName,
        mixed $default
    ): mixed {
        if (array_key_exists($key, $values)) {
            return $values[$key];
        }

        if (array_key_exists($fieldName, $values)) {
            return $values[$fieldName];
        }

        return $default;
    }

    private static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return is_scalar($value)
            && in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    private static function normalizeHost(mixed $value): string
    {
        $host = is_scalar($value) ? trim((string) $value) : '';
        if (
            $host === ''
            || strlen($host) > 253
            || preg_match('/[\x00-\x20\x7F]/', $host) === 1
        ) {
            return self::DEFAULT_HOST;
        }

        $bare = trim($host, '[]');
        $isIp = filter_var($bare, FILTER_VALIDATE_IP) !== false;
        if (preg_match('/^[A-Za-z0-9._-]+$/', $host) !== 1 && !$isIp) {
            return self::DEFAULT_HOST;
        }

        return $host;
    }

    private static function normalizeUsername(mixed $value): string
    {
        $username = is_scalar($value) ? (string) $value : '';
        $username = trim((string) preg_replace('/[\x00-\x1F\x7F]+/', '', $username));

        return substr($username, 0, 128);
    }

    private static function normalizePassword(mixed $value): string
    {
        $password = is_scalar($value) ? (string) $value : '';

        return substr($password, 0, 512);
    }

    private static function normalizePrefix(mixed $value): string
    {
        $prefix = is_scalar($value) ? trim((string) $value) : '';
        $prefix = (string) preg_replace('/[^A-Za-z0-9._:-]+/', '', $prefix);
        $prefix = trim(substr($prefix, 0, 64), ':');

        return $prefix;
    }

    private static function normalizeTimeout(mixed $value): float
    {
        $timeout = is_numeric($value) ? (float) $value : self::DEFAULT_TIMEOUT;
        if (!is_finite($timeout) || $timeout <= 0) {
            return self::DEFAULT_TIMEOUT;
        }

        return max(0.1, min(10.0, $timeout));
    }

    private static function normalizeInt(mixed $value, int $default, int $min, int $max): int
    {
        $number = is_numeric($value) ? (int) $value : $default;

        return max($min, min($max, $number));
    }

    private static function buildNamespace(string $siteUrl, string $themeName, string $prefix): string
    {
        $site = strtolower(rtrim(trim($siteUrl), '/'));
        $hash = substr(hash('sha256', $site), 0, 10);

        $theme = (string) preg_replace('/[^A-Za-z0-9_-]+/', '', $themeName);
        $theme = substr($theme === '' ? 'theme' : $theme, 0, 40);

        $namespace = 'ttdf:' . $hash . ':' . $theme . ':';
        if ($prefix !== '') {
            $namespace .= $prefix . ':';
        }

        return $namespace;
    }
}
