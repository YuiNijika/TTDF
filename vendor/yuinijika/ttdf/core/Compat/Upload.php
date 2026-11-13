<?php

declare(strict_types=1);

namespace TTDF\Compat;

final class Upload
{
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
}
