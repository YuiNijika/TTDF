<?php

declare(strict_types=1);

namespace TTDF\Http\Rest\V2;

use InvalidArgumentException;
use TTDF\Compat\Typecho;
use TTDF\Hooks\Hooks;
use TTDF\Http\PayloadTooLargeException;
use TTDF\Http\Request;
use TTDF\Http\Response;
use TTDF\Http\ResponseException;
use TTDF\Http\Rest\Registry;
use TTDF\Http\Rest\Router as V1Router;
use TTDF\Http\Rest\RouteMatch;

/**
 * Router for the v2 API.
 *
 * v2 is a full API surface: sessions, front-end reads, comment writing and the
 * administration endpoints. It is mounted on its own prefix and has its own
 * child switch, while the public REST switch remains the master switch for
 * both API versions. Built-in routes always win over extension routes so a
 * theme cannot shadow `auth/login` or `admin/options`.
 */
final class Router
{
    private const ROUTE_HOME = 'TTDF_API_V2_HOME';

    private const ROUTE_BASE = 'TTDF_API_V2';

    private const SCOPE_PUBLIC = 'v2.public';

    private const SCOPE_ADMIN = 'v2.admin';

    /** Unsafe methods authenticated by a cookie still have to prove the origin. */
    private const CSRF_EXEMPT = ['auth/login', 'auth/logout'];

    /** @var list<string> */
    private static array $registered = [];

    public static function enabled(): bool
    {
        return \TTDF\Theme\ThemeConfig::restV2Enabled();
    }

    public static function prefix(): string
    {
        return \TTDF\Theme\ThemeConfig::restV2Prefix();
    }

    public static function matchedPrefix(): string
    {
        return \TTDF\Http\Rest\Prefixes::match(\TTDF\Http\Rest\Prefixes::V2) ?? self::prefix();
    }

    public static function defaultTokenTtl(): int
    {
        return Token::clampTtl(\TTDF\Theme\ThemeConfig::restV2TokenTtl());
    }

    public static function registerRoutes(): void
    {
        self::unregisterRoutes();

        foreach (\TTDF\Http\Rest\Prefixes::for(\TTDF\Http\Rest\Prefixes::V2) as $mount) {
            $home = self::routeName('HOME', $mount['prefix']);
            $base = self::routeName('BASE', $mount['prefix']);

            Typecho::addRoute($home, '/' . $mount['prefix'], 'Widget_Archive', 'render');
            Typecho::addRoute($base, '/' . $mount['prefix'] . '/%path alphaslash 0%', 'Widget_Archive', 'render');

            self::$registered[] = $home;
            self::$registered[] = $base;
        }
    }

    public static function unregisterRoutes(): void
    {
        foreach (self::$registered as $name) {
            Typecho::removeRoute($name);
        }

        self::$registered = [];

        // Names used before prefixes became configurable.
        Typecho::removeRoute(self::ROUTE_HOME);
        Typecho::removeRoute(self::ROUTE_BASE);
    }

    public static function syncRoutes(): void
    {
        if (self::enabled()) {
            self::registerRoutes();
            return;
        }

        self::unregisterRoutes();
    }

    public static function isRequest(): bool
    {
        if (!self::enabled()) {
            return false;
        }

        return \TTDF\Http\Rest\Prefixes::match(\TTDF\Http\Rest\Prefixes::V2) !== null;
    }

    public static function handleIfRequested(string $root): void
    {
        if (!self::isRequest()) {
            return;
        }

        self::handle($root);
    }

    public static function handle(string $root): never
    {
        if (!self::enabled()) {
            // A routing table that still holds a stale registration must not be
            // able to serve the write-capable API after the switch is turned
            // off.
            Response::error('Endpoint not found', 404);
        }

        $scope = self::SCOPE_PUBLIC;
        try {
            $segments = self::segments();
            $method = Request::method();
            $path = implode('/', $segments);
            $scope = $segments !== [] && $segments[0] === 'admin' ? self::SCOPE_ADMIN : self::SCOPE_PUBLIC;
            V1Router::enterScope($scope);

            Hooks::doAction('rest.request', $method, $path, $scope);
            Hooks::doAction('rest.v2.request', $method, $path, $scope);

            if ($method === 'OPTIONS') {
                \TTDF\Http\Rest\Cors::preflight();
            }

            self::dispatch($root, $segments, $method, $path);
        } catch (ResponseException $exception) {
            throw $exception;
        } catch (PayloadTooLargeException $exception) {
            Response::error($exception->getMessage(), 413);
        } catch (InvalidArgumentException $exception) {
            Response::error($exception->getMessage(), 400);
        } catch (\Throwable $exception) {
            Hooks::doAction('rest.exception', $exception, V1Router::scope());
            error_log('TTDF REST v2 error: ' . $exception->getMessage());
            Response::error('Internal server error', 500);
        } finally {
            V1Router::leaveScope();
        }
    }

