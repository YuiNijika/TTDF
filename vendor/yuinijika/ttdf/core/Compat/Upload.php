<?php

declare(strict_types=1);

namespace TTDF\Compat;

final class Upload
{
    private const DEFAULT_UPLOAD_DIR = '/usr/uploads';

    /**
     * Resolve an attachment path through Typecho's upload handler.
     *
     * Typecho 1.2.x expects an array containing an `attachment` object, while
     * 1.3.x expects a `Typecho\Config` instance. Reflection keeps the call
     * compatible with both without version sniffing.
     *
     * @param class-string $handler
     */
    public static function url(string $path, string $handler = 'Widget\\Upload'): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        $normalized = ltrim(str_replace('\\', '/', $path), '/');
        if (!self::isUploadPath($normalized)) {
            return '';
        }

        if (class_exists($handler)) {
            try {
                $method = new \ReflectionMethod($handler, 'attachmentHandle');
                $parameter = $method->getParameters()[0] ?? null;
                $type = $parameter?->getType();
                $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : '';

                $argument = $typeName === 'Typecho\\Config'
                    ? new \Typecho\Config(['path' => $path])
                    : ['attachment' => (object) ['path' => $path]];

                $result = $method->invoke(null, $argument);
                if (is_string($result) && trim($result) !== '') {
                    return trim($result);
                }
            } catch (\Throwable) {
                // Fall through to the site URL based fallback.
            }
        }

        $base = self::baseUrl();
        return $base === '' ? '' : $base . '/' . $normalized;
    }

    /**
     * Delete an uploaded file through Typecho's upload handler.
     *
     * Only paths inside the Typecho upload directory are accepted so a
     * corrupted attachment record cannot be used to unlink arbitrary files.
     *
     * @param class-string $handler
     */
    public static function delete(string $path, string $handler = 'Widget\\Upload'): bool
    {
        $path = ltrim(str_replace('\\', '/', trim($path)), '/');
        if (!self::isUploadPath($path)) {
            return false;
        }

        $root = self::root();
        if ($root === '') {
            return false;
        }

        $absolute = $root . '/' . $path;
        $uploadRoot = realpath($root . '/' . self::directory());
        if ($uploadRoot === false) {
            return false;
        }

        $realFile = realpath($absolute);
        if ($realFile !== false && !self::withinDirectory($realFile, $uploadRoot)) {
            return false;
        }

        if (self::usesTypechoRoot() && class_exists($handler)) {
            try {
                $deleted = (bool) $handler::deleteHandle([
                    'attachment' => (object) ['path' => $path],
                ]);
                if ($deleted) {
                    return true;
                }
            } catch (\Throwable) {
                // Fall through to a guarded unlink.
            }
        }

        return !is_file($absolute) || @unlink($absolute);
    }

    public static function isUploadPath(string $path): bool
    {
        $path = ltrim(str_replace('\\', '/', trim($path)), '/');
        $directory = self::directory();

        return $directory !== ''
            && str_starts_with($path, $directory . '/')
            && !str_contains($path, '..')
            && !str_contains($path, "\0");
    }

    /**
     * Resolve Typecho's upload directory relative to the site root.
     *
     * `__TYPECHO_UPLOAD_DIR__` is the documented override; the Widget
     * constant is the fallback used by Typecho 1.2.x and 1.3.x.
     */
    public static function directory(): string
    {
        $directory = defined('__TYPECHO_UPLOAD_DIR__')
            ? (string) constant('__TYPECHO_UPLOAD_DIR__')
            : (defined('Widget\\Upload::UPLOAD_DIR')
                ? (string) constant('Widget\\Upload::UPLOAD_DIR')
                : self::DEFAULT_UPLOAD_DIR);

        $directory = str_replace('\\', '/', trim($directory));
        $directory = trim((string) preg_replace('#/+#', '/', $directory), '/');

        if ($directory === '' || str_contains($directory, '..')) {
            return trim(self::DEFAULT_UPLOAD_DIR, '/');
        }

        return $directory;
    }

    /**
     * Resolve the filesystem root that contains Typecho's upload directory.
     *
     * Typecho uses `__TYPECHO_UPLOAD_ROOT_DIR__` when uploads live outside the
     * site root, so deletion must use the same base as `uploadHandle()`.
     */
    public static function root(): string
    {
        $root = defined('__TYPECHO_UPLOAD_ROOT_DIR__')
            ? (string) constant('__TYPECHO_UPLOAD_ROOT_DIR__')
            : (defined('__TYPECHO_ROOT_DIR__')
                ? (string) constant('__TYPECHO_ROOT_DIR__')
                : '');

        return rtrim(str_replace('\\', '/', trim($root)), '/');
    }

    /**
     * Resolve the public base URL used by `attachmentHandle()`.
     */
    public static function baseUrl(): string
    {
        $base = defined('__TYPECHO_UPLOAD_URL__')
            ? (string) constant('__TYPECHO_UPLOAD_URL__')
            : Typecho::siteUrl();
        $base = trim(str_replace('\\', '/', $base));

        if ($base === '') {
            return '';
        }

        return rtrim($base, '/');
    }

    /**
     * Typecho's own delete handler resolves paths against `__TYPECHO_ROOT_DIR__`.
     * When uploads use a separate root, the framework must delete the guarded
     * path itself instead of asking Typecho to unlink a different file.
     */
    private static function usesTypechoRoot(): bool
    {
        if (!defined('__TYPECHO_UPLOAD_ROOT_DIR__') || !defined('__TYPECHO_ROOT_DIR__')) {
            return true;
        }

        $uploadRoot = rtrim(str_replace('\\', '/', trim((string) constant('__TYPECHO_UPLOAD_ROOT_DIR__'))), '/');
        $siteRoot = rtrim(str_replace('\\', '/', trim((string) constant('__TYPECHO_ROOT_DIR__'))), '/');

        return strcasecmp($uploadRoot, $siteRoot) === 0;
    }

    private static function withinDirectory(string $path, string $directory): bool
    {
        $path = str_replace('\\', '/', $path);
        $directory = rtrim(str_replace('\\', '/', $directory), '/');

        return str_starts_with(strtolower($path), strtolower($directory) . '/');
    }
}
