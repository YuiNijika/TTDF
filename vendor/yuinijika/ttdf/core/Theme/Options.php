<?php

declare(strict_types=1);

namespace TTDF\Theme;

use InvalidArgumentException;
use RuntimeException;
use TTDF\Compat\Typecho;
use TTDF\Config\Config;
use TTDF\Support\Json;

/**
 * Theme settings are stored as one JSON document per theme.
 *
 * The row name is the active theme name, stored either in TTDF's dedicated
 * settings table or - when `__TTDF_DATABASE_TABLE__` is defined as `false` -
 * in Typecho's own options table under `ttdf:{theme}`. Every save keeps the
 * previous document as `{theme}:backup_{timestamp}`.
 */
final class Options
{
    private const BACKUP_MARKER = ':backup_';

    private const COMPACT_BACKUP_MARKER = ':b_';

    private const BACKUP_TIMESTAMP_LENGTH = 10;

    private static ?self $instance = null;

    /** @var array<string, mixed> */
    private array $data = [];

    private bool $loaded = false;

    private ?string $lastBackupKey = null;

    public function __construct(
        private readonly OptionsStore $store,
        private readonly string $themeName
    ) {
        if ($themeName !== '' && preg_match('/^[A-Za-z0-9_-]{1,100}$/', $themeName) !== 1) {
            throw new InvalidArgumentException('Invalid active theme name');
        }
    }

    public static function instance(): self
    {
        $themeName = Typecho::themeName();
        if (self::$instance === null || (self::$instance->themeName === '' && $themeName !== '')) {
            self::$instance = new self(
                self::createStore(),
                $themeName
            );
        }

        return self::$instance;
    }

    /**
     * `__TTDF_DATABASE_TABLE__` defaults to the dedicated `ttdf` table.
     * Defining it as `false` stores settings inside Typecho's options table.
     */
    public static function usesDedicatedTable(): bool
    {
        return StorageMode::dedicatedTableEnabled(self::databaseTableFlag());
    }

    /**
     * Logical table used by the active storage driver.
     */
    public static function storageTable(): string
    {
        return self::usesDedicatedTable()
            ? (string) Config::get('settings.table', 'ttdf')
            : 'options';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->themeName === '') {
            return $default;
        }

        $this->assertKey($key);
        $this->load();

