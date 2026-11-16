<?php

declare(strict_types=1);

namespace TTDF\Theme;

use RuntimeException;
use Throwable;
use Typecho\Db;

/**
 * Stores theme settings inside Typecho's own `options` table.
 *
 * Enabled only when `__TTDF_DATABASE_TABLE__` is defined as `false`, so a site
 * owner can keep the database free of extra tables. Rows are scoped to
 * `user = 0` and prefixed with `ttdf:` to avoid colliding with core options
 * such as `theme`, `siteUrl` or `theme:{themeName}`.
 *
 * `typecho_options.name` is `varchar(32)`, so callers must keep primary keys
 * short and prefer the compact backup marker when the full one does not fit.
 */
final class TypechoNativeOptionsStore implements OptionsStore
{
    private const PREFIX = 'ttdf:';

    private const MAX_NAME_LENGTH = 32;

    private readonly Db $db;

    public function __construct(?Db $db = null)
    {
        $this->db = $db ?? Db::get();
    }

    public function key(string $name): string
    {
        return self::PREFIX . $name;
    }

    public function maxNameLength(): int
    {
        return self::MAX_NAME_LENGTH;
    }

    public function table(): string
    {
        return 'options';
    }

    public function get(string $name): ?string
    {
        if (!$this->isValidName($name)) {
            return null;
        }

        try {
            $row = @$this->db->fetchRow(
                $this->db->select('value')
                    ->from('table.options')
                    ->where('name = ?', $name)
                    ->where('user = ?', 0)
                    ->limit(1)
            );
        } catch (Throwable) {
            return null;
        }

        return is_array($row) && array_key_exists('value', $row) ? (string) $row['value'] : null;
    }

    public function set(string $name, string $value): void
    {
        if (!$this->isValidName($name)) {
            throw new RuntimeException(
                'Typecho option name must be 1-' . self::MAX_NAME_LENGTH . ' characters'
            );
        }

        if ($this->exists($name)) {
            $this->db->query(
                $this->db->update('table.options')
                    ->rows(['value' => $value])
                    ->where('name = ?', $name)
                    ->where('user = ?', 0)
            );
            return;
        }

        $this->db->query(
            $this->db->insert('table.options')->rows([
                'name' => $name,
                'user' => 0,
                'value' => $value,
            ])
        );
    }

    public function delete(string $name): void
    {
        if (!$this->isValidName($name)) {
            return;
        }

        try {
            @$this->db->query(
                $this->db->delete('table.options')
                    ->where('name = ?', $name)
                    ->where('user = ?', 0)
            );
        } catch (Throwable) {
            // Missing option rows are already absent.
        }
    }

    public function all(): array
    {
        try {
            $rows = @$this->db->fetchAll(
                $this->db->select('name', 'value')
                    ->from('table.options')
                    ->where('user = ?', 0)
            );
        } catch (Throwable) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            if (isset($row['name'])) {
                $result[(string) $row['name']] = (string) ($row['value'] ?? '');
            }
        }

        return $result;
    }

    public function exists(string $name): bool
    {
        return $this->get($name) !== null;
    }

    /**
     * Typecho's options table always exists; there is nothing to install.
     */
    public function install(): void
    {
    }

    /**
     * Reads with an over-long name can never match a row, so they degrade to
     * "no value" instead of failing a front-end request. Writes still throw.
     */
    private function isValidName(string $name): bool
    {
        return $name !== '' && strlen($name) <= self::MAX_NAME_LENGTH;
    }
}
