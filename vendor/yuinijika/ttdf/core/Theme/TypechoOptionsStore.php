<?php

declare(strict_types=1);

namespace TTDF\Theme;

use InvalidArgumentException;
use Throwable;
use Typecho\Db;

final class TypechoOptionsStore implements OptionsStore
{
    private const MAX_NAME_LENGTH = 200;

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

    public function key(string $name): string
    {
        return $name;
    }

    public function maxNameLength(): int
    {
        return self::MAX_NAME_LENGTH;
    }

    public function get(string $name): ?string
    {
        try {
            $row = @$this->db->fetchRow(
                $this->db->select('value')
                    ->from($this->tableName())
                    ->where('name = ?', $name)
                    ->limit(1)
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
                $this->db->update($this->tableName())
                    ->rows(['value' => $value])
                    ->where('name = ?', $name)
            );
            return;
        }

        $this->db->query(
            $this->db->insert($this->tableName())->rows([
                'name' => $name,
                'value' => $value,
            ])
        );
    }

    public function delete(string $name): void
    {
        try {
            @$this->db->query(
                $this->db->delete($this->tableName())
                    ->where('name = ?', $name)
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
                    ->from($this->tableName())
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

    public function install(): void
    {
        $table = $this->db->getPrefix() . $this->table;
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
    }

    public function table(): string
    {
        return $this->table;
    }

    private function tableName(): string
    {
        return 'table.' . $this->table;
    }
}
