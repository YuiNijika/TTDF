<?php

declare(strict_types=1);

namespace TTDF\Compat;

final class Typecho
{
    private const REST_NONCE_SUFFIX = 'ttdf-rest-admin';

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $routes = null;

    public static function options(): ?object
    {
        return class_exists('Helper') ? \Helper::options() : null;
    }

    public static function siteUrl(): string
    {
        $value = self::option('siteUrl');
        return is_scalar($value) ? (string) $value : '';
    }

    public static function themeUrl(): string
    {
        $value = self::option('themeUrl');
        return is_scalar($value) ? (string) $value : '';
    }

    public static function themeName(): string
    {
        $value = self::option('theme');
        return is_scalar($value) ? (string) $value : '';
    }

    public static function version(): string
    {
        if (self::options() === null) {
            return 'unknown';
        }

        $version = self::option('version');
        if (is_scalar($version) && (string) $version !== '') {
            return (string) $version;
        }

        $legacy = self::option('Version');
        return is_scalar($legacy) && (string) $legacy !== '' ? (string) $legacy : 'unknown';
    }

    public static function securityToken(): string
    {
        try {
            if (class_exists('Widget\\Security')) {
                $security = \Widget\Security::alloc();
                return (string) $security->getToken(self::REST_NONCE_SUFFIX);
            }
        } catch (\Throwable) {
            // A missing security widget must not break theme rendering.
        }

        return '';
    }

    public static function checkToken(string $token): bool
    {
        if ($token === '') {
            return false;
        }

        $expected = self::securityToken();
        return $expected !== '' && hash_equals($expected, $token);
    }

    public static function isAdmin(): bool
    {
        if (!class_exists('Widget\\User')) {
            return false;
        }

        try {
            $user = \Widget\User::alloc();
            if (!$user->hasLogin()) {
                return false;
            }

            return (bool) $user->pass('administrator', true);
        } catch (\Throwable) {
            return false;
        }
    }

    public static function addRoute(string $name, string $pattern, string $widget, string $action): void
    {
        if (!class_exists('Typecho\\Router') || !class_exists('Typecho\\Router\\Parser')) {
            return;
        }

        $routes = self::routes();
        if ($routes === null) {
            return;
        }

        $parser = new \Typecho\Router\Parser([
            $name => [
                'url' => $pattern,
                'widget' => $widget,
                'action' => $action,
            ],
        ]);
        $parsed = $parser->parse();
        if (isset($parsed[$name])) {
            $routes[$name] = $parsed[$name];
            self::$routes = $routes;
            \Typecho\Router::setRoutes([$routes]);
        }
    }

    public static function removeRoute(string $name): void
    {
        if (!class_exists('Typecho\\Router')) {
            return;
        }

        $routes = self::routes();
        if ($routes === null || !isset($routes[$name])) {
            return;
        }

        unset($routes[$name]);
        self::$routes = $routes;
        \Typecho\Router::setRoutes([$routes]);
    }

    public static function resetRoutes(): void
    {
        self::$routes = null;
    }

    /**
     * @return array<string, array<string, mixed>>|null
     */
    private static function routes(): ?array
    {
        if (self::$routes !== null) {
            return self::$routes;
        }

        $options = self::options();
        if (!$options || !isset($options->routingTable) || !is_array($options->routingTable)) {
            return null;
        }

        $table = $options->routingTable;
        if (isset($table[0]) && is_array($table[0])) {
            return self::$routes = $table[0];
        }

        if (!class_exists('Typecho\\Router\\Parser')) {
            return null;
        }

        return self::$routes = (new \Typecho\Router\Parser($table))->parse();
    }

    public static function markdown(string $text): string
    {
        try {
            if (class_exists('Utils\\Markdown')) {
                return (string) \Utils\Markdown::convert($text);
            }
        } catch (\Throwable) {
            // Fall back to plain text if the parser is unavailable.
        }

        return $text;
    }

    /**
     * Apply the same tag whitelist Typecho uses when rendering comments.
     */
    public static function commentHtml(string $html): string
    {
        $allowed = self::option('commentsHTMLTagAllowed');
        $allowed = is_scalar($allowed) ? (string) $allowed : '';
        $allowedTags = '<p><br>' . $allowed;

        try {
            if (class_exists('Typecho\\Common')) {
                return (string) \Typecho\Common::stripTags($html, $allowedTags);
            }
        } catch (\Throwable) {
            // Fall back to the conservative whitelist below.
        }

        return strip_tags($html, $allowedTags);
    }

    private static function option(string $name): mixed
    {
        $options = self::options();
        if ($options === null) {
            return null;
        }

        if (property_exists($options, $name) || method_exists($options, '__get')) {
            return $options->{$name};
        }

        return null;
    }
}
