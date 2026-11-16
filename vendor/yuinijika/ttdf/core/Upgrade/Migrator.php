<?php

declare(strict_types=1);

namespace TTDF\Upgrade;

use JsonException;
use TTDF\Compat\Typecho;
use TTDF\Theme\Options;
use TTDF\Theme\Schema;
use TTDF\Theme\Settings;

/**
 * Converts legacy per-field settings rows into one JSON document.
 *
 * The migration is one-way: it only runs when the active theme has no JSON
 * document yet. Once the JSON document exists it is treated as the new format
 * and migration becomes a no-op. Legacy rows are never deleted.
 *
 * Rows are matched by the current field name first and by a declared
 * `aliases` entry second, so a renamed field can still pick up its old value.
 */
final class Migrator
{
    private const PRIORITY_THEME_FIELD = 4;
    private const PRIORITY_GLOBAL_FIELD = 3;
    private const PRIORITY_THEME_ALIAS = 2;
    private const PRIORITY_GLOBAL_ALIAS = 1;

    public function __construct(
        private readonly Options $options,
        private readonly string $root
    ) {
    }

    /**
     * @return array{status: string, migrated: int, backup: string|null}
     */
    public function run(): array
    {
        $this->options->ensureInstalled();
        $theme = Typecho::themeName();
        if ($theme === '') {
            return [
                'status' => 'already-current',
                'migrated' => 0,
                'backup' => null,
            ];
        }

        if ($this->options->exists()) {
            return [
                'status' => 'already-current',
                'migrated' => 0,
                'backup' => null,
            ];
        }

        $fields = Schema::fields($this->root);
        $aliases = Schema::aliases($this->root);
        $prefix = $theme . '_';
        $entries = [];

        foreach ($this->options->store()->all() as $name => $raw) {
            if (
                $name === $theme
                || $this->options->isBackupKey($name)
                || str_starts_with($name, '__schema_version')
            ) {
                continue;
            }

            $scoped = str_starts_with($name, $prefix);
            $candidate = $scoped ? substr($name, strlen($prefix)) : $name;

            if (isset($fields[$candidate])) {
                $fieldName = $candidate;
                $priority = $scoped ? self::PRIORITY_THEME_FIELD : self::PRIORITY_GLOBAL_FIELD;
            } elseif (isset($aliases[$candidate])) {
                $fieldName = $aliases[$candidate];
                $priority = $scoped ? self::PRIORITY_THEME_ALIAS : self::PRIORITY_GLOBAL_ALIAS;
            } else {
                continue;
            }

            if (!isset($entries[$fieldName]) || $priority > $entries[$fieldName]['priority']) {
                $entries[$fieldName] = [
                    'raw' => $raw,
                    'priority' => $priority,
                ];
            }
        }

        if ($entries === []) {
            return [
                'status' => 'already-current',
                'migrated' => 0,
                'backup' => null,
            ];
        }

        $values = [];
        foreach ($entries as $fieldName => $entry) {
            $values[$fieldName] = Settings::normalizeValue(
                $fields[$fieldName],
                $this->decode($entry['raw'])
            );
        }

        $this->options->merge($values);

        return [
            'status' => 'migrated',
            'migrated' => count($values),
            'backup' => $this->options->lastBackupKey(),
        ];
    }

    private function decode(string $raw): mixed
    {
        try {
            return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $raw;
        }
    }
}
