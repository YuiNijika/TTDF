<?php

declare(strict_types=1);

namespace TTDF\Http\Rest;

use InvalidArgumentException;
use RuntimeException;
use TTDF\Compat\Typecho;
use TTDF\Config\Config;
use TTDF\Hooks\Hooks;
use TTDF\Http\PayloadTooLargeException;
use TTDF\Http\Request;
use TTDF\Http\Response;
use TTDF\Http\ResponseException;

final class Router
{
    private static string $scope = Registry::AUTH_PUBLIC;

    private static bool $handling = false;

    public static function registerRoutes(): void
    {
        $prefix = trim((string) Config::get('rest.prefix', 'ttdf/v1'), '/');
        Typecho::removeRoute('TTDF_API_V1_HOME');
        Typecho::removeRoute('TTDF_API_V1');

        Typecho::addRoute('TTDF_API_V1_HOME', '/' . $prefix, 'Widget_Archive', 'render');
        Typecho::addRoute('TTDF_API_V1', '/' . $prefix . '/%path alphaslash 0%', 'Widget_Archive', 'render');
    }

    public static function unregisterRoutes(): void
    {
        Typecho::removeRoute('TTDF_API_V1_HOME');
        Typecho::removeRoute('TTDF_API_V1');
    }

    public static function syncRoutes(): void
    {
        if (State::enabled() || Typecho::isAdmin()) {
            self::registerRoutes();
            return;
        }

        self::unregisterRoutes();
    }

    public static function handleIfRequested(string $root): void
    {
        if (!self::isRequest()) {
            return;
        }

        self::handle($root);
    }

    public static function isRequest(): bool
    {
        $prefix = trim((string) Config::get('rest.prefix', 'ttdf/v1'), '/');
        $path = self::requestPath();

        return $path === $prefix || str_starts_with($path, $prefix . '/');
    }

    public static function handle(string $root): never
    {
        self::$handling = true;
        try {
            $segments = self::segments();
            $method = Request::method();
            $path = implode('/', $segments);
            $isAdmin = $segments !== [] && $segments[0] === 'admin';
            self::$scope = $isAdmin ? Registry::AUTH_ADMIN : Registry::AUTH_PUBLIC;

            $registry = new Registry();
            Hooks::doAction('rest.request', $method, $path, self::$scope);
            Hooks::doAction('rest.routes', $registry, $method, $path);

            if ($isAdmin) {
                if ($method === 'OPTIONS') {
                    Response::error('Method not allowed', 405);
                }

                Auth::requireAdmin();
                Auth::requireSameOrigin();
                Auth::requireNonce();
                $route = $registry->match($method, $path, Registry::AUTH_ADMIN);
                if ($route !== null) {
                    self::dispatchCustom($route);
                }
                self::dispatchAdmin($root, array_slice($segments, 1), $method);
            }

            if ($method === 'OPTIONS') {
                Cors::preflight();
            }

            if ($method !== 'GET') {
                Response::error('Method not allowed', 405);
            }

            Auth::requirePublicAccess();
            Cors::apply();
            $route = $registry->match($method, $path, Registry::AUTH_PUBLIC);
            if ($route !== null) {
                self::dispatchCustom($route);
            }
            self::dispatchPublic($segments);
        } catch (ResponseException $exception) {
            throw $exception;
        } catch (PayloadTooLargeException $exception) {
            Response::error($exception->getMessage(), 413);
        } catch (InvalidArgumentException $exception) {
            Response::error($exception->getMessage(), 400);
        } catch (\Throwable $exception) {
            Hooks::doAction('rest.exception', $exception, self::$scope);
            error_log('TTDF REST error: ' . $exception->getMessage());
            Response::error('Internal server error', 500);
        } finally {
            self::$handling = false;
        }
    }

    public static function scope(): string
    {
        return self::$scope;
    }

    public static function isHandling(): bool
    {
        return self::$handling;
    }

    private static function dispatchCustom(RouteMatch $match): never
    {
        $route = $match->route;
        $request = new RouteRequest($route->method, $route->path, $match->params, $route->auth);
        $result = ($route->handler)($request);

        if ($result instanceof Result) {
            if ($result->error !== null) {
                Response::error($result->error, $result->status, $result->errors);
            }

            if ($result->empty) {
                Response::noContent($result->status);
            }

            Response::success($result->data, $result->meta, $result->status);
        }

        if (is_array($result)) {
            Response::success($result);
        }

        if ($result === null) {
            Response::noContent();
        }

        throw new RuntimeException(
            'REST route handler must return TTDF\\Http\\Rest\\Result, an array, or null'
        );
    }

    /**
     * @param list<string> $segments
     */
    private static function dispatchPublic(array $segments): never
    {
        $controller = new PublicController();
        $resource = $segments[0] ?? '';
        $id = isset($segments[1]) && ctype_digit($segments[1]) ? (int) $segments[1] : 0;

        match ($resource) {
            'site' => $controller->site(),
            'posts' => $id > 0 ? $controller->post($id) : $controller->posts(),
            'pages' => $id > 0 ? $controller->page($id) : $controller->pages(),
            'categories' => $controller->categories(),
            'tags' => $controller->tags(),
            'comments' => $controller->comments(),
            'attachments' => $controller->attachments(),
            'search' => $controller->search(),
            default => Response::error('Endpoint not found', 404),
        };
    }

    /**
     * @param list<string> $segments
     */
    private static function dispatchAdmin(string $root, array $segments, string $method): never
    {
        $controller = new AdminController($root);
        $media = new MediaController();
        $endpoint = $segments[0] ?? '';
        $mediaId = isset($segments[1]) && ctype_digit((string) $segments[1])
            ? (int) $segments[1]
            : 0;

        if ($method === 'GET') {
            match ($endpoint) {
                'options' => $controller->optionsGet(),
                'schema' => $controller->schema(),
                'config' => $controller->config(),
                'form-data' => $controller->formData(),
                'theme-info' => $controller->themeInfo(),
                'export' => $controller->export(),
                'diagnostics' => $controller->diagnostics(),
                'media' => $mediaId > 0 ? $media->show($mediaId) : $media->index(),
                default => Response::error('Endpoint not found', 404),
            };
        }

        if ($method === 'POST') {
            match ($endpoint) {
                'options' => $controller->optionsPost(),
                'import' => $controller->import(),
                'migrate' => $controller->migrate(),
                'media' => $mediaId > 0 ? $media->rename($mediaId) : $media->upload(),
                default => Response::error('Endpoint not found', 404),
            };
        }

        if ($method === 'DELETE' && $endpoint === 'media' && $mediaId > 0) {
            $media->delete($mediaId);
        }

        Response::error('Method not allowed', 405);
    }

    /**
     * @return list<string>
     */
    private static function segments(): array
    {
        $prefix = trim((string) Config::get('rest.prefix', 'ttdf/v1'), '/');
        $path = self::requestPath();
        $suffix = $path === $prefix ? '' : substr($path, strlen($prefix) + 1);

        return array_values(array_filter(
            explode('/', trim($suffix, '/')),
            static fn (string $segment): bool => $segment !== ''
        ));
    }

    private static function requestPath(): string
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

        return $path;
    }
}
