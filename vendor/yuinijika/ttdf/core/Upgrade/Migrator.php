<?php

declare(strict_types=1);

namespace TTDF\Upgrade;

use TTDF\Compat\Typecho;
use TTDF\Config\Config;
use TTDF\Support\Json;
use TTDF\Theme\Options;
use TTDF\Theme\Schema;
use TTDF\Theme\Settings;

final class Migrator
{
    public function __construct(
        private readonly Options $options,
        private readonly string $root
    ) {
    }

    /**
     * @return array{status: string, migrated: int, schemaVersion: int, backup: string|null}
     */
    public function run(): array
    {
        $this->options->ensureInstalled();
        $current = $this->options->schemaVersion() ?? 0;
        $target = max(1, (int) Config::get('settings.schema_version', 2));
        if ($current >= $target) {
            return [
                'status' => 'already-current',
                'migrated' => 0,
                'schemaVersion' => $current,
                'backup' => null,
            ];
        }

        $backup = $this->backup();
        $fields = Schema::fields($this->root);
        $theme = Typecho::themeName();
        $prefix = $theme !== '' ? $theme . '_' : '';
        $migrated = 0;

        $entries = [];
        foreach ($this->options->store()->all() as $name => $raw) {
            if (str_starts_with($name, '__schema_version')) {
                continue;
            }

            if ($prefix !== '' && str_starts_with($name, $prefix)) {
                $fieldName = substr($name, strlen($prefix));
                if (!isset($fields[$fieldName])) {
                    continue;
                }
                $targetName = $name;
                $priority = 2;
            } elseif (isset($fields[$name])) {
                $fieldName = $name;
                $targetName = $prefix . $name;
                $priority = 1;
            } else {
                continue;
            }

            if (!isset($entries[$fieldName]) || $priority > $entries[$fieldName]['priority']) {
                $entries[$fieldName] = [
                    'target' => $targetName,
                    'raw' => $raw,
                    'priority' => $priority,
                ];
            }
        }

        foreach ($entries as $fieldName => $entry) {
            $value = Settings::normalizeValue($fields[$fieldName], $this->decode($entry['raw']));
            $this->options->store()->set($entry['target'], Json::encode($value));
            $migrated++;
        }

        $this->options->setSchemaVersion($target);
        Options::reset();

        return [
            'status' => 'migrated',
            'migrated' => $migrated,
            'schemaVersion' => $target,
            'backup' => $backup,
        ];
    }

    private function backup(): ?string
    {
        $directory = $this->root . '/logs';
        if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
            return null;
        }

        $file = $directory . '/ttdf-migration-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.json';
        $payload = Json::encode($this->backupRows(), true);
        if (@file_put_contents($file, $payload, LOCK_EX) === false) {
            return null;
        }

        @chmod($file, 0640);
        return $file;
    }

    /**
     * Back up only rows that belong to the current theme or schema.
     *
     * @return array<string, string>
     */
    private function backupRows(): array
    {
        $fields = Schema::fields($this->root);
        $theme = Typecho::themeName();
        $prefix = $theme !== '' ? $theme . '_' : '';
        $rows = [];

        foreach ($this->options->store()->all() as $name => $raw) {
            if (str_starts_with($name, '__schema_version')) {
                continue;
            }

            if ($prefix !== '' && str_starts_with($name, $prefix)) {
                $fieldName = substr($name, strlen($prefix));
                if (isset($fields[$fieldName])) {
                    $rows[$name] = $raw;
                }
            } elseif (isset($fields[$name])) {
                $rows[$name] = $raw;
            }
        }

        return $rows;
    }

    private function decode(string $raw): mixed
    {
        try {
            return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $raw;
        }
    }

}
