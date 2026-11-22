<?php

declare(strict_types=1);

namespace TTDF\Http\Rest\V2;

/**
 * URL rules shared by the v2 user and comment endpoints.
 *
 * FILTER_VALIDATE_URL alone accepts executable schemes such as
 * `javascript:`. Every value that can later become an href therefore has to
 * opt into an explicit scheme allowlist here before it is stored.
 */
final class Url
{
    public static function website(mixed $value, int $maxLength = 255): string
    {
        $url = self::clean($value, $maxLength);
        if ($url === '' || str_starts_with($url, '//')) {
            return '';
        }

        $parts = parse_url($url);
        if (
            !is_array($parts)
            || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || (string) ($parts['host'] ?? '') === ''
            || array_key_exists('user', $parts)
            || array_key_exists('pass', $parts)
            || filter_var($url, FILTER_VALIDATE_URL) === false
        ) {
            return '';
        }

        return $url;
    }

    /**
     * Sanitize an existing link for output. Legacy comments may contain a
     * site-relative path, but new homepage values must use website().
     */
    public static function link(mixed $value, int $maxLength = 2048): string
    {
        $url = self::clean($value, $maxLength);
        if ($url === '' || str_starts_with($url, '//')) {
            return '';
        }

        if (str_starts_with($url, '/') || str_starts_with($url, '#') || str_starts_with($url, '?')) {
            return $url;
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) (is_array($parts) ? ($parts['scheme'] ?? '') : ''));
        if (
            !is_array($parts)
            || !in_array($scheme, ['http', 'https', 'mailto'], true)
            || array_key_exists('user', $parts)
            || array_key_exists('pass', $parts)
            || (in_array($scheme, ['http', 'https'], true) && (string) ($parts['host'] ?? '') === '')
            || filter_var($url, FILTER_VALIDATE_URL) === false
        ) {
            return '';
        }

        return $url;
    }

    private static function clean(mixed $value, int $maxLength): string
    {
        if (!is_string($value)) {
            return '';
        }

        $url = trim($value);
        if ($url === '' || strlen($url) > max(1, $maxLength)) {
            return '';
        }

        return preg_match('/[\x00-\x20\x7f"\'<>`\\\\]/', $url) === 1 ? '' : $url;
    }
}
