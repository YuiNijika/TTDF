<?php

declare(strict_types=1);

namespace TTDF\Http;

use InvalidArgumentException;
use TTDF\Support\Json;
use TTDF\Compat\Typecho;

final class Request
{
    public const MAX_BODY_BYTES = 1_048_576;

    private static ?string $body = null;

    /**
     * Inject a raw body for tests and headless integrations.
     */
    public static function setBody(?string $body): void
    {
        self::$body = $body;
    }

    public static function resetBody(): void
    {
        self::$body = null;
    }

    public static function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public static function path(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? rawurldecode($path) : '/';
        $path = trim($path, '/');

        $base = parse_url(Typecho::siteUrl(), PHP_URL_PATH);
        $base = is_string($base) ? trim(rawurldecode($base), '/') : '';
        if ($base !== '' && ($path === $base || str_starts_with($path, $base . '/'))) {
            $path = ltrim(substr($path, strlen($base)), '/');
        }

        if ($path === 'index.php') {
            $path = '';
        } elseif (str_starts_with($path, 'index.php/')) {
            $path = substr($path, strlen('index.php/'));
        }

        return $path;
    }

    public static function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public static function queryString(string $key, string $default = ''): string
    {
        $value = self::query($key, $default);
        return is_scalar($value) ? trim((string) $value) : $default;
    }

    public static function queryInt(string $key, int $default = 0): int
    {
        $value = self::query($key, $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    public static function header(string $name): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (isset($_SERVER[$key])) {
            return trim((string) $_SERVER[$key]);
        }

        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $header => $value) {
                if (strcasecmp((string) $header, $name) === 0) {
                    return trim((string) $value);
                }
            }
        }

        return '';
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function file(string $key): ?array
    {
        $file = $_FILES[$key] ?? null;

        return is_array($file) ? $file : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function json(): array
    {
        $declared = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($declared > self::MAX_BODY_BYTES) {
            throw new PayloadTooLargeException('Request body is too large');
        }

        $raw = self::$body ?? file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return [];
        }

        if (strlen($raw) > self::MAX_BODY_BYTES) {
            throw new PayloadTooLargeException('Request body is too large');
        }

        $data = Json::decode($raw);
        if (!is_array($data)) {
            throw new InvalidArgumentException('Request body must be a JSON object');
        }

        return $data;
    }

    public static function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }
}