        return array_key_exists($key, $this->data) ? $this->data[$key] : $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->merge([$key => $value]);
    }

    /**
     * Merge values into the current JSON document with one write and one
     * backup of the previous document.
     *
     * @param array<string, mixed> $values
     */
    public function merge(array $values): void
    {
        $this->assertWritable();
        $this->load();

        if ($values === []) {
            return;
        }

        $next = $this->data;
        foreach ($values as $key => $value) {
            $this->assertKey($key);
            $next[$key] = $value;
        }

        $this->persist($next);
    }

    /**
     * Replace the complete JSON document with the provided values.
     *
     * @param array<string, mixed> $values
     */
    public function replace(array $values): void
    {
        $this->assertWritable();
        $next = [];

        foreach ($values as $key => $value) {
            $this->assertKey($key);
            $next[$key] = $value;
        }

        $this->persist($next);
    }

    public function delete(string $key): void
    {
        $this->assertWritable();
        $this->assertKey($key);
        $this->load();

        if (!array_key_exists($key, $this->data)) {
            return;
        }

        $next = $this->data;
        unset($next[$key]);
        $this->persist($next);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->themeName === '') {
            return [];
        }

        $this->load();
        return $this->data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function import(array $data): void
    {
        $this->merge($data);
    }

    /**
     * Create the settings table only from explicit admin or save actions.
     */
    public function ensureInstalled(): void
    {
        if ($this->themeName === '') {
            return;
        }

        $this->store->install();
    }

    public function exists(): bool
    {
        if ($this->themeName === '') {
            return false;
        }

        $raw = $this->store->get($this->key());
        if ($raw === null || trim($raw) === '') {
            return false;
        }

        return is_array(Json::decode($raw));
    }

    /**
     * Back up the currently persisted JSON document.
     */
    public function backup(?int $timestamp = null): ?string
    {
        if ($this->themeName === '') {
            return null;
        }

        $raw = $this->store->get($this->key());
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        return $this->writeBackup($raw, $timestamp ?? time());
    }

    /**
     * @return list<array{key: string, timestamp: int, settings: array<string, mixed>}>
     */
    public function backups(): array
    {
        if ($this->themeName === '') {
            return [];
        }

        $prefixes = $this->backupPrefixes();
        if ($prefixes === []) {
            return [];
        }

        $items = [];

        foreach ($this->store->all() as $name => $raw) {
            $prefix = $this->matchBackupPrefix($name, $prefixes);
            if ($prefix === null) {
                continue;
            }

            $timestamp = substr($name, strlen($prefix));
            if ($timestamp === '' || ctype_digit($timestamp) === false) {
                continue;
            }

            $settings = Json::decode($raw);
            if (!is_array($settings)) {
                continue;
            }

            $items[] = [
                'key' => $name,
                'timestamp' => (int) $timestamp,
                'settings' => $settings,
            ];
        }

        usort(
            $items,
            static fn (array $left, array $right): int => $right['timestamp'] <=> $left['timestamp']
        );

        return $items;
    }

    public function restore(string $key): bool
    {
        if ($this->themeName === '' || !$this->isBackupKey($key)) {
            return false;
        }

        $raw = $this->store->get($key);
        if ($raw === null) {
            return false;
        }

        $settings = Json::decode($raw);
        if (!is_array($settings)) {
            return false;
        }

        $this->replace($settings);
        return true;
    }

    public function store(): OptionsStore
    {
        return $this->store;
    }

    public function themeName(): string
    {
        return $this->themeName;
    }

    public function backupPrefix(): string
    {
        $prefixes = $this->backupPrefixes();
        if ($prefixes === []) {
            throw $this->keyLengthError();
        }

        return $prefixes[0];
    }

    /**
     * Backup prefixes that fit the backing column, longest first.
     *
     * `:backup_` is preferred; a theme name that leaves no room falls back to
     * the compact `:b_` marker. Both forms stay readable and keep the Unix
     * timestamp intact.
     *
     * @return list<string>
     */
    public function backupPrefixes(): array
    {
        if ($this->themeName === '') {
            return [];
        }

        $prefixes = [];
        foreach ([self::BACKUP_MARKER, self::COMPACT_BACKUP_MARKER] as $marker) {
            $prefix = $this->store->key($this->themeName . $marker);
            if ($this->fits($prefix . str_repeat('0', self::BACKUP_TIMESTAMP_LENGTH))) {
                $prefixes[] = $prefix;
            }
        }

        return $prefixes;
    }

    /**
     * Whether a physical row name belongs to this theme's backups.
     */
    public function isBackupKey(string $name): bool
    {
        return $this->matchBackupPrefix($name, $this->backupPrefixes()) !== null;
    }

    public function lastBackupKey(): ?string
    {
        return $this->lastBackupKey;
    }

    public static function reset(): void
    {
        self::$instance = null;
        ThemeConfig::reset();
    }

    private function assertWritable(): void
    {
        if ($this->themeName === '') {
            throw new RuntimeException('Cannot save theme settings before the active theme is resolved');
        }

        if (!$this->fits($this->key())) {
            throw $this->keyLengthError();
        }
    }

    private function assertKey(string $key): void
    {
        if (
            preg_match('/^[A-Za-z0-9_]{1,100}$/', $key) !== 1
            || str_starts_with($key, '__')
        ) {
            throw new InvalidArgumentException('Invalid option name');
        }
    }

    private function load(): void
    {
        if ($this->loaded) {
            return;
        }

        $raw = $this->themeName === '' ? null : $this->store->get($this->key());
        $decoded = $raw === null ? null : Json::decode($raw);
        $this->data = is_array($decoded) ? $decoded : [];
        $this->loaded = true;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function persist(array $data): void
    {
        $this->assertWritable();
        $encoded = Json::encode($data);
        $current = $this->store->get($this->key());
        $this->lastBackupKey = null;

        if ($current === $encoded) {
            $this->data = $data;
            $this->loaded = true;
            return;
        }

        if ($current !== null && trim($current) !== '') {
            $this->lastBackupKey = $this->writeBackup($current, time());
        }

        $this->store->set($this->key(), $encoded);
        $this->data = $data;
        $this->loaded = true;
    }

    private function writeBackup(string $raw, int $timestamp): string
    {
        $timestamp = max(1, $timestamp);
        $key = $this->backupPrefix() . $timestamp;

        while ($this->store->exists($key)) {
            $timestamp++;
            $key = $this->backupPrefix() . $timestamp;
        }

        $this->store->set($key, $raw);
        return $key;
    }

    private static function createStore(): OptionsStore
    {
        if (!self::usesDedicatedTable()) {
            return new TypechoNativeOptionsStore();
        }

        return new TypechoOptionsStore((string) Config::get('settings.table', 'ttdf'));
    }

    private static function databaseTableFlag(): mixed
    {
        return defined('__TTDF_DATABASE_TABLE__') ? constant('__TTDF_DATABASE_TABLE__') : null;
    }

    private function key(): string
    {
        return $this->store->key($this->themeName);
    }

    /**
     * @param list<string> $prefixes
     */
    private function matchBackupPrefix(string $name, array $prefixes): ?string
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return $prefix;
            }
        }

        return null;
    }

    private function fits(string $name): bool
    {
        return strlen($name) <= $this->store->maxNameLength();
    }

    private function keyLengthError(): RuntimeException
    {
        return new RuntimeException(sprintf(
            'Settings key "%s" exceeds the %d-character storage limit; shorten the theme directory name'
            . ' or remove the __TTDF_DATABASE_TABLE__ override',
            $this->key(),
            $this->store->maxNameLength()
        ));
    }
}
