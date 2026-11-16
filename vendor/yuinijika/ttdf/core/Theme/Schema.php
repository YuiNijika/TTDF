<?php

declare(strict_types=1);

namespace TTDF\Theme;

final class Schema
{
    /** @var array<string, array<string, mixed>>|null */
    private static ?array $tabs = null;

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $fields = null;

    /** @var array<string, string>|null */
    private static ?array $aliases = null;

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function tabs(string $root): array
    {
        if (self::$tabs !== null) {
            return self::$tabs;
        }

        $file = Paths::settings($root);
        if (!is_file($file)) {
            return self::$tabs = [];
        }

        $tabs = require $file;
        self::$tabs = is_array($tabs) ? $tabs : [];

        return self::$tabs;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function fields(string $root): array
    {
        if (self::$fields !== null) {
            return self::$fields;
        }

        $fields = [];
        foreach (self::tabs($root) as $tab) {
            foreach (($tab['fields'] ?? []) as $field) {
                if (!is_array($field) || !isset($field['name']) || !is_string($field['name'])) {
                    continue;
                }
                if (preg_match('/^[A-Za-z0-9_]{1,100}$/', $field['name']) !== 1) {
                    continue;
                }
                if (($field['type'] ?? 'Text') === 'Html') {
                    continue;
                }
                $fields[$field['name']] = $field;
            }
        }

        return self::$fields = $fields;
    }

    /**
     * @return list<string>
     */
    public static function names(string $root): array
    {
        return array_keys(self::fields($root));
    }

    /**
     * Map legacy field names to the current canonical field name.
     *
     * A field may declare `'aliases' => ['logoUrl']` when it was renamed
     * between theme versions. Aliases are migration-only: they are never
     * stored, they only let {@see \TTDF\Upgrade\Migrator} read old rows.
     *
     * @return array<string, string>
     */
    public static function aliases(string $root): array
    {
        if (self::$aliases !== null) {
            return self::$aliases;
        }

        $fields = self::fields($root);
        $aliases = [];

        foreach ($fields as $name => $field) {
            $declared = $field['aliases'] ?? [];
            if (!is_array($declared)) {
                continue;
            }

            foreach ($declared as $alias) {
                if (!is_string($alias) || preg_match('/^[A-Za-z0-9_]{1,100}$/', $alias) !== 1) {
                    continue;
                }
                if ($alias === $name || isset($fields[$alias]) || isset($aliases[$alias])) {
                    continue;
                }

                $aliases[$alias] = $name;
            }
        }

        return self::$aliases = $aliases;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function field(string $root, string $name): ?array
    {
        return self::fields($root)[$name] ?? null;
    }

    public static function reset(): void
    {
        self::$tabs = null;
        self::$fields = null;
        self::$aliases = null;
    }
}
