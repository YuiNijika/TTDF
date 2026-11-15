<?php

declare(strict_types=1);

namespace TTDF\Ajax;

use InvalidArgumentException;
use TTDF\Compat\Typecho;
use TTDF\Http\PayloadTooLargeException;
use TTDF\Http\Request;
use TTDF\Http\Response;
use TTDF\Http\ResponseException;

/**
 * Small, explicit AJAX endpoint for theme-owned actions.
 *
 * Registrations belong in the framework.boot hook (or before Framework::boot)
 * so they are available before the current request is dispatched.
 */
final class Endpoint
{
    public const ROUTE_NAME = 'TTDF_AJAX';

    public const PATH = 'ttdf/ajax';

    public const TOKEN_HEADER = 'X-Typecho-Token';

    private const ACTION_PATTERN = '/^[a-z0-9][a-z0-9_-]{0,63}$/';

    /** @var array<string, array{handler: callable, method: string, auth: string, nonce: bool, same_origin: bool}> */
    private static array $actions = [];

    private static bool $routesRegistered = false;

    /**
     * @param array{
     *     method?: string,
     *     auth?: string,
     *     nonce?: bool,
     *     same_origin?: bool
     * } $options
     */
    public static function register(string $action, callable $handler, array $options = []): void
    {
        if (preg_match(self::ACTION_PATTERN, $action) !== 1) {
            throw new InvalidArgumentException('Invalid AJAX action name');
        }

        $method = strtoupper((string) ($options['method'] ?? 'POST'));
        if (!in_array($method, ['GET', 'POST', 'ANY'], true)) {
            throw new InvalidArgumentException('Invalid AJAX method');
        }

        $auth = (string) ($options['auth'] ?? 'admin');
        if (!in_array($auth, ['admin', 'user', 'public'], true)) {
            throw new InvalidArgumentException('Invalid AJAX auth policy');
        }

        self::$actions[$action] = [
            'handler' => $handler,
            'method' => $method,
            'auth' => $auth,
            'nonce' => (bool) ($options['nonce'] ?? $auth !== 'public'),
            'same_origin' => (bool) ($options['same_origin'] ?? true),
        ];
    }

    public static function registerRoutes(): void
    {
        if (self::$routesRegistered) {
            return;
        }

        Typecho::addRoute(self::ROUTE_NAME, '/' . self::PATH, 'Widget_Archive', 'render');
        self::$routesRegistered = true;
    }

    public static function url(string $action = ''): string
    {
        $url = '';

        try {
            if (class_exists('Typecho\\Router')) {
                $url = (string) \Typecho\Router::url(self::ROUTE_NAME);
            }
        } catch (\Throwable) {
            $url = '';
        }

        if ($url === '') {
            $url = rtrim(Typecho::siteUrl(), '/') . '/index.php/' . self::PATH;
        }

        return $action === ''
            ? $url
            : $url . (str_contains($url, '?') ? '&' : '?') . http_build_query(['action' => $action]);
    }

    public static function isRequest(): bool
    {
        return Request::path() === self::PATH;
    }

    public static function handleIfRequested(): void
    {
        if (self::isRequest()) {
            self::handle();
        }
    }

    public static function handle(): never
    {
        try {
            $data = Payload::fromRequest();
            $action = $data['action'] ?? '';
            if (!is_string($action) || preg_match(self::ACTION_PATTERN, $action) !== 1) {
                Response::error('Invalid AJAX action', 400);
            }

            $route = self::$actions[$action] ?? null;
            if ($route === null) {
                Response::error('Unknown AJAX action', 404);
            }

            Guard::authorize($route);

            $result = ($route['handler'])($data, $action);
            Response::success(is_array($result) ? $result : ['result' => $result]);
        } catch (ResponseException $exception) {
            throw $exception;
        } catch (PayloadTooLargeException $exception) {
            Response::error($exception->getMessage(), 413);
        } catch (InvalidArgumentException $exception) {
            Response::error($exception->getMessage(), 400);
        } catch (\Throwable $exception) {
            error_log('TTDF AJAX error: ' . $exception->getMessage());
            Response::error('Internal server error', 500);
        }
    }

    public static function reset(): void
    {
        self::$actions = [];
        self::$routesRegistered = false;
    }
}
