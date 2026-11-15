<?php

declare(strict_types=1);

namespace TTDF\Compat;

final class Upload
{
    private const UPLOAD_PATH_PREFIX = 'usr/uploads/';

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

        $base = Typecho::siteUrl();
        return $base === '' ? '' : rtrim($base, '/') . '/' . ltrim($path, '/');
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

        if (!defined('__TYPECHO_ROOT_DIR__')) {
            return false;
        }

        $root = rtrim((string) constant('__TYPECHO_ROOT_DIR__'), '/\\');
        $absolute = $root . '/' . $path;
        $uploadRoot = realpath($root . '/' . rtrim(self::UPLOAD_PATH_PREFIX, '/'));
        if ($uploadRoot === false) {
            return false;
        }

        $realFile = realpath($absolute);
        if ($realFile !== false && !self::withinDirectory($realFile, $uploadRoot)) {
            return false;
        }

        if (class_exists($handler)) {
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

        return str_starts_with($path, self::UPLOAD_PATH_PREFIX)
            && !str_contains($path, '..')
            && !str_contains($path, "\0");
    }

    private static function withinDirectory(string $path, string $directory): bool
    {
        $path = str_replace('\\', '/', $path);
        $directory = rtrim(str_replace('\\', '/', $directory), '/');

        return str_starts_with(strtolower($path), strtolower($directory) . '/');
    }
}
