<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TTDF\Compat\Typecho;
use TTDF\Config\Config;
use TTDF\Hooks\Hooks;
use TTDF\Http\Response;
use TTDF\Http\ResponseException;
use TTDF\Http\Rest\Router;
use TTDF\Theme\Options;

final class RestRouterTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/ttdf/v1/site';
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
        Hooks::reset();
        Config::reset();
        Options::reset();
        Typecho::resetRoutes();
        \Typecho\Router::reset();
        \Typecho\Db::reset();
        \Widget\User::reset();
        \Helper::$optionsOverride = null;
        $_SERVER = [];
        $_GET = [];
    }

    public function testPrefixMatchingIsBoundaryAware(): void
    {
        self::assertTrue(Router::isRequest());

        $_SERVER['REQUEST_URI'] = '/ttdf/v1';
        self::assertTrue(Router::isRequest());

        $_SERVER['REQUEST_URI'] = '/ttdf/v1/';
        self::assertTrue(Router::isRequest());

        $_SERVER['REQUEST_URI'] = '/ttdf/v10/site';
        self::assertFalse(Router::isRequest());

        $_SERVER['REQUEST_URI'] = '/other/ttdf/v1/site';
        self::assertFalse(Router::isRequest());
    }

    public function testEncodedPrefixStillMatches(): void
    {
        $_SERVER['REQUEST_URI'] = '/ttdf/v1/%70osts';
        self::assertTrue(Router::isRequest());
    }

    public function testIndexPhpPrefixStillMatches(): void
    {
        $_SERVER['REQUEST_URI'] = '/index.php/ttdf/v1/site';
        self::assertTrue(Router::isRequest());

        $payload = $this->capture(static fn () => Router::handle(dirname(__DIR__, 2)))->payload();
        self::assertSame(200, $payload['code']);
        self::assertSame('5.0.2-next', $payload['data']['frameworkVersion']);
    }

    public function testSubdirectoryInstallWithIndexPhpPrefixStillMatches(): void
    {
        $options = \Helper::options();
        $options->siteUrl = 'https://example.test/blog';
        \Helper::$optionsOverride = $options;

        $_SERVER['REQUEST_URI'] = '/blog/index.php/ttdf/v1/site';
        self::assertTrue(Router::isRequest());
    }

    public function testRoutesAreRegisteredOnlyWhenPublicApiIsEnabled(): void
    {
        Router::syncRoutes();
        self::assertArrayHasKey('TTDF_API_V1_HOME', \Typecho\Router::routes());
        self::assertArrayHasKey('TTDF_API_V1', \Typecho\Router::routes());

        Config::load(['rest' => ['enabled' => false, 'prefix' => 'ttdf/v1']]);
        Options::reset();
        \Widget\User::$loggedIn = false;
        Router::syncRoutes();
        self::assertArrayNotHasKey('TTDF_API_V1_HOME', \Typecho\Router::routes());
        self::assertArrayNotHasKey('TTDF_API_V1', \Typecho\Router::routes());
    }

    public function testAdminRoutesAreRegisteredEvenWhenPublicApiIsDisabled(): void
    {
        Config::load(['rest' => ['enabled' => false, 'prefix' => 'ttdf/v1']]);
        Options::reset();
        $_SERVER['REQUEST_URI'] = '/ttdf/v1/admin/options';

        Router::syncRoutes();

        self::assertArrayHasKey('TTDF_API_V1', \Typecho\Router::routes());
    }

    public function testSubdirectoryInstallStripsSiteBasePath(): void
    {
        $options = \Helper::options();
        $options->siteUrl = 'https://example.test/blog';
        \Helper::$optionsOverride = $options;

        $_SERVER['REQUEST_URI'] = '/blog/ttdf/v1/posts';
        self::assertTrue(Router::isRequest());

        $_SERVER['REQUEST_URI'] = '/blog/ttdf/v10/posts';
        self::assertFalse(Router::isRequest());
    }

    public function testAdminEndpointsRequireLogin(): void
    {
        $_SERVER['REQUEST_URI'] = '/ttdf/v1/admin/options';
        \Widget\User::$loggedIn = false;

        $exception = $this->capture(static fn () => Router::handle(dirname(__DIR__, 2)));

        self::assertSame(401, $exception->status());
        self::assertSame('Unauthorized', $exception->payload()['message']);
    }

    public function testAdminEndpointsRequireNonce(): void
    {
        $_SERVER['REQUEST_URI'] = '/ttdf/v1/admin/options';
        $_SERVER['HTTP_ORIGIN'] = 'https://example.test';

        $exception = $this->capture(static fn () => Router::handle(dirname(__DIR__, 2)));

        self::assertSame(403, $exception->status());
        self::assertSame('Invalid security token', $exception->payload()['message']);
    }

    public function testAdminNonceIsNotAcceptedFromQueryString(): void
    {
        $_SERVER['REQUEST_URI'] = '/ttdf/v1/admin/options';
        $_SERVER['HTTP_ORIGIN'] = 'https://example.test';
        $_GET = ['_token' => Typecho::securityToken()];

        $exception = $this->capture(static fn () => Router::handle(dirname(__DIR__, 2)));

        self::assertSame(403, $exception->status());
        self::assertSame('Invalid security token', $exception->payload()['message']);
    }

    public function testAdminEndpointsRejectForeignOrigin(): void
    {
        $_SERVER['REQUEST_URI'] = '/ttdf/v1/admin/options';
        $_SERVER['HTTP_ORIGIN'] = 'https://evil.test';
        $_SERVER['HTTP_X_TYPECHO_TOKEN'] = Typecho::securityToken();

        $exception = $this->capture(static fn () => Router::handle(dirname(__DIR__, 2)));

        self::assertSame(403, $exception->status());
        self::assertSame('Invalid origin', $exception->payload()['message']);
    }

    public function testAdminEndpointDispatchesWithLoginNonceAndOrigin(): void
    {
        $_SERVER['REQUEST_URI'] = '/ttdf/v1/admin/options';
        $_SERVER['HTTP_ORIGIN'] = 'https://example.test';
        $_SERVER['HTTP_X_TYPECHO_TOKEN'] = Typecho::securityToken();

        $exception = $this->capture(static fn () => Router::handle(dirname(__DIR__, 2)));

        self::assertSame(200, $exception->status());
        self::assertSame('success', $exception->payload()['message']);
        self::assertArrayHasKey('SubTitle', $exception->payload()['data']);
    }

    public function testPublicEndpointsStayDisabledByDefault(): void
    {
        Config::load(['rest' => ['enabled' => false, 'prefix' => 'ttdf/v1']]);
        $_SERVER['REQUEST_URI'] = '/ttdf/v1/site';

        $exception = $this->capture(static fn () => Router::handle(dirname(__DIR__, 2)));

        self::assertSame(404, $exception->status());
        self::assertSame('REST API is disabled', $exception->payload()['message']);
    }

    public function testInvalidContentDetailPathDoesNotFallBackToTheList(): void
    {
        $_SERVER['REQUEST_URI'] = '/ttdf/v1/posts/not-a-number';

        $exception = $this->capture(static fn () => Router::handle(dirname(__DIR__, 2)));

        self::assertSame(404, $exception->status());
        self::assertSame('Post not found', $exception->payload()['message']);
    }

    public function testTraversalSegmentsAreRejectedBeforeRouteDispatch(): void
    {
        $_SERVER['REQUEST_URI'] = '/ttdf/v1/posts/%2e%2e/admin/options';

        $exception = $this->capture(static fn () => Router::handle(dirname(__DIR__, 2)));

        self::assertSame(404, $exception->status());
        self::assertSame('Endpoint not found', $exception->payload()['message']);
    }

    public function testOversizedAdminBodyReturns413(): void
    {
        $_SERVER['REQUEST_URI'] = '/ttdf/v1/admin/options';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_ORIGIN'] = 'https://example.test';
        $_SERVER['HTTP_X_TYPECHO_TOKEN'] = Typecho::securityToken();
        $_SERVER['CONTENT_LENGTH'] = (string) (\TTDF\Http\Request::MAX_BODY_BYTES + 1);

        $exception = $this->capture(static fn () => Router::handle(dirname(__DIR__, 2)));

        self::assertSame(413, $exception->status());
        self::assertSame('Request body is too large', $exception->payload()['message']);
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
