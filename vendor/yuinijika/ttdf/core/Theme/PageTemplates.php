<?php

declare(strict_types=1);

namespace TTDF\Theme;

use TTDF\Support\Path;

/**
 * Redirects Typecho's Archive renderer to app/pages and resolves the same
 * page-type fallback chain Typecho uses, with a realpath boundary check.
 */
final class PageTemplates
{
    private const FILE_PATTERN = '/^[A-Za-z0-9_\/-]+\.php$/';

    /**
     * @return string|null The selected file relative to app/pages.
     */
    public static function apply(object $archive, string $root): ?string
    {
        $directory = self::directory($root);
        if ($directory === null || !is_callable([$archive, 'setThemeDir'])) {
            return null;
        }

        $archive->setThemeDir($directory . '/');

        $selected = self::select($archive, $directory);
        if (is_callable([$archive, 'setThemeFile'])) {
            $archive->setThemeFile($selected ?? '');
        }

        return $selected;
    }

    public static function directory(string $root): ?string
    {
        $directory = Paths::pages($root);
        if (!is_dir($directory)) {
            return null;
        }

        $resolved = realpath($directory);
        if ($resolved === false) {
            return null;
        }

        return rtrim(str_replace('\\', '/', $resolved), '/');
    }

    public static function isSafeFile(string $file): bool
    {
        return $file !== ''
            && !str_contains($file, "\0")
            && !str_contains($file, '\\')
            && !str_starts_with($file, '/')
            && !str_contains($file, '..')
            && preg_match(self::FILE_PATTERN, $file) === 1;
    }

    private static function select(object $archive, string $directory): ?string
    {
        $current = self::currentFile($archive);
        if ($current !== null && self::resolve($directory, $current) !== null) {
            return $current;
        }

        $type = self::archiveType($archive);
        if ($type === null || $type === '') {
            return self::firstExisting($directory, ['index.php']);
        }

        $slug = self::archiveSlug($archive);
        $candidates = [];

        if ($slug !== null && $slug !== '') {
            $candidates[] = $type . '/' . $slug . '.php';
        }

        $candidates[] = $type . '.php';

        if ($type === 'attachment') {
            $candidates[] = 'page.php';
            $candidates[] = 'post.php';
        }

        if ($type !== 'index' && $type !== 'front') {
            $candidates[] = self::isSingle($archive) ? 'single.php' : 'archive.php';
        }

        $candidates[] = 'index.php';

        return self::firstExisting($directory, $candidates);
    }

    /**
     * @param list<string> $candidates
     */
    private static function firstExisting(string $directory, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (self::isSafeFile($candidate) && self::resolve($directory, $candidate) !== null) {
                return $candidate;
            }
        }

        return null;
    }

    private static function resolve(string $directory, string $file): ?string
    {
        if (!self::isSafeFile($file)) {
            return null;
        }

        $resolved = realpath($directory . '/' . $file);
        if ($resolved === false || !Path::contains($directory, $resolved)) {
            return null;
        }

        return $resolved;
    }

    private static function currentFile(object $archive): ?string
    {
        if (!is_callable([$archive, 'getThemeFile'])) {
            return null;
        }

        try {
            $reflection = new \ReflectionObject($archive);
            if ($reflection->hasProperty('themeFile')) {
                $property = $reflection->getProperty('themeFile');
                if (!$property->isInitialized($archive)) {
                    return null;
                }
            }

            $value = $archive->getThemeFile();
            return is_string($value) ? str_replace('\\', '/', $value) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function archiveType(object $archive): ?string
    {
        if (!is_callable([$archive, 'getArchiveType'])) {
            return null;
        }

        try {
            $type = $archive->getArchiveType();
            return is_string($type) ? $type : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function archiveSlug(object $archive): ?string
    {
        if (!is_callable([$archive, 'getArchiveSlug'])) {
            return null;
        }

        try {
            $reflection = new \ReflectionObject($archive);
            if ($reflection->hasProperty('archiveSlug')) {
                $property = $reflection->getProperty('archiveSlug');
                if (!$property->isInitialized($archive)) {
                    return null;
                }
            }

            $slug = $archive->getArchiveSlug();
            return is_string($slug) ? $slug : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function isSingle(object $archive): bool
    {
        if (!is_callable([$archive, 'is'])) {
            return false;
        }

        try {
            return $archive->is('single') === true;
        } catch (\Throwable) {
            return false;
        }
    }
}
