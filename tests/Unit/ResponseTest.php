<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TTDF\Http\Response;
use TTDF\Http\ResponseException;

final class ResponseTest extends TestCase
{
    protected function setUp(): void
    {
        Response::capture(true);
    }

    protected function tearDown(): void
    {
        Response::capture(false);
    }

    public function testSuccessShape(): void
    {
        $exception = $this->capture(static fn () => Response::success(
            [['cid' => 1]],
            ['page' => 1, 'pageSize' => 10]
        ));

        self::assertSame(200, $exception->status());
        self::assertSame(200, $exception->payload()['code']);
        self::assertSame('success', $exception->payload()['message']);
        self::assertSame([['cid' => 1]], $exception->payload()['data']);
        self::assertSame(1, $exception->payload()['meta']['page']);
        self::assertArrayHasKey('timestamp', $exception->payload()['meta']);
    }

    public function testErrorShapeIncludesValidationErrors(): void
    {
        $exception = $this->capture(static fn () => Response::error('Invalid', 422, ['field' => 'bad']));

        self::assertSame(422, $exception->status());
        self::assertSame('Invalid', $exception->payload()['message']);
        self::assertNull($exception->payload()['data']);
        self::assertSame(['field' => 'bad'], $exception->payload()['errors']);
    }

    public function testNoContentUsesEmptyCapturedPayload(): void
    {
        $exception = $this->capture(static fn () => Response::noContent());

        self::assertSame(204, $exception->status());
        self::assertSame([], $exception->payload());
    }

    public function testTextResponseCapturesStatusContentTypeAndBody(): void
    {
        $exception = $this->capture(static fn () => Response::text(
            '<urlset></urlset>',
            200,
            'application/xml; charset=UTF-8',
            ['Cache-Control' => 'no-store']
        ));

        self::assertSame(200, $exception->status());
        self::assertSame('application/xml; charset=UTF-8', $exception->payload()['contentType']);
        self::assertSame('<urlset></urlset>', $exception->payload()['body']);
    }

    public function testRedirectRejectsHeaderInjection(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Response::redirect("/user/profile\r\nLocation: https://evil.example");
    }

    #[DataProvider('invalidRedirectProvider')]
    public function testRedirectRejectsAmbiguousOrCredentialBearingTargets(string $url): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Response::redirect($url);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidRedirectProvider(): iterable
    {
        yield 'scheme-relative' => ['//evil.example/path'];
        yield 'backslash' => ['/\\evil.example'];
        yield 'relative path' => ['user/profile'];
        yield 'userinfo' => ['https://user@example.com/path'];
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
