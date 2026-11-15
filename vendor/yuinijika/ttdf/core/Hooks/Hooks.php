<?php

declare(strict_types=1);

namespace TTDF\Hooks;

final class Hooks
{
    /**
     * v4 hook names remain valid aliases. They are resolved to the v5 hook
     * group, so callbacks registered on either name run exactly once.
     *
     * @var array<string, string>
     */
    private const LEGACY_ALIASES = [
        'load_head' => 'theme.head',
        'load_foot' => 'theme.footer',
        'header' => 'typecho.archive.header',
        'footer' => 'typecho.archive.footer',
        'before_render' => 'typecho.archive.before_render',
        'after_render' => 'typecho.archive.after_render',
    ];

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
        $entries = [];
        foreach (self::group($hook) as $name) {
            foreach (self::$actions[$name] ?? [] as $entry) {
                $entries[] = $entry;
            }
        }

        foreach (self::sorted($entries) as $entry) {
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
        foreach (self::group($hook) as $name) {
            self::$actions[$name] = array_values(array_filter(
                self::$actions[$name] ?? [],
                static fn (array $entry): bool => $entry['callback'] !== $callback
            ));
        }
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
        foreach (self::group($hook) as $name) {
            if ((self::$actions[$name] ?? []) !== []) {
                return true;
            }
        }

        return false;
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
     * @return list<string>
     */
    private static function group(string $hook): array
    {
        $canonical = self::LEGACY_ALIASES[$hook] ?? $hook;
        $names = [$canonical];

        foreach (self::LEGACY_ALIASES as $alias => $target) {
            if ($target === $canonical) {
                $names[] = $alias;
            }
        }

        return array_values(array_unique($names));
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
