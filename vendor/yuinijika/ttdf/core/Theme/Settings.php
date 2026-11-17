<?php

declare(strict_types=1);

namespace TTDF\Theme;

use TTDF\Compat\Typecho;
use TTDF\Framework;
use TTDF\Http\Rest\Prefixes;
use TTDF\Support\Color;
use TTDF\Support\Escaper;

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
        $initializationError = '';
        try {
            $options->ensureInstalled();
        } catch (\Throwable $exception) {
            error_log('TTDF settings storage initialization failed: ' . $exception->getMessage());
            $initializationError = '主题设置存储初始化失败，请检查数据库权限或设置表结构。';
        }

        $indexUrl = Typecho::indexUrl();
        if ($indexUrl === '') {
            $indexUrl = Typecho::siteUrl();
        }

        $payload = [
            'apiUrl' => rtrim($indexUrl, '/')
                . '/'
                . Prefixes::v1()
                . '/admin',
            'nonce' => Typecho::securityToken(),
            'themeName' => Typecho::themeName(),
            'themeVersion' => Typecho::themeVersion(),
            'ttdfVersion' => Framework::VERSION,
            'initError' => $initializationError,
        ];

        echo '<div id="options-app"></div>' . PHP_EOL;
        echo '<script>window.ttdfOptionsInfo = ' . Escaper::js($payload) . ';</script>' . PHP_EOL;

        Assets::printStaticStyles([
            'arco/index.css',
            'options/options.css',
        ]);

        Assets::printStaticScripts([
            'vue/vue.global.prod.js',
            'arco/arco-vue.min.js',
            'arco/arco-vue-icon.min.js',
            'options/options.js',
        ]);
    }

    /**
     * @param object $layout
     */
    public static function fields(object $layout): void
    {
        $fields = Fields::declarations(self::root());
        if ($fields !== []) {
            Fields::addToLayout($layout, $fields);
        }
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
            $stored = $options->get($name, null);
            $value = $stored === null ? ($field['value'] ?? '') : $stored;
            $values[$name] = self::normalizeValue($field, $value);
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

            $result[$name] = self::normalizeValue($field, $input[$name]);
        }

        return $result;
    }

    /**
     * Resolve how a field stores its media value.
     *
     * `type => 'Media'` is a dedicated picker and stores the attachment cid,
     * while `Text` / `Textarea` keep their editable control and store the
     * media URL the library wrote into it.
     *
     * @param array<string, mixed> $field
     * @return 'reference'|'url'|null
     */
    public static function mediaMode(array $field): ?string
    {
        $type = (string) ($field['type'] ?? 'Text');

        if ($type === 'Media') {
            return 'reference';
        }

        if (!in_array($type, ['Text', 'Textarea'], true)) {
            return null;
        }

        $media = $field['media'] ?? false;
        if (is_bool($media)) {
            return $media ? 'url' : null;
        }

        if (is_array($media)) {
            return ($media['enabled'] ?? true) !== false ? 'url' : null;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $field
     */
    public static function isMediaField(array $field): bool
    {
        return self::mediaMode($field) !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function export(): array
    {
        return [
            'meta' => [
                'theme' => Typecho::themeName(),
                'themeVersion' => Typecho::themeVersion(),
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
        self::save($validated);

        return count($validated);
    }

    /**
     * Save one validated JSON document for the active theme.
     *
     * @param array<string, mixed> $input
     */
    public static function save(array $input): int
    {
        $validated = self::validate($input);
        if ($validated === []) {
            return 0;
        }

        $options = Options::instance();
        $options->ensureInstalled();
        $options->merge($validated);

        return count($validated);
    }

    public static function backup(): ?string
    {
        $options = Options::instance();
        $options->ensureInstalled();

        return $options->backup();
    }

    /**
     * @return list<array{key: string, timestamp: int, count: int}>
     */
    public static function backups(): array
    {
        return array_map(
            static fn (array $backup): array => [
                'key' => $backup['key'],
                'timestamp' => $backup['timestamp'],
                'count' => count($backup['settings']),
            ],
            Options::instance()->backups()
        );
    }

    public static function restore(string $key): bool
    {
        return Options::instance()->restore($key);
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
            'categories' => self::categoryOptions(),
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
            'themeVersion' => Typecho::themeVersion(),
            'ttdfVersion' => Framework::VERSION,
            'typechoVersion' => Typecho::version(),
            'components' => Templates::available(self::root()),
            'templateSlots' => Templates::SLOTS,
        ];
    }

    /**
     * Category choices for the settings UI. The theme config stays declarative:
     * a `Category` field is hydrated from Typecho at request time instead of
     * storing a stale category list in PHP.
     *
     * @return array<string, string>
     */
    public static function categoryOptions(): array
    {
        if (!class_exists('Widget\\Metas\\Category\\Rows')) {
            return [];
        }

        $items = [];
        try {
            $rows = \Widget\Metas\Category\Rows::alloc();
            while ($rows->next()) {
                $slug = trim((string) ($rows->slug ?? ''));
                $name = trim((string) ($rows->name ?? ''));
                if ($slug === '' || $name === '') {
                    continue;
                }
                $items[$slug] = $name;
            }
        } catch (\Throwable $exception) {
            error_log('TTDF settings category lookup failed: ' . $exception->getMessage());
        }

        return $items;
    }

    private static function root(): string
    {
        $root = Framework::root();

        return $root !== '' ? $root : dirname(__DIR__, 5);
    }

    /**
     * Normalize a page-block list.
     *
     * The canonical value is an ordered list of `{name, variant?, data?}`
     * objects. The legacy `name` / `name:variant` string form is still
     * accepted so themes can upgrade without rewriting stored settings.
     * Identical entries are collapsed; the same block may appear more than
     * once as long as its variant or custom data differs.
     *
     * @param array<string, mixed> $field
     * @return list<array<string, mixed>>
     */
    private static function normalizeBlocks(mixed $value, array $field): array
    {
        $items = is_string($value)
            ? preg_split('/[\r\n,]+/', $value)
            : (is_array($value) ? $value : []);

        if (!is_array($items)) {
            return [];
        }

        $catalog = self::blockCatalog($field);
        $blocks = [];
        $seen = [];

        foreach ($items as $item) {
            $block = self::normalizeBlockEntry($item, $catalog);
            if ($block === null) {
                continue;
            }

            $fingerprint = json_encode($block, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (is_string($fingerprint)) {
                if (isset($seen[$fingerprint])) {
                    continue;
                }

                $seen[$fingerprint] = true;
            }

            $blocks[] = $block;
        }

        return $blocks;
    }

    /**
     * Declared blocks for one Blocks field, keyed by block name.
     *
     * @param array<string, mixed> $field
     * @return array<string, array<string, mixed>>
     */
    private static function blockCatalog(array $field): array
    {
        $options = $field['options'] ?? [];
        if (!is_array($options)) {
            return [];
        }

        $catalog = [];
        foreach ($options as $name => $definition) {
            $name = (string) $name;
            if (preg_match('/^[a-z][a-z0-9-]*$/', $name) !== 1) {
                continue;
            }

            $catalog[$name] = is_array($definition) ? $definition : ['label' => (string) $definition];
        }

        return $catalog;
    }

    /**
     * @param array<string, array<string, mixed>> $catalog
     * @return array<string, mixed>|null
     */
    private static function normalizeBlockEntry(mixed $item, array $catalog): ?array
    {
        $values = [];

        if (is_string($item)) {
            $parts = explode(':', trim($item), 2);
            $name = trim($parts[0]);
            $variant = isset($parts[1]) ? trim($parts[1]) : '';
        } elseif (is_array($item)) {
            $name = trim((string) ($item['name'] ?? ($item['block'] ?? '')));
            $variant = trim((string) ($item['variant'] ?? ''));
            $values = isset($item['data']) && is_array($item['data']) ? $item['data'] : [];
        } else {
            return null;
        }

        if (preg_match('/^[a-z][a-z0-9-]*$/', $name) !== 1) {
            return null;
        }

        if ($variant !== '' && preg_match('/^[a-z][a-z0-9-]*$/', $variant) !== 1) {
            return null;
        }

        $entry = ['name' => $name];
        if ($variant !== '') {
            $entry['variant'] = $variant;
        }

        $data = self::normalizeBlockData($catalog[$name]['fields'] ?? [], $values);
        if ($data !== []) {
            $entry['data'] = $data;
        }

        return $entry;
    }

    /**
     * Keep only declared custom-data keys and normalize each value with the
     * same rules as a top-level theme field.
     *
     * @param mixed $declared
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private static function normalizeBlockData(mixed $declared, array $values): array
    {
        if (!is_array($declared) || $values === []) {
            return [];
        }

        $data = [];
        $used = [];

        foreach ($declared as $index => $field) {
            if (!is_array($field)) {
                continue;
            }

            $name = (string) ($field['name'] ?? ('field-' . $index));
            if (preg_match('/^[A-Za-z0-9_]{1,100}$/', $name) !== 1 || isset($used[$name])) {
                continue;
            }
            $used[$name] = true;

            if (!array_key_exists($name, $values)) {
                continue;
            }

            $type = (string) ($field['type'] ?? 'Text');
            if (in_array($type, ['Blocks', 'Html'], true)) {
                continue;
            }

            $normalized = self::normalizeValue($field, $values[$name]);
            if (is_string($normalized)) {
                $normalized = trim($normalized);
            }
            if ($normalized === '' || $normalized === [] || $normalized === null) {
                continue;
            }

            $data[$name] = $normalized;
        }

        return $data;
    }

    /**
     * Normalize one schema value for both persisted reads and writes.
     *
     * @param array<string, mixed> $field
     */
    public static function normalizeValue(array $field, mixed $value): mixed
    {
        $type = (string) ($field['type'] ?? 'Text');
        $mediaMode = self::mediaMode($field);

        if ($mediaMode === 'reference') {
            $value = is_scalar($value) ? trim((string) $value) : '';

            return ctype_digit($value) && (int) $value > 0 ? $value : '';
        }

        if ($mediaMode === 'url') {
            return $type === 'Textarea'
                ? Media::normalizeUrlList($value)
                : Media::normalizeUrl($value);
        }

        if ($type === 'TemplateMap') {
            return Templates::filterMap(
                Templates::normalizeMap(is_array($value) ? $value : []),
                Templates::allowedComponents($field)
            );
        }

        if ($type === 'Blocks') {
            return self::normalizeBlocks($value, $field);
        }

        if ($type === 'Nav') {
            return self::normalizeNav($value);
        }

        if ($type === 'Slides') {
            return self::normalizeSlides($value);
        }

        if (in_array($type, ['Checkbox', 'Tags', 'AddList', 'Cascader', 'Transfer'], true)) {
            if (is_string($value)) {
                return array_values(array_filter(array_map('trim', explode(',', $value))));
            }

            return is_array($value) ? $value : [];
        }

        if ($type === 'Switch') {
            return is_bool($value)
                ? $value
                : (is_scalar($value)
                    && in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true));
        }

        if ($type === 'ColorPicker') {
            return Color::normalize($value);
        }

        if (in_array($type, ['Number', 'Slider'], true)) {
            if (!is_numeric($value)) {
                return 0;
            }

            if (is_int($value) || (is_string($value) && preg_match('/^[+-]?\d+$/', trim($value)) === 1)) {
                return (int) $value;
            }

            return (float) $value;
        }

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @return list<array{label: string, url: string, children: list<array{label: string, url: string}>}>
     */
    private static function normalizeNav(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach (array_slice($value, 0, 12) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $label = trim((string) ($item['label'] ?? ''));
            $url = self::navUrl($item['url'] ?? '');
            if ($label === '' || $url === '') {
                continue;
            }

            $children = [];
            $rawChildren = isset($item['children']) && is_array($item['children'])
                ? $item['children']
                : [];
            foreach (array_slice($rawChildren, 0, 8) as $child) {
                if (!is_array($child)) {
                    continue;
                }

                $childLabel = trim((string) ($child['label'] ?? ''));
                $childUrl = self::navUrl($child['url'] ?? '');
                if ($childLabel === '' || $childUrl === '') {
                    continue;
                }

                $children[] = ['label' => $childLabel, 'url' => $childUrl];
            }

            $items[] = ['label' => $label, 'url' => $url, 'children' => $children];
        }

        return $items;
    }

    /**
     * @return list<array{image: string, title: string, description: string, url: string}>
     */
    private static function normalizeSlides(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $slides = [];
        foreach (array_slice($value, 0, 12) as $slide) {
            if (!is_array($slide)) {
                continue;
            }

            $image = Media::normalizeUrl($slide['image'] ?? '');
            $title = trim((string) ($slide['title'] ?? ''));
            $description = trim((string) ($slide['description'] ?? ''));
            $url = self::navUrl($slide['url'] ?? '');

            if ($image === '' && $title === '' && $description === '') {
                continue;
            }

            $slides[] = [
                'image' => $image,
                'title' => $title,
                'description' => $description,
                'url' => $url,
            ];
        }

        return $slides;
    }

    private static function navUrl(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (str_starts_with($value, '#') || str_starts_with($value, '/')) {
            return str_contains($value, '..')
                || str_contains($value, '\\')
                || str_contains($value, "\0")
                || preg_match('/[\x00-\x1F\x7F]/', $value) === 1
                    ? ''
                    : $value;
        }

        return preg_match('#^https?://#i', $value) === 1 ? $value : '';
    }
}
