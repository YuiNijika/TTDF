<?php

declare(strict_types=1);

namespace TTDF\Support;

use RuntimeException;

final class Path
{
    public static function normalize(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#/+#', '/', $path) ?? $path;
        return rtrim($path, '/');
    }

    public static function join(string $base, string ...$parts): string
    {
        $path = self::normalize($base);
        foreach ($parts as $part) {
            $part = trim(self::normalize($part), '/');
            if ($part !== '') {
                $path .= '/' . $part;
            }
        }

        return $path;
    }

    public static function relative(string $base, string $path): string
    {
        $base = self::normalize($base);
        $path = self::normalize($path);

        if ($base === '' || $path === '' || $path === $base) {
            return '';
        }

        return str_starts_with($path, $base . '/')
            ? substr($path, strlen($base) + 1)
            : '';
    }

    public static function contains(string $root, string $path): bool
    {
        $rootReal = realpath($root);
        $pathReal = realpath($path);

        if ($rootReal === false || $pathReal === false) {
            return false;
        }

        $rootReal = rtrim(str_replace('\\', '/', $rootReal), '/');
        $pathReal = str_replace('\\', '/', $pathReal);

        return $pathReal === $rootReal || str_starts_with($pathReal, $rootReal . '/');
    }

    /**
     * Include a trusted PHP template without exposing arbitrary local variables.
     *
     * Templates receive `$route`, `$params`, `$archive` and `$viewData`; callers
     * that need custom values should read them from `$viewData`.
     *
     * @param array<string, mixed> $data
     */
    public static function safeInclude(string $root, string $file, array $data = []): void
    {
        if (str_contains($file, "\0") || !str_ends_with(strtolower($file), '.php')) {
            throw new RuntimeException('Refusing to include an invalid file');
        }

        $resolved = realpath($file);
        if ($resolved === false || !self::contains($root, $resolved)) {
            throw new RuntimeException('Refusing to include a file outside the allowed root');
        }

        (static function (string $template, array $viewData): void {
            $route = $viewData['route'] ?? null;
            $params = is_array($viewData['params'] ?? null) ? $viewData['params'] : [];
            $archive = $viewData['archive'] ?? null;
            include $template;
        })($resolved, $data);
    }
}
