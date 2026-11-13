<?php

declare(strict_types=1);

namespace TTDF\View;

use RuntimeException;
use TTDF\Support\Path;

final class Components
{
    /**
     * @param array<string, mixed> $data
     */
    public static function render(string $root, string $name, array $data = []): void
    {
        $file = self::resolve($root, $name);
        Path::safeInclude($root . '/app/components', $file, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function capture(string $root, string $name, array $data = []): string
    {
        ob_start();
        try {
            self::render($root, $name, $data);
            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }

    public static function exists(string $root, string $name): bool
    {
        try {
            self::resolve($root, $name);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function resolve(string $root, string $name): string
    {
        $name = trim(str_replace('\\', '/', $name), '/');
        if ($name === '' || preg_match('/^[A-Za-z0-9_\/-]+$/', $name) !== 1 || str_contains($name, '..')) {
            throw new RuntimeException('Invalid component name');
        }

        $base = $root . '/app/components';
        $file = $base . '/' . $name . '.php';
        $resolved = realpath($file);

        if ($resolved === false || !Path::contains($base, $resolved)) {
            throw new RuntimeException('Component not found: ' . $name);
        }

        return $resolved;
    }
}
