<?php

declare(strict_types=1);

namespace TTDF\Theme;

use InvalidArgumentException;
use RuntimeException;
use TTDF\Compat\Typecho;
use TTDF\Config\Config;
use TTDF\Support\Json;

final class Options
{
    private const SCHEMA_VERSION_PREFIX = '__schema_version_';

    private static ?self $instance = null;

    /** @var array<string, mixed> */
    private array $cache = [];

    public function __construct(
        private readonly OptionsStore $store,
        private readonly string $themeName
    ) {
    }

    public static function instance(): self
    {
        $themeName = Typecho::themeName();
        if (self::$instance === null || (self::$instance->themeName === '' && $themeName !== '')) {
            self::$instance = new self(
                new TypechoOptionsStore((string) Config::get('settings.table', 'ttdf')),
                $themeName
            );
        }

        return self::$instance;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->themeName === '') {
            return $default;
        }

        $fullName = $this->fullName($key);
        if (array_key_exists($fullName, $this->cache)) {
            return $this->cache[$fullName];
        }

        $raw = $this->store->get($fullName);
        $value = $raw === null ? $default : $this->decode($raw);
        $this->cache[$fullName] = $value;

        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        if ($this->themeName === '') {
            throw new RuntimeException('Cannot save theme settings before the active theme is resolved');
        }

        $fullName = $this->fullName($key);
        $this->store->set($fullName, Json::encode($value));
        $this->cache[$fullName] = $value;
    }

    public function delete(string $key): void
    {
        if ($this->themeName === '') {
            throw new RuntimeException('Cannot delete theme settings before the active theme is resolved');
        }

        $fullName = $this->fullName($key);
        $this->store->delete($fullName);
        unset($this->cache[$fullName]);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->themeName === '') {
            return [];
        }

        $result = [];
        $prefix = $this->themeName . '_';

        foreach ($this->store->all() as $name => $raw) {
            if ($name === '__schema_version' || str_starts_with($name, '__schema_version_')) {
                continue;
            }

            if (!str_starts_with($name, $prefix)) {
                continue;
            }
            $name = substr($name, strlen($prefix));

            $result[$name] = $this->decode($raw);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function import(array $data): void
    {
        foreach ($data as $key => $value) {
            if (str_starts_with($key, '__schema_version')) {
                continue;
            }
            $this->set($key, $value);
        }
    }

    public function ensureInstalled(): void
    {
        if ($this->themeName === '') {
            return;
        }

        if ($this->schemaVersion() !== null) {
            return;
        }

        $hasExistingRows = $this->store->all() !== [];
        $this->store->install();
        $key = $this->schemaKey();
        if ($key !== '' && $this->store->get($key) === null) {
            $version = $hasExistingRows
                ? 1
                : max(1, (int) Config::get('settings.schema_version', 2));
            $this->store->set($key, Json::encode($version));
        }
    }

    public function schemaVersion(): ?int
    {
        $key = $this->schemaKey();
        if ($key === '') {
            return null;
        }

        $value = $this->store->get($key);

        return $value !== null && ctype_digit($value) ? (int) $value : null;
    }

    public function setSchemaVersion(int $version): void
    {
        $key = $this->schemaKey();
        if ($key === '') {
            throw new RuntimeException('Cannot save schema version before the active theme is resolved');
        }

        $this->store->set($key, (string) max(1, $version));
    }

    public function store(): OptionsStore
    {
        return $this->store;
    }

    public function themeName(): string
    {
        return $this->themeName;
    }

    public static function reset(): void
    {
        self::$instance = null;
        ThemeConfig::reset();
    }

    private function fullName(string $key): string
    {
        if (preg_match('/^[A-Za-z0-9_]{1,100}$/', $key) !== 1) {
            throw new InvalidArgumentException('Invalid option name');
        }

        if ($this->themeName === '') {
            throw new RuntimeException('Theme settings are unavailable before the active theme is resolved');
        }

        if (preg_match('/^[A-Za-z0-9_-]{1,100}$/', $this->themeName) !== 1) {
            throw new RuntimeException('Invalid active theme name');
        }

        return $this->themeName . '_' . $key;
    }

    private function schemaKey(): string
    {
        if ($this->themeName === '' || preg_match('/^[A-Za-z0-9_-]{1,100}$/', $this->themeName) !== 1) {
            return '';
        }

        return self::SCHEMA_VERSION_PREFIX . $this->themeName;
    }

    private function decode(string $raw): mixed
    {
        try {
            return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $raw;
        }
    }
}
