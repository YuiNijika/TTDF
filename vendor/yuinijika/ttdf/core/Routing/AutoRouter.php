<?php

declare(strict_types=1);

namespace TTDF\Routing;

use TTDF\Compat\Typecho;
use TTDF\Config\Config;
use TTDF\Support\Path;
use TTDF\Theme\Paths;

final class AutoRouter
{
    public static function boot(string $root): void
    {
        if (!(bool) Config::get('router.enabled', true)) {
            return;
        }

        $appDir = Paths::app($root);
        $pagesDir = Paths::router($root, (string) Config::get('router.pages_dir', 'router'));
        if (!is_dir($pagesDir)) {
            return;
        }

        $pagesDir = realpath($pagesDir);
        if ($pagesDir === false || !Path::contains($appDir, $pagesDir)) {
            return;
        }

        $requestPath = self::requestPath();
        if ($requestPath === '' || self::shouldSkip($requestPath)) {
            return;
        }

        $match = self::resolve($pagesDir, $requestPath);
        if ($match === null) {
            return;
        }

        $route = [
            'path' => $requestPath,
            'params' => $match['params'],
            'file' => str_replace(str_replace('\\', '/', $pagesDir), '', str_replace('\\', '/', $match['file'])),
        ];

        if (class_exists('Typecho\\Response')) {
            \Typecho\Response::getInstance()->setStatus(200);
        } else {
            http_response_code(200);
        }

        Path::safeInclude($pagesDir, $match['file'], [
            'route' => $route,
            'params' => $match['params'],
        ]);
        exit;
    }

    /**
     * Resolve a request path without rendering. Kept public for tests and
     * diagnostics; callers must still treat the result as untrusted input.
     *
     * @return array{file: string, params: array<string, string>}|null
     */
    public static function resolve(string $pagesDir, string $requestPath): ?array
    {
        $root = realpath($pagesDir);
        if ($root === false || !is_dir($root)) {
            return null;
        }

        return self::match($root, trim($requestPath, '/'));
    }

    private static function requestPath(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $base = parse_url(Typecho::siteUrl(), PHP_URL_PATH);
        $base = is_string($base) ? trim(rawurldecode($base), '/') : '';

        return self::normalizePath($uri, $base);
    }

    public static function normalizePath(string $uri, string $basePath = ''): string
    {
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path)) {
            return '';
        }

        $path = rawurldecode($path);
        if (str_contains($path, "\0") || str_contains($path, '\\')) {
            return '';
        }

        $basePath = trim(rawurldecode($basePath), '/');
        $trimmed = trim($path, '/');
        if ($basePath !== '' && ($trimmed === $basePath || str_starts_with($trimmed, $basePath . '/'))) {
            $trimmed = ltrim(substr($trimmed, strlen($basePath)), '/');
        }

        if ($trimmed === 'index.php') {
            return '';
        }

        return str_starts_with($trimmed, 'index.php/')
            ? ltrim(substr($trimmed, strlen('index.php')), '/')
            : $trimmed;
    }

    private static function shouldSkip(string $path): bool
    {
        $first = strtolower(explode('/', $path)[0]);
        $restPrefix = trim((string) Config::get('rest.prefix', 'ttdf/v1'), '/');
        $restRequest = $restPrefix !== ''
            && ($path === $restPrefix || str_starts_with($path, $restPrefix . '/'));

        return in_array($first, ['admin', 'feed', 'assets', 'favicon.ico', 'robots.txt', 'sitemap.xml'], true)
            || $restRequest;
    }

    /**
     * @return array{file: string, params: array<string, string>}|null
     */
    private static function match(string $root, string $requestPath): ?array
    {
        $parts = array_values(array_filter(explode('/', $requestPath), static fn (string $part): bool => $part !== ''));
        foreach ($parts as $part) {
            if ($part === '.' || $part === '..' || preg_match('/^[A-Za-z0-9_-]+$/', $part) !== 1) {
                return null;
            }
        }

        return self::scan($root, $parts, 0, $root);
    }

    /**
     * @param list<string> $parts
     * @return array{file: string, params: array<string, string>}|null
     */
    private static function scan(string $directory, array $parts, int $index, string $root): ?array
    {
        if (!is_dir($directory) || !Path::contains($root, $directory)) {
            return null;
        }

        $files = @scandir($directory);
        if ($files === false) {
            return null;
        }

        if ($index >= count($parts)) {
            $indexFile = $directory . '/index.php';
            if (is_file($indexFile) && Path::contains($root, $indexFile)) {
                return ['file' => $indexFile, 'params' => []];
            }

            foreach ($files as $file) {
                if (preg_match('/^\[\[(\w+)\]\]\.php$/', $file, $matches) !== 1) {
                    continue;
                }

                $filePath = $directory . '/' . $file;
                if (is_file($filePath) && Path::contains($root, $filePath)) {
                    return ['file' => $filePath, 'params' => []];
                }
            }

            return null;
        }

        $part = $parts[$index];
        $exactFile = $directory . '/' . $part . '.php';
        if (is_file($exactFile) && Path::contains($root, $exactFile)) {
            if ($index === count($parts) - 1) {
                return ['file' => $exactFile, 'params' => []];
            }
        }

        $exactDirectory = $directory . '/' . $part;
        if (is_dir($exactDirectory) && Path::contains($root, $exactDirectory)) {
            $result = self::scan($exactDirectory, $parts, $index + 1, $root);
            if ($result !== null) {
                return $result;
            }
        }

        foreach ($files as $file) {
            if (preg_match('/^\[(\w+)\]\.php$/', $file, $matches) !== 1) {
                continue;
            }

            $filePath = $directory . '/' . $file;
            if ($index === count($parts) - 1 && is_file($filePath) && Path::contains($root, $filePath)) {
                return ['file' => $filePath, 'params' => [$matches[1] => $part]];
            }
        }

        foreach ($files as $file) {
            if (preg_match('/^\[(\w+)\]$/', $file, $matches) !== 1) {
                continue;
            }

            $directoryPath = $directory . '/' . $file;
            if (is_dir($directoryPath) && Path::contains($root, $directoryPath)) {
                $result = self::scan($directoryPath, $parts, $index + 1, $root);
                if ($result !== null) {
                    $result['params'][$matches[1]] = $part;
                    return $result;
                }
            }
        }

        foreach ($files as $file) {
            if (preg_match('/^\[\[(\w+)\]\]\.php$/', $file, $matches) !== 1) {
                continue;
            }

            $filePath = $directory . '/' . $file;
            if ($index === count($parts) - 1 && is_file($filePath) && Path::contains($root, $filePath)) {
                return ['file' => $filePath, 'params' => [$matches[1] => $part]];
            }
        }

        foreach ($files as $file) {
            if (preg_match('/^\[\[(\w+)\]\]$/', $file, $matches) !== 1) {
                continue;
            }

            $directoryPath = $directory . '/' . $file;
            if (is_dir($directoryPath) && Path::contains($root, $directoryPath)) {
                $result = self::scan($directoryPath, $parts, $index + 1, $root);
                if ($result !== null) {
                    $result['params'][$matches[1]] = $part;
                    return $result;
                }
            }
        }

        return null;
    }
}
