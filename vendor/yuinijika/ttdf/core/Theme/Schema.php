<?php

declare(strict_types=1);

namespace TTDF\Theme;

final class Schema
{
    /** @var array<string, array<string, mixed>>|null */
    private static ?array $tabs = null;

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $fields = null;

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function tabs(string $root): array
    {
        if (self::$tabs !== null) {
            return self::$tabs;
        }

        $file = $root . '/app/setup.php';
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
    }
}
