<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use TTDF\Ajax\Endpoint;
use TTDF\Http\Request;
use TTDF\Http\Response;
use TTDF\Http\ResponseException;

final class AjaxTest extends TestCase
{
    protected function setUp(): void
    {
        Endpoint::reset();
        Request::resetBody();
        Response::capture(true);
        $_GET = [];
        $_POST = [];
        $_SERVER = [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/ttdf/ajax',
            'HTTP_HOST' => 'example.test',
        ];
        \Widget\User::reset();
    }

    protected function tearDown(): void
    {
        Endpoint::reset();
        Request::resetBody();
        Response::capture(false);
        $_GET = [];
        $_POST = [];
        $_SERVER = [];
        \Widget\User::reset();
    }

    public function testRejectsInvalidActionName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Endpoint::register('../bad', static fn (): array => []);
    }

    public function testPublicActionReturnsJson(): void
    {
        Endpoint::register('ping', static fn (array $data): array => [
            'pong' => (int) ($data['value'] ?? 0),
        ], [
            'auth' => 'public',
            'nonce' => false,
            'same_origin' => false,
        ]);
        $_POST = ['action' => 'ping', 'value' => '42'];

        $response = $this->invoke();

        self::assertSame(200, $response->status());
        self::assertSame(['pong' => 42], $response->payload()['data']);
    }

    public function testAdminActionRequiresAuthentication(): void
    {
        \Widget\User::$administrator = false;
        Endpoint::register('admin-only', static fn (): array => ['ok' => true]);
        $_POST = ['action' => 'admin-only'];

        $response = $this->invoke();

        self::assertSame(401, $response->status());
        self::assertSame('Unauthorized', $response->payload()['message']);
    }

    public function testAuthenticatedActionRequiresNonce(): void
    {
        Endpoint::register('admin-only', static fn (): array => ['ok' => true]);
        $_POST = ['action' => 'admin-only'];

        $response = $this->invoke();

        self::assertSame(403, $response->status());
        self::assertSame('Invalid security token', $response->payload()['message']);
    }

    public function testAuthenticatedActionDispatchesWithNonce(): void
    {
        Endpoint::register('admin-only', static fn (): array => ['ok' => true]);
        $_POST = ['action' => 'admin-only'];
        $_SERVER['HTTP_X_TYPECHO_TOKEN'] = hash('sha256', 'test-secret&ttdf-rest-admin');

        $response = $this->invoke();

        self::assertSame(200, $response->status());
        self::assertSame(['ok' => true], $response->payload()['data']);
    }

    public function testMethodIsEnforced(): void
    {
        Endpoint::register('write', static fn (): array => ['ok' => true]);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['action' => 'write'];

        $response = $this->invoke();

        self::assertSame(405, $response->status());
    }

    public function testJsonBodyIsMergedIntoPayload(): void
    {
        Endpoint::register('json', static fn (array $data): array => [
            'value' => $data['value'] ?? null,
        ], [
            'auth' => 'public',
            'nonce' => false,
            'same_origin' => false,
        ]);
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        Request::setBody('{"action":"json","value":"ok"}');

        $response = $this->invoke();

        self::assertSame(200, $response->status());
        self::assertSame(['value' => 'ok'], $response->payload()['data']);
    }

    public function testRequestPathUsesSiteBasePath(): void
    {
        $_SERVER['REQUEST_URI'] = '/sub/ttdf/ajax';
        \Helper::$optionsOverride = (object) [
            'siteUrl' => 'https://example.test/sub/',
            'themeUrl' => 'https://example.test/sub/usr/themes/ttdf',
            'theme' => 'Theme',
            'version' => '1.3.0',
        ];

        try {
            self::assertSame('ttdf/ajax', Request::path());
        } finally {
            \Helper::$optionsOverride = null;
        }
    }

    public function testRequestPathStripsIndexPhpPrefix(): void
    {
        $_SERVER['REQUEST_URI'] = '/index.php/ttdf/ajax';

        self::assertSame('ttdf/ajax', Request::path());
        self::assertTrue(Endpoint::isRequest());
    }

    private function invoke(): ResponseException
    {
        try {
            Endpoint::handle();
        } catch (ResponseException $exception) {
            return $exception;
        }

        self::fail('AJAX handle() did not produce a response');
    }
}