    /**
     * @param list<string> $segments
     */
    private static function dispatch(string $root, array $segments, string $method, string $path): never
    {
        $isAdmin = $segments !== [] && $segments[0] === 'admin';

        $route = self::match($isAdmin ? self::adminRoutes($root) : self::frontRoutes(), $method, $segments, $isAdmin);

        if ($route === null) {
            self::dispatchExtension($root, $segments, $method, $path, $isAdmin);
        }

        self::call($route, $method, $path, $segments);
    }

    /**
     * @param list<string> $segments
     */
    private static function dispatchExtension(
        string $root,
        array $segments,
        string $method,
        string $path,
        bool $isAdmin
    ): never {
        unset($root, $segments);

        $registry = new Registry();
        Hooks::doAction('rest.routes', $registry, $method, $path);
        $match = $registry->match($method, $path, $isAdmin ? Registry::AUTH_ADMIN : Registry::AUTH_PUBLIC);

        if ($match === null) {
            Response::error('Endpoint not found', 404);
        }

        self::invoke($match, $isAdmin, $method, $path);
    }

    /**
     * @param array{tier: string, params: array<string, string>, handler: callable(array<string, string>, array<string, mixed>): never} $route
     * @param list<string> $segments
     */
    private static function call(
        array $route,
        string $method,
        string $path,
        array $segments
    ): never {
        $identity = Auth::requireTier($route['tier']);

        if (!in_array($path, self::CSRF_EXEMPT, true)) {
            Auth::assertTrustedOrigin($method);
        }

        Hooks::doAction('rest.v2.auth', $identity, $route['tier'], $path, $method);

        unset($segments);

        $handler = $route['handler'];
        $handler($route['params'], $identity);
    }

    private static function invoke(RouteMatch $match, bool $isAdmin, string $method, string $path): never
    {
        $identity = $isAdmin ? Auth::requireTier(Auth::TIER_ADMIN) : (Auth::identity() ?? []);
        Hooks::doAction(
            'rest.v2.auth',
            $identity,
            $isAdmin ? Auth::TIER_ADMIN : Auth::TIER_PUBLIC,
            $path,
            $method
        );

        V1Router::dispatchCustom($match);
    }

    /**
     * @param list<array{method: string, path: string, tier: string, handler: callable(array<string, string>, array<string, mixed>): never}> $routes
     * @param list<string> $segments
     * @return array{tier: string, params: array<string, string>, handler: callable(array<string, string>, array<string, mixed>): never}|null
     */
    private static function match(array $routes, string $method, array $segments, bool $isAdmin): ?array
    {
        $path = implode('/', $isAdmin ? array_slice($segments, 1) : $segments);

        foreach ($routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $params = self::params($route['path'], $path);
            if ($params === null) {
                continue;
            }

            return ['tier' => $route['tier'], 'params' => $params, 'handler' => $route['handler']];
        }

        // A known path with the wrong method is a 405, not a 404.
        foreach ($routes as $route) {
            if (self::params($route['path'], $path) !== null) {
                Response::error('Method not allowed', 405);
            }
        }

        return null;
    }

    /**
     * @return array<string, string>|null
     */
    private static function params(string $pattern, string $path): ?array
    {
        $patternParts = explode('/', $pattern);
        $pathParts = explode('/', $path);
        if (count($patternParts) !== count($pathParts)) {
            return null;
        }

        $params = [];
        foreach ($patternParts as $index => $part) {
            $value = $pathParts[$index];
            if (preg_match('/^\{([a-z][a-z0-9_]*)\}$/', $part, $matches) === 1) {
                if ($value === '' || str_contains($value, '..')) {
                    return null;
                }
                $params[$matches[1]] = $value;
                continue;
            }

            if ($part !== $value) {
                return null;
            }
        }

        return $params;
    }

