<?php

declare(strict_types=1);

namespace TTDF\Http;

use Throwable;
use TTDF\Hooks\Hooks;
use TTDF\Http\Rest\Router as RestRouter;
use TTDF\Support\Json;

final class Response
{
    private static bool $capture = false;

    private static bool $filtering = false;

    /**
     * Enable capture mode for tests and headless integrations. When enabled,
     * sending a response throws ResponseException instead of calling exit().
     */
    public static function capture(bool $enabled = true): void
    {
        self::$capture = $enabled;
    }

    public static function isCapturing(): bool
    {
        return self::$capture;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function json(array $payload, int $status = 200): never
    {
        $payload = self::filterRestPayload($payload, $status);

        if (self::$capture) {
            throw new ResponseException($payload, $status);
        }

        ResponseEmitter::send(
            Json::encode($payload),
            $status,
            [
                'Content-Type' => 'application/json; charset=UTF-8',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'no-store',
            ]
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private static function filterRestPayload(array $payload, int $status): array
    {
        if (self::$filtering || !RestRouter::isHandling()) {
            return $payload;
        }

        self::$filtering = true;
        try {
            $scope = RestRouter::scope();
            $filtered = Hooks::applyFilters(
                'rest.response',
                $payload,
                $status,
                $scope
            );

            $payload = is_array($filtered) ? $filtered : $payload;

            if (str_starts_with($scope, 'v2.')) {
                $filtered = Hooks::applyFilters('rest.v2.response', $payload, $status, $scope);
                $payload = is_array($filtered) ? $filtered : $payload;
            }

            return $payload;
        } catch (Throwable $exception) {
            error_log('TTDF REST response filter error: ' . $exception->getMessage());
            return $payload;
        } finally {
            self::$filtering = false;
        }
    }

    /**
     * @param array<string, mixed> $errors
     */
    public static function error(string $message, int $status, array $errors = []): never
    {
        $payload = [
            'code' => $status,
            'message' => $message,
            'data' => null,
            'meta' => ['timestamp' => time()],
        ];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        self::json($payload, $status);
    }

    /**
     * @param array<mixed> $data
     * @param array<string, mixed> $meta
     */
    public static function success(array $data, array $meta = [], int $status = 200): never
    {
        self::json([
            'code' => $status,
            'message' => 'success',
            'data' => $data,
            'meta' => ['timestamp' => time()] + $meta,
        ], $status);
    }

    public static function noContent(int $status = 204): never
    {
        if (self::$capture) {
            throw new ResponseException([], $status);
        }

        ResponseEmitter::send('', $status, [
            'Content-Length' => '0',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Emit a non-REST terminal response with a stable status and headers.
     *
     * @param array<string, string> $headers
     */
    public static function text(
        string $body,
        int $status = 200,
        string $contentType = 'text/plain; charset=UTF-8',
        array $headers = []
    ): never {
        $headers = ['Content-Type' => $contentType] + $headers;

        if (self::$capture) {
            throw new ResponseException([
                'message' => 'Text response captured',
                'contentType' => $contentType,
                'body' => $body,
            ], $status);
        }

        ResponseEmitter::send($body, $status, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    public static function redirect(
        string $url,
        int $status = 302,
        array $headers = []
    ): never {
        $parts = parse_url($url);
        $relative = str_starts_with($url, '/') && !str_starts_with($url, '//');
        $absolute = is_array($parts)
            && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            && (string) ($parts['host'] ?? '') !== ''
            && !isset($parts['user'])
            && !isset($parts['pass']);

        if (
            $url === ''
            || preg_match('/[\x00-\x20\x7f\\\\]/', $url) === 1
            || (!$relative && !$absolute)
        ) {
            throw new \InvalidArgumentException('Invalid redirect URL');
        }

        self::text('', $status, 'text/plain; charset=UTF-8', [
            'Location' => $url,
            'Cache-Control' => 'no-store',
        ] + $headers);
    }
}
