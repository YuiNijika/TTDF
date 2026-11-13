<?php

declare(strict_types=1);

namespace TTDF\Hooks;

final class Hooks
{
    /** @var array<string, list<array{priority: int, sequence: int, callback: callable}>> */
    private static array $actions = [];

    /** @var array<string, list<array{priority: int, sequence: int, callback: callable}>> */
    private static array $filters = [];

    private static int $sequence = 0;

    public static function addAction(string $hook, callable $callback, int $priority = 10): void
    {
        self::$actions[$hook][] = self::entry($callback, $priority);
    }

    public static function addFilter(string $hook, callable $callback, int $priority = 10): void
    {
        self::$filters[$hook][] = self::entry($callback, $priority);
    }

    public static function doAction(string $hook, mixed ...$args): void
    {
        foreach (self::sorted(self::$actions[$hook] ?? []) as $entry) {
            ($entry['callback'])(...$args);
        }
    }

    public static function applyFilters(string $hook, mixed $value, mixed ...$args): mixed
    {
        foreach (self::sorted(self::$filters[$hook] ?? []) as $entry) {
            $value = ($entry['callback'])($value, ...$args);
        }

        return $value;
    }

    public static function removeAction(string $hook, callable $callback): void
    {
        self::$actions[$hook] = array_values(array_filter(
            self::$actions[$hook] ?? [],
            static fn (array $entry): bool => $entry['callback'] !== $callback
        ));
    }

    public static function removeFilter(string $hook, callable $callback): void
    {
        self::$filters[$hook] = array_values(array_filter(
            self::$filters[$hook] ?? [],
            static fn (array $entry): bool => $entry['callback'] !== $callback
        ));
    }

    public static function hasAction(string $hook): bool
    {
        return (self::$actions[$hook] ?? []) !== [];
    }

    public static function hasFilter(string $hook): bool
    {
        return (self::$filters[$hook] ?? []) !== [];
    }

    public static function reset(): void
    {
        self::$actions = [];
        self::$filters = [];
        self::$sequence = 0;
    }

    /**
     * @return array{priority: int, sequence: int, callback: callable}
     */
    private static function entry(callable $callback, int $priority): array
    {
        return [
            'priority' => $priority,
            'sequence' => ++self::$sequence,
            'callback' => $callback,
        ];
    }

    /**
     * @param list<array{priority: int, sequence: int, callback: callable}> $entries
     * @return list<array{priority: int, sequence: int, callback: callable}>
     */
    private static function sorted(array $entries): array
    {
        usort($entries, static function (array $left, array $right): int {
            return [$left['priority'], $left['sequence']] <=> [$right['priority'], $right['sequence']];
        });

        return $entries;
    }
}