    /**
     * @return list<array{method: string, path: string, tier: string, handler: callable(array<string, string>, array<string, mixed>): never}>
     */
    private static function frontRoutes(): array
    {
        $controller = static fn (): FrontController => new FrontController();

        return [
            self::route('GET', 'site', Auth::TIER_PUBLIC, static fn () => $controller()->site()),
            self::route('GET', 'posts', Auth::TIER_PUBLIC, static fn () => $controller()->posts()),
            self::route('GET', 'posts/{cid}', Auth::TIER_PUBLIC, static fn (array $p) => $controller()->post((int) $p['cid'])),
            self::route('GET', 'pages', Auth::TIER_PUBLIC, static fn () => $controller()->pages()),
            self::route('GET', 'pages/{cid}', Auth::TIER_PUBLIC, static fn (array $p) => $controller()->page((int) $p['cid'])),
            self::route('GET', 'categories', Auth::TIER_PUBLIC, static fn () => $controller()->categories()),
            self::route('GET', 'tags', Auth::TIER_PUBLIC, static fn () => $controller()->tags()),
            self::route('GET', 'comments', Auth::TIER_PUBLIC, static fn () => $controller()->commentList()),
            self::route(
                'GET',
                'attachments',
                Auth::TIER_USER,
                static fn (array $p, array $i) => $controller()->attachments($i)
            ),
            self::route(
                'GET',
                'media',
                Auth::TIER_WRITER,
                static fn (array $p, array $i) => $controller()->mediaList($i)
            ),
            self::route(
                'GET',
                'media/{id}',
                Auth::TIER_WRITER,
                static fn (array $p, array $i) => $controller()->mediaShow($i, (int) $p['id'])
            ),
            self::route(
                'POST',
                'media',
                Auth::TIER_WRITER,
                static fn (array $p, array $i) => $controller()->mediaUpload($i)
            ),
            self::route(
                'POST',
                'media/{id}',
                Auth::TIER_WRITER,
                static fn (array $p, array $i) => $controller()->mediaRename($i, (int) $p['id'])
            ),
            self::route(
                'DELETE',
                'media/{id}',
                Auth::TIER_WRITER,
                static fn (array $p, array $i) => $controller()->mediaDelete($i, (int) $p['id'])
            ),
            self::route('GET', 'search', Auth::TIER_PUBLIC, static fn () => $controller()->search()),
            self::route('GET', 'users/{uid}', Auth::TIER_PUBLIC, static fn (array $p) => $controller()->author((int) $p['uid'])),

            self::route('POST', 'auth/login', Auth::TIER_PUBLIC, static fn () => $controller()->login()),
            self::route('POST', 'auth/register', Auth::TIER_PUBLIC, static fn () => $controller()->register()),
            self::route('POST', 'auth/email-code', Auth::TIER_PUBLIC, static fn () => $controller()->emailCode()),
            self::route(
                'POST',
                'auth/reset-password',
                Auth::TIER_PUBLIC,
                static fn () => $controller()->resetPassword()
            ),
            self::route('POST', 'auth/logout', Auth::TIER_USER, static fn (array $p, array $i) => $controller()->logout($i)),
            self::route('GET', 'auth/session', Auth::TIER_USER, static fn (array $p, array $i) => $controller()->session($i)),
            self::route(
                'POST',
                'auth/password',
                Auth::TIER_USER,
                static fn (array $p, array $i) => $controller()->changePassword($i)
            ),

            self::route('GET', 'me', Auth::TIER_USER, static fn (array $p, array $i) => $controller()->me($i)),
            self::route('PATCH', 'me', Auth::TIER_USER, static fn (array $p, array $i) => $controller()->updateMe($i)),
            self::route(
                'GET',
                'me/comments',
                Auth::TIER_USER,
                static fn (array $p, array $i) => $controller()->myComments($i)
            ),

            self::route('POST', 'comments', Auth::TIER_PUBLIC, static fn () => $controller()->createComment()),
            self::route(
                'DELETE',
                'comments/{coid}',
                Auth::TIER_USER,
                static fn (array $p, array $i) => $controller()->deleteComment($i, (int) $p['coid'])
            ),
        ];
    }

