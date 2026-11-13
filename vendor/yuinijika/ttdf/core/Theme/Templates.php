<?php

declare(strict_types=1);

namespace TTDF\Theme;

use TTDF\View\Components;

final class Templates
{
    public const SLOTS = [
        'post' => '文章',
        'page' => '独立页面',
        'index' => '首页',
        'archive' => '归档',
        'category' => '分类',
        'tag' => '标签',
        'search' => '搜索',
        'author' => '作者',
        'date' => '日期',
        '404' => '404',
    ];

    /**
     * @return array<string, string>
     */
    public static function mapping(): array
    {
        $value = Options::instance()->get('Templates', []);

        return self::normalizeMap(is_array($value) ? $value : []);
    }

    /**
     * @param array<string, mixed> $map
     * @return array<string, string>
     */
    public static function normalizeMap(array $map): array
    {
        $result = [];

        foreach (array_keys(self::SLOTS) as $slotName) {
            if (!array_key_exists($slotName, $map)) {
                continue;
            }

            $component = $map[$slotName];
            if (!is_string($component)) {
                continue;
            }

            $component = trim(str_replace('\\', '/', $component));
            $component = trim($component, '/');
            if ($component === '') {
                $result[$slotName] = '';
                continue;
            }

            if (str_contains($component, '..')) {
                continue;
            }

            if (preg_match('/^[A-Za-z0-9_\/-]+$/', $component) !== 1) {
                continue;
            }

            $result[$slotName] = $component;
        }

        return $result;
    }

    /**
     * Resolve the configured component for a slot, falling back to a default.
     *
     * @param array<string, string>|null $map
     */
    public static function component(string $slot, string $default = '', ?array $map = null): string
    {
        $map ??= self::mapping();

        return $map[$slot] ?? $default;
    }

    /**
     * Render the configured component. Returns false when the slot has no
     * usable component, so the entry template can keep its inline fallback.
     *
     * @param array<string, string>|null $map
     */
    public static function render(
        string $root,
        string $slot,
        object $archive,
        string $default = '',
        ?array $map = null
    ): bool {
        $component = self::component($slot, $default, $map);
        if ($component === '' || !Components::exists($root, $component)) {
            return false;
        }

        Components::render($root, $component, [
            'archive' => $archive,
            'slot' => $slot,
        ]);

        return true;
    }

    public static function archiveSlot(object $archive): string
    {
        if (!is_callable([$archive, 'is'])) {
            return 'archive';
        }

        foreach (['index', 'category', 'tag', 'search', 'author', 'date'] as $slot) {
            if ($archive->is($slot)) {
                return $slot;
            }
        }

        return 'archive';
    }

    /**
     * @return list<string>
     */
    public static function available(string $root): array
    {
        $base = $root . '/app/components';
        if (!is_dir($base)) {
            return [];
        }

        $base = realpath($base);
        if ($base === false) {
            return [];
        }

        $components = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }

            if (strtolower($file->getExtension()) !== 'php') {
                continue;
            }

            $path = realpath($file->getPathname());
            if ($path === false || !str_starts_with(str_replace('\\', '/', $path), str_replace('\\', '/', $base) . '/')) {
                continue;
            }

            $relative = substr($path, strlen($base) + 1, -4);
            $relative = str_replace('\\', '/', $relative);
            if (preg_match('/^[A-Za-z0-9_\/-]+$/', $relative) === 1) {
                $components[] = $relative;
            }
        }

        sort($components);

        return $components;
    }
}
