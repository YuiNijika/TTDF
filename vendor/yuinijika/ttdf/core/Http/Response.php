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

        if (!headers_sent()) {
            if (class_exists('Typecho\\Response')) {
                \Typecho\Response::getInstance()->setStatus($status);
            } else {
                http_response_code($status);
            }
            header('Content-Type: application/json; charset=UTF-8');
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: no-store');
        }

        echo Json::encode($payload);
        exit;
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
            $filtered = Hooks::applyFilters(
                'rest.response',
                $payload,
                $status,
                RestRouter::scope()
            );

            return is_array($filtered) ? $filtered : $payload;
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

        if (!headers_sent()) {
            if (class_exists('Typecho\\Response')) {
                \Typecho\Response::getInstance()->setStatus($status);
            } else {
                http_response_code($status);
            }
            header('Content-Length: 0');
        }

        exit;
    }
}
