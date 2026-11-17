<?php

declare(strict_types=1);

namespace TTDF\Routing;

use TTDF\Compat\Typecho;
use TTDF\Config\Config;
use TTDF\Hooks\Hooks;
use TTDF\Http\Rest\Prefixes;
use TTDF\Support\Path;
use TTDF\Theme\Paths;

final class AutoRouter
{
    public static function boot(string $root): void
    {
        if (!(bool) Config::get('router.enabled', true)) {
            return;
        }

        $requestPath = self::requestPath();
        if ($requestPath === '') {
            return;
        }

        $routePath = self::aliasTarget($requestPath);
        if ($routePath === null) {
            if (self::shouldSkip($requestPath)) {
                return;
            }

            $hookRoute = self::resolveHook($requestPath);
            if ($hookRoute !== null) {
                self::setStatus(200);
                ($hookRoute['handler'])($hookRoute['params']);
                exit;
            }

            $routePath = $requestPath;
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

        $match = self::resolve($pagesDir, $routePath);
        if ($match === null) {
            return;
        }

        $route = [
            'path' => $requestPath,
            'params' => $match['params'],
            'file' => str_replace(str_replace('\\', '/', $pagesDir), '', str_replace('\\', '/', $match['file'])),
        ];

        self::setStatus(200);

        Path::safeInclude($pagesDir, $match['file'], [
            'route' => $route,
            'params' => $match['params'],
        ]);
        exit;
    }

    /**
     * Resolve a server-side route registered through the `router.match` filter.
     * Hooks may only return a callable handler and scalar string parameters;
     * invalid payloads are ignored so a broken extension cannot capture a
     * request or include arbitrary files.
     *
     * @return array{handler: callable, params: array<string, string>}|null
     */
    public static function resolveHook(string $requestPath): ?array
    {
        $match = Hooks::applyFilters('router.match', null, trim($requestPath, '/'));
        if ($match === null) {
            return null;
        }

        if (!is_array($match) || !isset($match['handler']) || !is_callable($match['handler'])) {
            error_log('TTDF router.match returned an invalid handler for: ' . $requestPath);

            return null;
        }

        $params = $match['params'] ?? [];
        if (!is_array($params)) {
            error_log('TTDF router.match returned invalid params for: ' . $requestPath);

            return null;
        }

        $normalized = [];
        foreach ($params as $key => $value) {
            if (
                !is_string($key)
                || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key) !== 1
                || !is_string($value)
            ) {
                error_log('TTDF router.match returned an invalid parameter for: ' . $requestPath);

                return null;
            }

            $normalized[$key] = $value;
        }

        return [
            'handler' => $match['handler'],
            'params' => $normalized,
        ];
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

    private static function setStatus(int $status): void
    {
        if (class_exists('Typecho\\Response')) {
            \Typecho\Response::getInstance()->setStatus($status);
        } else {
            http_response_code($status);
        }
    }

    private static function shouldSkip(string $path): bool
    {
        $first = strtolower(explode('/', $path)[0]);
        $restRequest = false;
        foreach (Prefixes::strings() as $restPrefix) {
            if ($path === $restPrefix || str_starts_with($path, $restPrefix . '/')) {
                $restRequest = true;
                break;
            }
        }
        $ajaxRequest = $path === 'ttdf/ajax' || str_starts_with($path, 'ttdf/ajax/');

        return in_array(
            $first,
            ['admin', 'feed', 'assets', 'usr', 'favicon.ico', 'robots.txt', 'sitemap.xml'],
            true
        ) || $restRequest || $ajaxRequest;
    }

    /**
     * Resolve a URL alias such as `sitemap.xml` to a router path such as
     * `sitemap`. Aliases are exact matches and targets must stay inside the
     * router directory.
     */
    private static function aliasTarget(string $path): ?string
    {
        $aliases = Config::get('router.aliases', []);
        if (!is_array($aliases) || $aliases === []) {
            return null;
        }

        $requested = trim($path, '/');
        foreach ($aliases as $alias => $target) {
            if (!is_string($alias) || !is_string($target) || trim($alias, '/') !== $requested) {
                continue;
            }

            $target = trim($target, '/');
            if ($target === '' || str_contains($target, '\\') || str_contains($target, "\0")) {
                return null;
            }

            foreach (explode('/', $target) as $segment) {
                if (
                    $segment === ''
                    || $segment === '.'
                    || $segment === '..'
                    || preg_match('/^[A-Za-z0-9_-]+$/', $segment) !== 1
                ) {
                    return null;
                }
            }

            return $target;
        }

        return null;
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
