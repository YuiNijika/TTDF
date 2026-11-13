<?php

declare(strict_types=1);

namespace TTDF\Upgrade;

use TTDF\Compat\Typecho;
use TTDF\Support\Json;
use TTDF\Theme\Options;
use TTDF\Theme\Schema;

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
        if ($current >= 2) {
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

        foreach ($this->options->store()->all() as $name => $raw) {
            if ($name === '__schema_version') {
                continue;
            }

            if ($prefix !== '' && str_starts_with($name, $prefix)) {
                $fieldName = substr($name, strlen($prefix));
            } elseif (isset($fields[$name])) {
                $fieldName = $name;
            } else {
                continue;
            }

            $value = $this->decode($raw);
            if (isset($fields[$fieldName])) {
                $value = $this->coerce($fields[$fieldName], $value);
            }

            $this->options->store()->set($name, Json::encode($value));
            $migrated++;
        }

        $this->options->store()->set('__schema_version', '2');
        Options::reset();

        return [
            'status' => 'migrated',
            'migrated' => $migrated,
            'schemaVersion' => 2,
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
            if ($name === '__schema_version') {
                continue;
            }

            if ($prefix !== '' && str_starts_with($name, $prefix)) {
                $rows[$name] = $raw;
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

    /**
     * @param array<string, mixed> $field
     */
    private function coerce(array $field, mixed $value): mixed
    {
        $type = (string) ($field['type'] ?? 'Text');

        if (in_array($type, ['Checkbox', 'Tags', 'AddList', 'Cascader', 'Transfer'], true)) {
            if (is_string($value)) {
                return array_values(array_filter(array_map('trim', explode(',', $value))));
            }

            return is_array($value) ? array_values($value) : [];
        }

        if ($type === 'Switch') {
            return is_bool($value)
                ? $value
                : (is_scalar($value)
                    && in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true));
        }

        if (in_array($type, ['Number', 'Slider'], true)) {
            return is_numeric($value) ? $value + 0 : 0;
        }

        return is_scalar($value) ? (string) $value : '';
    }
}
