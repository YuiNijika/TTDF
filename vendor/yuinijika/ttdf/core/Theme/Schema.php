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

    private static bool $loading = false;

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

        self::$loading = true;
        try {
            $tabs = require $file;
        } finally {
            self::$loading = false;
        }

        self::$tabs = is_array($tabs) ? self::normalizeTabs($tabs) : [];

        return self::$tabs;
    }

    /**
     * Keep the top-level tab list backward compatible while exposing nested
     * `tabs` as `sections`. `fields` is flattened so storage, validation and
     * older settings clients keep working without knowing about sub-tabs.
     *
     * @param array<string, mixed> $tabs
     * @return array<string, array<string, mixed>>
     */
    private static function normalizeTabs(array $tabs): array
    {
        $normalized = [];
        $usedTabs = [];

        $index = 0;
        foreach ($tabs as $id => $tab) {
            if (!is_array($tab)) {
                continue;
            }

            $index++;
            $id = (string) $id;
            if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $id) !== 1) {
                $id = 'tab-' . $index;
            }
            $base = substr($id, 0, 60);
            $suffix = 1;
            while (isset($usedTabs[$id])) {
                $suffix++;
                $id = $base . '-' . $suffix;
            }
            $usedTabs[$id] = true;

            $sections = self::normalizeSections($tab['tabs'] ?? $tab['sections'] ?? null);
            $legacyFields = self::normalizeFields($tab['fields'] ?? null);
            $legacyHtml = self::normalizeHtml($tab['html'] ?? null);

            if ($sections === []) {
                $sections[] = [
                    'id' => 'main',
                    'title' => (string) ($tab['title'] ?? $id),
                    'description' => '',
                    'fields' => $legacyFields,
                    'html' => $legacyHtml,
                ];
            } elseif ($legacyFields !== [] || $legacyHtml !== []) {
                array_unshift($sections, [
                    'id' => 'general',
                    'title' => '一般设置',
                    'description' => '',
                    'fields' => $legacyFields,
                    'html' => $legacyHtml,
                ]);
            }

            $fields = [];
            $html = [];
            foreach ($sections as $section) {
                foreach ($section['fields'] as $field) {
                    $fields[] = $field;
                }
                foreach ($section['html'] as $block) {
                    $html[] = $block;
                }
            }

            $tab['id'] = $id;
            $tab['title'] = (string) ($tab['title'] ?? $id);
            $tab['sections'] = $sections;
            $tab['fields'] = $fields;
            $tab['html'] = $html;
            $normalized[$id] = $tab;
        }

        return $normalized;
    }

    /**
     * @param mixed $tabs
     * @return list<array{id: string, title: string, description: string, fields: list<array<string, mixed>>, html: list<array{content: string}>}>
     */
    private static function normalizeSections(mixed $tabs): array
    {
        if (!is_array($tabs)) {
            return [];
        }

        $sections = [];
        $used = [];
        foreach ($tabs as $key => $section) {
            if (!is_array($section)) {
                continue;
            }

            $id = (string) ($section['id'] ?? $section['name'] ?? (is_string($key) ? $key : ''));
            if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $id) !== 1) {
                $id = 'section-' . (count($sections) + 1);
            }
            $base = substr($id, 0, 60);
            $suffix = 1;
            while (isset($used[$id])) {
                $suffix++;
                $id = $base . '-' . $suffix;
            }
            $used[$id] = true;

            $sections[] = [
                'id' => $id,
                'title' => (string) ($section['title'] ?? $id),
                'description' => (string) ($section['description'] ?? ''),
                'fields' => self::normalizeFields($section['fields'] ?? null),
                'html' => self::normalizeHtml($section['html'] ?? null),
            ];
        }

        return $sections;
    }

    /**
     * @param mixed $fields
     * @return list<array<string, mixed>>
     */
    private static function normalizeFields(mixed $fields): array
    {
        if (!is_array($fields)) {
            return [];
        }

        return array_values(array_filter($fields, 'is_array'));
    }

    /**
     * @param mixed $html
     * @return list<array{content: string}>
     */
    private static function normalizeHtml(mixed $html): array
    {
        if (!is_array($html)) {
            return [];
        }

        $blocks = [];
        foreach ($html as $block) {
            if (is_string($block)) {
                $blocks[] = ['content' => $block];
                continue;
            }

            if (is_array($block) && is_string($block['content'] ?? null)) {
                $blocks[] = ['content' => $block['content']];
            }
        }

        return $blocks;
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
        self::$loading = false;
    }

    public static function isLoading(): bool
    {
        return self::$loading;
    }
}