    /**
     * Admin routes are matched without the leading `admin` segment.
     *
     * @return list<array{method: string, path: string, tier: string, handler: callable(array<string, string>, array<string, mixed>): never}>
     */
    private static function adminRoutes(string $root): array
    {
        $controller = static fn (): AdminController => new AdminController($root);
        $writer = Auth::TIER_WRITER;

        return [
            self::route('GET', 'site', Auth::TIER_ADMIN, static fn () => $controller()->site()),
            self::route('GET', 'options', Auth::TIER_ADMIN, static fn () => $controller()->optionsGet()),
            self::route('PATCH', 'options', Auth::TIER_ADMIN, static fn () => $controller()->optionsPatch()),

            self::route('GET', 'contents', $writer, static fn () => $controller()->contentList()),
            self::route('GET', 'contents/{cid}', $writer, static fn (array $p) => $controller()->content((int) $p['cid'])),
            self::route(
                'POST',
                'posts',
                $writer,
                static fn (array $p, array $i) => $controller()->createContent('post', $i)
            ),
            self::route(
                'PATCH',
                'posts/{cid}',
                $writer,
                static fn (array $p, array $i) => $controller()->updateContent('post', (int) $p['cid'], $i)
            ),
            self::route(
                'DELETE',
                'posts/{cid}',
                $writer,
                static fn (array $p) => $controller()->deleteContent('post', (int) $p['cid'])
            ),
            self::route(
                'POST',
                'pages',
                $writer,
                static fn (array $p, array $i) => $controller()->createContent('page', $i)
            ),
            self::route(
                'PATCH',
                'pages/{cid}',
                $writer,
                static fn (array $p, array $i) => $controller()->updateContent('page', (int) $p['cid'], $i)
            ),
            self::route(
                'DELETE',
                'pages/{cid}',
                $writer,
                static fn (array $p) => $controller()->deleteContent('page', (int) $p['cid'])
            ),

            self::route('GET', 'comments', Auth::TIER_ADMIN, static fn () => $controller()->commentList()),
            self::route(
                'PATCH',
                'comments/{coid}',
                Auth::TIER_ADMIN,
                static fn (array $p) => $controller()->commentStatus((int) $p['coid'])
            ),
            self::route(
                'DELETE',
                'comments/{coid}',
                Auth::TIER_ADMIN,
                static fn (array $p) => $controller()->commentDelete((int) $p['coid'])
            ),
            self::route('POST', 'comments/bulk', Auth::TIER_ADMIN, static fn () => $controller()->commentBulk()),

            self::route('GET', 'categories', $writer, static fn () => $controller()->termList('category')),
            self::route('POST', 'categories', $writer, static fn () => $controller()->termCreate('category')),
            self::route(
                'PATCH',
                'categories/{mid}',
                $writer,
                static fn (array $p) => $controller()->termUpdate('category', (int) $p['mid'])
            ),
            self::route(
                'DELETE',
                'categories/{mid}',
                $writer,
                static fn (array $p) => $controller()->termDelete('category', (int) $p['mid'])
            ),
            self::route('GET', 'tags', $writer, static fn () => $controller()->termList('tag')),
            self::route('POST', 'tags', $writer, static fn () => $controller()->termCreate('tag')),
            self::route(
                'PATCH',
                'tags/{mid}',
                $writer,
                static fn (array $p) => $controller()->termUpdate('tag', (int) $p['mid'])
            ),
            self::route(
                'DELETE',
                'tags/{mid}',
                $writer,
                static fn (array $p) => $controller()->termDelete('tag', (int) $p['mid'])
            ),

            self::route('GET', 'users', Auth::TIER_ADMIN, static fn () => $controller()->userList()),
            self::route('POST', 'users', Auth::TIER_ADMIN, static fn () => $controller()->userCreate()),
            self::route('GET', 'users/{uid}', Auth::TIER_ADMIN, static fn (array $p) => $controller()->user((int) $p['uid'])),
            self::route(
                'PATCH',
                'users/{uid}',
                Auth::TIER_ADMIN,
                static fn (array $p, array $i) => $controller()->userUpdate((int) $p['uid'], $i)
            ),
            self::route(
                'DELETE',
                'users/{uid}',
                Auth::TIER_ADMIN,
                static fn (array $p, array $i) => $controller()->userDelete((int) $p['uid'], $i)
            ),

            self::route('GET', 'media', Auth::TIER_ADMIN, static fn () => $controller()->mediaList()),
            self::route('GET', 'media/{id}', Auth::TIER_ADMIN, static fn (array $p) => $controller()->mediaShow((int) $p['id'])),
            self::route('POST', 'media', Auth::TIER_ADMIN, static fn () => $controller()->mediaUpload()),
            self::route('POST', 'media/{id}', Auth::TIER_ADMIN, static fn (array $p) => $controller()->mediaRename((int) $p['id'])),
            self::route(
                'DELETE',
                'media/{id}',
                Auth::TIER_ADMIN,
                static fn (array $p) => $controller()->mediaDelete((int) $p['id'])
            ),

            self::route('GET', 'diagnostics', Auth::TIER_ADMIN, static fn () => $controller()->diagnostics()),
        ];
    }

    /**
     * @param callable(array<string, string>, array<string, mixed>): never $handler
     * @return array{method: string, path: string, tier: string, handler: callable(array<string, string>, array<string, mixed>): never}
     */
    private static function route(string $method, string $path, string $tier, callable $handler): array
    {
        return ['method' => $method, 'path' => $path, 'tier' => $tier, 'handler' => $handler];
    }

    /**
     * @return list<string>
     */
    private static function segments(): array
    {
        $prefix = self::matchedPrefix();
        $path = Request::path();
        $suffix = $path === $prefix ? '' : substr($path, strlen($prefix) + 1);

        $segments = array_values(array_filter(
            explode('/', trim($suffix, '/')),
            static fn (string $segment): bool => $segment !== ''
        ));

        foreach ($segments as $segment) {
            if (
                $segment === '.'
                || $segment === '..'
                || str_contains($segment, "\0")
                || str_contains($segment, '\\')
            ) {
                Response::error('Endpoint not found', 404);
            }
        }

        return $segments;
    }

    private static function routeName(string $kind, string $prefix): string
    {
        return self::ROUTE_BASE . '_' . $kind . '_' . substr(sha1($prefix), 0, 10);
    }

}
