<?php

declare(strict_types=1);

namespace TTDF\Theme;

use InvalidArgumentException;
use Throwable;
use Typecho\Db;

final class TypechoOptionsStore implements OptionsStore
{
    private readonly Db $db;

    public function __construct(
        private readonly string $table = 'ttdf',
        ?Db $db = null
    ) {
        if (preg_match('/^[A-Za-z0-9_]{1,64}$/', $table) !== 1) {
            throw new InvalidArgumentException('Invalid settings table name');
        }

        $this->db = $db ?? Db::get();
    }

    public function get(string $name): ?string
    {
        try {
            $row = @$this->db->fetchRow(
                $this->db->select('value')->from($this->table())->where('name = ?', $name)
            );
        } catch (Throwable) {
            return null;
        }

        return is_array($row) && array_key_exists('value', $row) ? (string) $row['value'] : null;
    }

    public function set(string $name, string $value): void
    {
        if ($this->exists($name)) {
            $this->db->query(
                $this->db->update($this->table())->rows(['value' => $value])->where('name = ?', $name)
            );
            return;
        }

        $this->db->query($this->db->insert($this->table())->rows([
            'name' => $name,
            'value' => $value,
        ]));
    }

    public function delete(string $name): void
    {
        try {
            @$this->db->query($this->db->delete($this->table())->where('name = ?', $name));
        } catch (Throwable) {
            // Deleting from a missing table is a no-op.
        }
    }

    public function all(): array
    {
        try {
            $rows = @$this->db->fetchAll($this->db->select()->from($this->table()));
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

    public function install(): void
    {
        $prefix = $this->db->getPrefix();
        $table = $prefix . $this->table;
        $adapter = strtolower($this->db->getAdapterName());

        if (str_contains($adapter, 'sqlite')) {
            $sql = sprintf(
                'CREATE TABLE IF NOT EXISTS "%s" ("tid" INTEGER PRIMARY KEY AUTOINCREMENT, "name" VARCHAR(200) NOT NULL UNIQUE, "value" TEXT)',
                $table
            );
        } elseif (str_contains($adapter, 'pgsql') || str_contains($adapter, 'postgres')) {
            $sql = sprintf(
                'CREATE TABLE IF NOT EXISTS "%s" ("tid" SERIAL PRIMARY KEY, "name" VARCHAR(200) NOT NULL UNIQUE, "value" TEXT)',
                $table
            );
        } else {
            $sql = sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` (`tid` int(10) unsigned NOT NULL AUTO_INCREMENT, `name` varchar(200) NOT NULL, `value` text, PRIMARY KEY (`tid`), UNIQUE KEY `name` (`name`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                $table
            );
        }

        $this->db->query($sql);

        if ($this->get('__schema_version') === null) {
            $this->db->query($this->db->insert($this->table())->rows([
                'name' => '__schema_version',
                'value' => '1',
            ]));
        }
    }

    public function schemaVersion(): ?int
    {
        $value = $this->get('__schema_version');
        return $value !== null && ctype_digit($value) ? (int) $value : null;
    }

    private function table(): string
    {
        return 'table.' . $this->table;
    }
}
