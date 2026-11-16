<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

/**
 * In-memory stand-in for the Typecho database used by the v2 API tests.
 *
 * Reads are answered from plain arrays and writes mutate those same arrays, so
 * a test can create a row through the API and read the row back from the
 * following response. Only the predicates the v2 repositories build are
 * interpreted; a predicate the fake does not know (`LIKE`, for example) counts
 * as satisfied, which keeps the assertions about real behaviour instead of
 * about the fake's own SQL parser.
 */
final class V2FakeDb
{
    /** @var array<string, string> */
    private const ID_COLUMNS = [
        'table.contents' => 'cid',
        'table.users' => 'uid',
        'table.metas' => 'mid',
        'table.comments' => 'coid',
    ];

    /** @var array<string, list<array<string, mixed>>> */
    private static array $tables = [];

    /** @var array<string, int> */
    private static array $sequences = [];

    /**
     * @param array<string, list<array<string, mixed>>> $tables keyed by the literal
     *     `table.xxx` name used in queries
     */
    public static function install(array $tables): void
    {
        self::$tables = $tables;
        self::$sequences = [];

        \Typecho\Db::$fetcher = static fn (\Typecho\Db\Query $query): array => self::fetch($query);
        \Typecho\Db::$writer = static fn (\Typecho\Db\Query $query): int => self::write($query);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function rows(string $table): array
    {
        return self::$tables[$table] ?? [];
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public static function tables(): array
    {
        return self::$tables;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function row(string $table, int $id): ?array
    {
        $column = self::ID_COLUMNS[$table] ?? null;
        if ($column === null) {
            return null;
        }

        foreach (self::$tables[$table] ?? [] as $row) {
            if ((int) ($row[$column] ?? 0) === $id) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function fetch(\Typecho\Db\Query $query): array
    {
        $matched = [];
        foreach (self::source($query) as $row) {
            if (self::matches($row, $query->wheres)) {
                $matched[] = $row;
            }
        }

        if (self::isCount($query->fields)) {
            return [['num' => count($matched)]];
        }

        $matched = self::sorted($matched, $query->orders);

        if ($query->offset !== null || $query->limit !== null) {
            $matched = array_slice($matched, $query->offset ?? 0, $query->limit);
        }

        return array_values($matched);
    }

    /**
     * Base rows with every joined table folded in.
     *
     * The repositories only ever join through `mid`/`cid`, so an inner-join of
     * the two arrays is enough to answer filter, count and detail reads the
     * same way the SQL would.
     *
     * @return list<array<string, mixed>>
     */
    private static function source(\Typecho\Db\Query $query): array
    {
        $rows = self::$tables[(string) $query->table] ?? [];

        foreach ($query->joins as $join) {
            $rows = self::foldJoin($rows, $join['table'], $join['condition']);
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private static function foldJoin(array $rows, string $table, string $condition): array
    {
        $pattern = '/^\s*([A-Za-z_][A-Za-z0-9_.]*)\.([A-Za-z_][A-Za-z0-9_]*)'
            . '\s*=\s*([A-Za-z_][A-Za-z0-9_.]*)\.([A-Za-z_][A-Za-z0-9_]*)\s*$/';
        if (preg_match($pattern, $condition, $matches) !== 1) {
            return $rows;
        }

        if ($matches[3] === $table) {
            [$baseColumn, $joinedColumn] = [$matches[2], $matches[4]];
        } elseif ($matches[1] === $table) {
            [$baseColumn, $joinedColumn] = [$matches[4], $matches[2]];
        } else {
            return $rows;
        }

        $joinedRows = self::$tables[$table] ?? [];
        $folded = [];
        foreach ($rows as $row) {
            foreach ($joinedRows as $joined) {
                if ((string) ($row[$baseColumn] ?? '') === (string) ($joined[$joinedColumn] ?? '')) {
                    // The base row wins on name clashes so `mid`/`cid` keep
                    // pointing at the row the query started from.
                    $folded[] = $row + $joined;
                }
            }
        }

        return $folded;
    }

    private static function write(\Typecho\Db\Query $query): int
    {
        $table = (string) $query->table;

        return match ($query->mode) {
            'insert' => self::insert($table, $query->rows),
            'update' => self::update($table, $query->rows, $query->wheres),
            'delete' => self::delete($table, $query->wheres),
            default => 0,
        };
    }

    /**
     * @param array<string, mixed> $rows
     */
    private static function insert(string $table, array $rows): int
    {
        $column = self::ID_COLUMNS[$table] ?? null;
        if ($column !== null) {
            $rows[$column] = self::nextId($table, $column);
        }

        self::$tables[$table][] = $rows;

        return $column === null ? 1 : (int) $rows[$column];
    }

    /**
     * @param array<string, mixed> $rows
     * @param list<array{condition: string, values: list<mixed>}> $wheres
     */
    private static function update(string $table, array $rows, array $wheres): int
    {
        $affected = 0;
        foreach (self::$tables[$table] ?? [] as $index => $row) {
            if (!self::matches($row, $wheres)) {
                continue;
            }

            self::$tables[$table][$index] = array_merge($row, $rows);
            $affected++;
        }

        return $affected;
    }

    /**
     * @param list<array{condition: string, values: list<mixed>}> $wheres
     */
    private static function delete(string $table, array $wheres): int
    {
        $kept = [];
        $affected = 0;
        foreach (self::$tables[$table] ?? [] as $row) {
            if (self::matches($row, $wheres)) {
                $affected++;
                continue;
            }

            $kept[] = $row;
        }

        self::$tables[$table] = $kept;

        return $affected;
    }

    private static function nextId(string $table, string $column): int
    {
        if (!isset(self::$sequences[$table])) {
            $max = 0;
            foreach (self::$tables[$table] ?? [] as $row) {
                $max = max($max, (int) ($row[$column] ?? 0));
            }

            self::$sequences[$table] = $max + 1;
        }

        return self::$sequences[$table]++;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<array{field: string, order: string}> $orders
     * @return list<array<string, mixed>>
     */
    private static function sorted(array $rows, array $orders): array
    {
        foreach ($orders as $order) {
            $field = str_contains($order['field'], '.')
                ? substr($order['field'], (int) strrpos($order['field'], '.') + 1)
                : $order['field'];
            $direction = strtoupper($order['order']) === 'ASC' ? 1 : -1;

            usort(
                $rows,
                static fn (array $left, array $right): int => self::comparable($left[$field] ?? null)
                    <=> self::comparable($right[$field] ?? null)
            );

            if ($direction === -1) {
                $rows = array_reverse($rows);
            }
        }

        return $rows;
    }

    private static function comparable(mixed $value): int|string
    {
        return is_numeric($value) ? (int) $value : (string) (is_scalar($value) ? $value : '');
    }

    /**
     * @param list<string> $fields
     */
    private static function isCount(array $fields): bool
    {
        foreach ($fields as $field) {
            if (str_contains(strtoupper($field), 'COUNT(')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<array{condition: string, values: list<mixed>}> $wheres
     */
    private static function matches(array $row, array $wheres): bool
    {
        foreach ($wheres as $where) {
            if (!self::evaluate($row, $where['condition'], $where['values'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<mixed> $values
     */
    private static function evaluate(array $row, string $condition, array $values): bool
    {
        $condition = trim($condition);

        $grouped = str_starts_with($condition, '(') && str_ends_with($condition, ')');
        $body = $grouped ? substr($condition, 1, -1) : $condition;

        $orParts = preg_split('/\s+OR\s+/i', $body) ?: [];
        if (count($orParts) > 1) {
            $offset = 0;
            foreach ($orParts as $part) {
                $count = substr_count($part, '?');
                if (self::evaluate($row, $part, array_slice($values, $offset, $count))) {
                    return true;
                }
                $offset += $count;
            }

            return false;
        }

        $andParts = preg_split('/\s+AND\s+/i', $body) ?: [];
        if (count($andParts) > 1) {
            $offset = 0;
            foreach ($andParts as $part) {
                $count = substr_count($part, '?');
                if (!self::evaluate($row, $part, array_slice($values, $offset, $count))) {
                    return false;
                }
                $offset += $count;
            }

            return true;
        }

        if (preg_match('/^([A-Za-z_][A-Za-z0-9_.]*)\s+NOT\s+IN\s+\?$/i', $body, $matches) === 1) {
            $list = is_array($values[0] ?? null) ? $values[0] : [];

            return !in_array(self::value($row, $matches[1]), array_map(self::flatten(...), $list), true);
        }

        if (preg_match('/^([A-Za-z_][A-Za-z0-9_.]*)\s+IN\s+\?$/i', $body, $matches) === 1) {
            $list = is_array($values[0] ?? null) ? $values[0] : [];

            return in_array(self::value($row, $matches[1]), array_map(self::flatten(...), $list), true);
        }

        if (preg_match('/^([A-Za-z_][A-Za-z0-9_.]*)\s*<>\s*\?$/', $body, $matches) === 1) {
            return !self::same(self::value($row, $matches[1]), $values[0] ?? null);
        }

        if (preg_match('/^([A-Za-z_][A-Za-z0-9_.]*)\s*=\s*\?$/', $body, $matches) === 1) {
            return self::same(self::value($row, $matches[1]), $values[0] ?? null);
        }

        return true;
    }

    private static function flatten(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private static function same(string $actual, mixed $expected): bool
    {
        return is_scalar($expected) && $actual === (string) $expected;
    }

    private static function value(array $row, string $column): string
    {
        $column = str_contains($column, '.') ? substr($column, (int) strrpos($column, '.') + 1) : $column;

        return is_scalar($row[$column] ?? null) ? (string) $row[$column] : '';
    }
}
