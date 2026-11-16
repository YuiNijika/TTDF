<?php

declare(strict_types=1);

namespace TTDF\Http\Rest;

use InvalidArgumentException;

final class Registry
{
    public const AUTH_PUBLIC = 'public';
    public const AUTH_ADMIN = 'admin';

    private const METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    /** @var list<string> */
    private const RESERVED_PUBLIC = [
        'site',
        'posts',
        'pages',
        'categories',
        'tags',
        'comments',
        'attachments',
        'search',
    ];

    /** @var list<string> */
    private const RESERVED_ADMIN = [
        'admin/options',
        'admin/schema',
        'admin/config',
        'admin/form-data',
        'admin/theme-info',
        'admin/export',
        'admin/import',
        'admin/migrate',
        'admin/backup',
        'admin/backups',
        'admin/restore',
        'admin/diagnostics',
        'admin/media',
    ];

    /** @var list<Route> */
    private array $routes = [];

    private int $sequence = 0;

    public function get(
        string $path,
        callable $handler,
        string $auth = self::AUTH_PUBLIC,
        int $priority = 10
    ): self {
        return $this->add('GET', $path, $handler, $auth, $priority);
    }

    public function post(
        string $path,
        callable $handler,
        string $auth = self::AUTH_ADMIN,
        int $priority = 10
    ): self {
        return $this->add('POST', $path, $handler, $auth, $priority);
    }

    public function put(
        string $path,
        callable $handler,
        string $auth = self::AUTH_ADMIN,
        int $priority = 10
    ): self {
        return $this->add('PUT', $path, $handler, $auth, $priority);
    }

    public function patch(
        string $path,
        callable $handler,
        string $auth = self::AUTH_ADMIN,
        int $priority = 10
    ): self {
        return $this->add('PATCH', $path, $handler, $auth, $priority);
    }

    public function delete(
        string $path,
        callable $handler,
        string $auth = self::AUTH_ADMIN,
        int $priority = 10
    ): self {
        return $this->add('DELETE', $path, $handler, $auth, $priority);
    }

    public function add(
        string $method,
        string $path,
        callable $handler,
        string $auth = self::AUTH_PUBLIC,
        int $priority = 10
    ): self {
        $method = strtoupper(trim($method));
        if (!in_array($method, self::METHODS, true)) {
            throw new InvalidArgumentException('Unsupported REST method: ' . $method);
        }

        if (!in_array($auth, [self::AUTH_PUBLIC, self::AUTH_ADMIN], true)) {
            throw new InvalidArgumentException('Unsupported REST auth mode: ' . $auth);
        }

        if ($auth === self::AUTH_PUBLIC && $method !== 'GET') {
            throw new InvalidArgumentException('Public REST routes must use GET');
        }

        $path = self::normalizePath($path);
        $this->assertBoundary($path, $auth);
        $this->assertNotReserved($path, $auth);

        foreach ($this->routes as $route) {
            if ($route->method === $method && $route->path === $path) {
                throw new InvalidArgumentException('Duplicate REST route: ' . $method . ' /' . $path);
            }
        }

        [$regex, $specificity] = self::compile($path);
        $this->routes[] = new Route(
            $method,
            $path,
            $handler,
            $auth,
            $priority,
            ++$this->sequence,
            $regex,
            $specificity
        );

        return $this;
    }

    /**
     * @return list<Route>
     */
    public function routes(): array
    {
        $routes = $this->routes;
        usort($routes, static function (Route $left, Route $right): int {
            return [$right->priority, $right->specificity, $left->sequence]
                <=> [$left->priority, $left->specificity, $right->sequence];
        });

        return $routes;
    }

    public function match(string $method, string $path, string $auth): ?RouteMatch
    {
        $method = strtoupper(trim($method));
        $path = trim($path, '/');

        foreach ($this->routes() as $route) {
            if ($route->method !== $method || $route->auth !== $auth) {
                continue;
            }

            $params = $route->params($path);
            if ($params !== null) {
                return new RouteMatch($route, $params);
            }
        }

        return null;
    }

    private static function normalizePath(string $path): string
    {
        $path = trim($path, '/');
        if ($path === '' || strlen($path) > 180) {
            throw new InvalidArgumentException('REST route path is empty or too long');
        }

        $segments = explode('/', $path);
        if (count($segments) > 8) {
            throw new InvalidArgumentException('REST route path has too many segments');
        }

        $params = [];
        foreach ($segments as $segment) {
            if (preg_match('/^\{([a-z][a-z0-9_]*)\}$/', $segment, $matches) === 1) {
                if (in_array($matches[1], $params, true)) {
                    throw new InvalidArgumentException('Duplicate REST route parameter: ' . $matches[1]);
                }
                $params[] = $matches[1];
                continue;
            }

            if (preg_match('/^[a-z0-9][a-z0-9_-]*$/', $segment) !== 1) {
                throw new InvalidArgumentException(
                    'REST route segments must be lowercase letters, digits, "-" or "_"'
                );
            }
        }

        return $path;
    }

    private function assertBoundary(string $path, string $auth): void
    {
        $isAdminPath = $path === 'admin' || str_starts_with($path, 'admin/');

        if ($auth === self::AUTH_ADMIN && !str_starts_with($path, 'admin/')) {
            throw new InvalidArgumentException('Admin REST routes must live under /admin/');
        }

        if ($auth === self::AUTH_PUBLIC && $isAdminPath) {
            throw new InvalidArgumentException('Routes under /admin/ must use admin auth');
        }
    }

    private function assertNotReserved(string $path, string $auth): void
    {
        $reserved = $auth === self::AUTH_ADMIN ? self::RESERVED_ADMIN : self::RESERVED_PUBLIC;
        foreach ($reserved as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                throw new InvalidArgumentException('REST route is reserved by TTDF: /' . $path);
            }
        }
    }

    /**
     * @return array{0: string, 1: int}
     */
    private static function compile(string $path): array
    {
        $parts = [];
        $specificity = 0;

        foreach (explode('/', $path) as $segment) {
            if (preg_match('/^\{([a-z][a-z0-9_]*)\}$/', $segment, $matches) === 1) {
                $parts[] = '(?P<' . $matches[1] . '>[^/]+)';
                $specificity += 1;
                continue;
            }

            $parts[] = preg_quote($segment, '#');
            $specificity += 2;
        }

        return ['#^' . implode('/', $parts) . '$#D', $specificity];
    }
}
