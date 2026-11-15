<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use TTDF\Compat\Typecho;
use TTDF\Config\Config;
use TTDF\Hooks\Hooks;
use TTDF\Http\Response;
use TTDF\Http\ResponseException;
use TTDF\Http\Rest\Registry;
use TTDF\Http\Rest\Result;
use TTDF\Http\Rest\RouteRequest;
use TTDF\Http\Rest\Router;
use TTDF\Theme\Options;

final class RestRegistryTest extends TestCase
{
    private const TOKEN_ENV = 'TTDF_TEST_REGISTRY_TOKEN';

    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/ttdf/v1/ext/hello/world';
        $_GET = [];
        Config::load(['rest' => ['enabled' => true, 'prefix' => 'ttdf/v1']]);
        Hooks::reset();
        Options::reset();
        Typecho::resetRoutes();
        \Typecho\Router::reset();
        \Typecho\Db::reset();
        \Widget\User::reset();
        \Helper::$optionsOverride = null;
        Response::capture(true);
    }

    protected function tearDown(): void
    {
        Response::capture(false);
        Config::reset();
        Hooks::reset();
        Options::reset();
        Typecho::resetRoutes();
        \Typecho\Router::reset();
        \Typecho\Db::reset();
        \Widget\User::reset();
        \Helper::$optionsOverride = null;
        putenv(self::TOKEN_ENV);
        $_SERVER = [];
        $_GET = [];
    }

    public function testPublicCustomRouteMatchesNamedParameters(): void
    {
        $events = [];
        Hooks::addAction('rest.request', static function (
            string $method,
            string $path,
            string $scope
        ) use (&$events): void {
            $events[] = [$method, $path, $scope];
        });
        Hooks::addAction('rest.routes', static function (Registry $registry): void {
            $registry->get('ext/hello/{name}', static function (RouteRequest $request): array {
                return [
                    'hello' => $request->param('name'),
                    'method' => $request->method,
                    'scope' => $request->scope,
                ];
            });
        });

        $exception = $this->capture(static fn () => Router::handle(dirname(__DIR__, 2)));

        self::assertSame(200, $exception->status());
        self::assertSame([
            'hello' => 'world',
            'method' => 'GET',
            'scope' => 'public',
        ], $exception->payload()['data']);
        self::assertSame([['GET', 'ext/hello/world', 'public']], $events);
        self::assertSame('public', Router::scope());
    }

    public function testStaticCustomRouteWinsOverNamedParameterRoute(): void
    {
        Hooks::addAction('rest.routes', static function (Registry $registry): void {
            $registry->get('ext/{slug}', static fn (): array => ['route' => 'dynamic']);
            $registry->get('ext/latest', static fn (): array => ['route' => 'static']);
        });
        $_SERVER['REQUEST_URI'] = '/ttdf/v1/ext/latest';

        $exception = $this->capture(static fn () => Router::handle(dirname(__DIR__, 2)));

        self::assertSame(['route' => 'static'], $exception->payload()['data']);
    }

    public function testResultCanReturnCreatedStatus(): void
    {
        Hooks::addAction('rest.routes', static function (Registry $registry): void {
            $registry->post(
                'admin/ext/items',
                static fn (): Result => Result::success(['created' => true], [], 201),
                Registry::AUTH_ADMIN
            );
        });
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/ttdf/v1/admin/ext/items';
        $_SERVER['HTTP_ORIGIN'] = 'https://example.test';
        $_SERVER['HTTP_X_TYPECHO_TOKEN'] = Typecho::securityToken();

        $exception = $this->capture(static fn () => Router::handle(dirname(__DIR__, 2)));

        self::assertSame(201, $exception->status());
        self::assertSame(['created' => true], $exception->payload()['data']);
    }

    public function testAdminCustomRouteRequiresLogin(): void
    {
        Hooks::addAction('rest.routes', static function (Registry $registry): void {
            $registry->get(
                'admin/ext/ping',
                static fn (): array => ['ok' => true],
                Registry::AUTH_ADMIN
            );
        });
        $_SERVER['REQUEST_URI'] = '/ttdf/v1/admin/ext/ping';
        \Widget\User::$loggedIn = false;

        $exception = $this->capture(static fn () => Router::handle(dirname(__DIR__, 2)));

        self::assertSame(401, $exception->status());
    }

    public function testAdminCustomRouteRequiresNonceAndSameOrigin(): void
    {
        Hooks::addAction('rest.routes', static function (Registry $registry): void {
            $registry->get(
                'admin/ext/ping',
                static fn (): array => ['ok' => true],
                Registry::AUTH_ADMIN
            );
        });
        $_SERVER['REQUEST_URI'] = '/ttdf/v1/admin/ext/ping';
        $_SERVER['HTTP_ORIGIN'] = 'https://evil.test';
        $_SERVER['HTTP_X_TYPECHO_TOKEN'] = Typecho::securityToken();

        $exception = $this->capture(static fn () => Router::handle(dirname(__DIR__, 2)));

        self::assertSame(403, $exception->status());
        self::assertSame('Invalid origin', $exception->payload()['message']);
    }

    public function testAdminCustomRouteDispatchesAfterAuthentication(): void
    {
        Hooks::addAction('rest.routes', static function (Registry $registry): void {
            $registry->get(
                'admin/ext/ping',
                static fn (): array => ['ok' => true],
                Registry::AUTH_ADMIN
            );
        });
        $_SERVER['REQUEST_URI'] = '/ttdf/v1/admin/ext/ping';
        $_SERVER['HTTP_ORIGIN'] = 'https://example.test';
        $_SERVER['HTTP_X_TYPECHO_TOKEN'] = Typecho::securityToken();

        $exception = $this->capture(static fn () => Router::handle(dirname(__DIR__, 2)));

        self::assertSame(200, $exception->status());
        self::assertSame(['ok' => true], $exception->payload()['data']);
        self::assertSame('admin', Router::scope());
    }

    public function testPublicCustomRouteStaysDisabledWhenRestIsDisabled(): void
    {
        Config::load(['rest' => ['enabled' => false, 'prefix' => 'ttdf/v1']]);
        Hooks::addAction('rest.routes', static function (Registry $registry): void {
            $registry->get('ext/hello/{name}', static fn (): array => ['ok' => true]);
        });

        $exception = $this->capture(static fn () => Router::handle(dirname(__DIR__, 2)));

        self::assertSame(404, $exception->status());
        self::assertSame('REST API is disabled', $exception->payload()['message']);
    }

    public function testPublicCustomRouteHonorsTokenSwitch(): void
    {
        Config::load([
            'rest' => [
                'enabled' => true,
                'prefix' => 'ttdf/v1',
                'token_enabled' => true,
                'token_env' => self::TOKEN_ENV,
            ],
        ]);
        putenv(self::TOKEN_ENV . '=' . str_repeat('a', 32));
        Hooks::addAction('rest.routes', static function (Registry $registry): void {
            $registry->get('ext/hello/{name}', static fn (): array => ['ok' => true]);
        });

        $exception = $this->capture(static fn () => Router::handle(dirname(__DIR__, 2)));

        self::assertSame(401, $exception->status());
        self::assertSame('Invalid token', $exception->payload()['message']);

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . str_repeat('a', 32);
        $exception = $this->capture(static fn () => Router::handle(dirname(__DIR__, 2)));

        self::assertSame(200, $exception->status());
        self::assertSame(['ok' => true], $exception->payload()['data']);
    }

    public function testResponseFilterReceivesScopeAndCanExtendPayload(): void
    {
        Hooks::addAction('rest.routes', static function (Registry $registry): void {
            $registry->get('ext/hello/{name}', static fn (): array => ['ok' => true]);
        });
        Hooks::addFilter('rest.response', static function (
            array $payload,
            int $status,
            string $scope
        ): array {
            $payload['meta']['scope'] = $scope;
            $payload['meta']['status'] = $status;
            return $payload;
        });

        $exception = $this->capture(static fn () => Router::handle(dirname(__DIR__, 2)));

        self::assertSame('public', $exception->payload()['meta']['scope']);
        self::assertSame(200, $exception->payload()['meta']['status']);
    }

    public function testBrokenResponseFilterFallsBackToOriginalPayload(): void
    {
        Hooks::addAction('rest.routes', static function (Registry $registry): void {
            $registry->get('ext/hello/{name}', static fn (): array => ['ok' => true]);
        });
        Hooks::addFilter('rest.response', static function (): string {
            return 'invalid';
        });

        $exception = $this->capture(static fn () => Router::handle(dirname(__DIR__, 2)));

        self::assertSame(['ok' => true], $exception->payload()['data']);
    }

    public function testExceptionHookReceivesHandlerFailures(): void
    {
        $received = null;
        Hooks::addAction('rest.routes', static function (Registry $registry): void {
            $registry->get('ext/fail', static function (): array {
                throw new \RuntimeException('boom');
            });
        });
        Hooks::addAction('rest.exception', static function (\Throwable $exception) use (&$received): void {
            $received = $exception->getMessage();
        });
        $_SERVER['REQUEST_URI'] = '/ttdf/v1/ext/fail';

        $exception = $this->capture(static fn () => Router::handle(dirname(__DIR__, 2)));

        self::assertSame(500, $exception->status());
        self::assertSame('Internal server error', $exception->payload()['message']);
        self::assertSame('boom', $received);
    }

    public function testRegistryRejectsUnsafePathsAndPublicWrites(): void
    {
        $registry = new Registry();

        foreach (['../secret', 'ext/UPPER', 'ext/{id}/{id}', 'ext//bad'] as $path) {
            try {
                $registry->get($path, static fn (): array => []);
                self::fail('Expected invalid path to be rejected: ' . $path);
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }

        $this->expectException(InvalidArgumentException::class);
        $registry->post('ext/write', static fn (): array => []);
    }

    public function testRegistryRejectsAdminBoundaryViolationsAndReservedRoutes(): void
    {
        $registry = new Registry();

        foreach ([
            static fn () => $registry->get('admin/ext/ping', static fn (): array => []),
            static fn () => $registry->get(
                'ext/ping',
                static fn (): array => [],
                Registry::AUTH_ADMIN
            ),
            static fn () => $registry->get('site', static fn (): array => []),
            static fn () => $registry->get('posts/latest', static fn (): array => []),
            static fn () => $registry->get(
                'admin/options',
                static fn (): array => [],
                Registry::AUTH_ADMIN
            ),
            static fn () => $registry->get(
                'admin/media/1',
                static fn (): array => [],
                Registry::AUTH_ADMIN
            ),
        ] as $callback) {
            try {
                $callback();
                self::fail('Expected invalid REST route to be rejected');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function testRegistryRejectsDuplicateMethodAndPath(): void
    {
        $registry = new Registry();
        $registry->get('ext/ping', static fn (): array => []);

        $this->expectException(InvalidArgumentException::class);
        $registry->get('ext/ping', static fn (): array => []);
    }

    /**
     * @param callable(): void $callback
     */
    private function capture(callable $callback): ResponseException
    {
        try {
            $callback();
        } catch (ResponseException $exception) {
            return $exception;
        }

        self::fail('Expected a captured response');
    }
}
