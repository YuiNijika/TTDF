<?php

declare(strict_types=1);

namespace TTDF\Theme;

use TTDF\Compat\Typecho;
use TTDF\Config\Config;
use TTDF\Framework;
use TTDF\Support\Escaper;
use TTDF\Upgrade\Migrator;

final class Settings
{
    /**
     * Typecho calls this only from the admin theme-options screen.
     *
     * @param object $form
     */
    public static function config(object $form): void
    {
        $options = Options::instance();
        try {
            $options->ensureInstalled();
            self::migrateLegacyValues($options);
            $targetVersion = max(1, (int) Config::get('settings.schema_version', 2));
            if (($options->schemaVersion() ?? 0) < $targetVersion) {
                (new Migrator($options, self::root()))->run();
            }
        } catch (\Throwable $exception) {
            error_log('TTDF settings table initialization failed: ' . $exception->getMessage());
        }

        $payload = [
            'apiUrl' => rtrim(Typecho::siteUrl(), '/') . '/' . trim((string) Config::get('rest.prefix', 'ttdf/v1'), '/') . '/admin',
            'nonce' => Typecho::securityToken(),
            'themeName' => Typecho::themeName(),
            'themeVersion' => (string) Config::get('version', ''),
            'ttdfVersion' => Framework::VERSION,
        ];

        echo '<div id="options-app"></div>' . PHP_EOL;
        echo '<script>window.ttdfOptionsInfo = ' . Escaper::js($payload) . ';</script>' . PHP_EOL;

        Assets::printStaticStyles([
            'element-plus/index.css',
            'options/options.css',
        ]);

        Assets::printStaticScripts([
            'vue/vue.global.prod.js',
            'element-plus/index.full.min.js',
            'options/options.js',
        ]);
    }

    /**
     * @param object $layout
     */
    public static function fields(object $layout): void
    {
        $file = Paths::themeFields(self::root());
        if (!is_file($file)) {
            return;
        }

        $fields = require $file;
        if (!is_array($fields)) {
            return;
        }

        Fields::addToLayout($layout, $fields);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function tabs(): array
    {
        return Schema::tabs(self::root());
    }

    /**
     * @return array<string, mixed>
     */
    public static function values(): array
    {
        $options = Options::instance();
        $values = [];

        foreach (Schema::fields(self::root()) as $name => $field) {
            $default = $field['value'] ?? '';
            $values[$name] = $options->get($name, $default);
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function validate(array $input): array
    {
        $fields = Schema::fields(self::root());
        $result = [];

        foreach ($fields as $name => $field) {
            if (!array_key_exists($name, $input)) {
                continue;
            }

            $type = (string) ($field['type'] ?? 'Text');
            $value = $input[$name];

            if ($type === 'TemplateMap') {
                $value = Templates::normalizeMap(is_array($value) ? $value : []);
            } elseif (in_array($type, ['Checkbox', 'Tags', 'AddList', 'Cascader', 'Transfer'], true)) {
                if (is_string($value)) {
                    $value = array_values(array_filter(array_map('trim', explode(',', $value))));
                }
                if (!is_array($value)) {
                    $value = [];
                }
            } elseif ($type === 'Switch') {
                $value = is_bool($value)
                    ? $value
                    : (is_scalar($value)
                        && in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true));
            } elseif (in_array($type, ['Number', 'Slider'], true)) {
                $value = is_numeric($value) ? (float) $value : 0;
            } else {
                $value = is_scalar($value) ? (string) $value : '';
            }

            $result[$name] = $value;
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public static function export(): array
    {
        return [
            'meta' => [
                'theme' => Typecho::themeName(),
                'frameworkVersion' => Framework::VERSION,
                'exportedAt' => date('c'),
            ],
            'settings' => self::values(),
        ];
    }

    /**
     * @param array<string, mixed> $settings
     */
    public static function import(array $settings): int
    {
        $validated = self::validate($settings);
        $options = Options::instance();
        foreach ($validated as $name => $value) {
            $options->set($name, $value);
        }

        return count($validated);
    }

    /**
     * @return array<string, mixed>
     */
    public static function schema(): array
    {
        return [
            'tabs' => self::tabs(),
            'fields' => Schema::fields(self::root()),
            'templateSlots' => Templates::SLOTS,
            'components' => Templates::available(self::root()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function formData(): array
    {
        return self::values();
    }

    /**
     * @return array<string, mixed>
     */
    public static function themeInfo(): array
    {
        return [
            'themeName' => Typecho::themeName(),
            'themeVersion' => (string) Config::get('version', ''),
            'ttdfVersion' => Framework::VERSION,
            'typechoVersion' => Typecho::version(),
            'components' => Templates::available(self::root()),
            'templateSlots' => Templates::SLOTS,
        ];
    }

    private static function root(): string
    {
        $root = Framework::root();

        return $root !== '' ? $root : dirname(__DIR__, 5);
    }

    private static function migrateLegacyValues(Options $options): void
    {
        $typecho = Typecho::options();
        if ($typecho === null) {
            return;
        }

        foreach (Schema::fields(self::root()) as $name => $field) {
            if ($options->get($name, null) !== null) {
                continue;
            }

            $legacy = property_exists($typecho, $name) || method_exists($typecho, '__get')
                ? $typecho->{$name}
                : null;
            if (is_array($legacy) || is_scalar($legacy)) {
                $options->set($name, $legacy);
            }
        }
    }
}
