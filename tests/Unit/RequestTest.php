<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TTDF\Http\PayloadTooLargeException;
use TTDF\Http\Request;

final class RequestTest extends TestCase
{
    protected function tearDown(): void
    {
        Request::resetBody();
        $_SERVER = [];
    }

    public function testDeclaredOversizedBodyIsRejectedBeforeReading(): void
    {
        $_SERVER['CONTENT_LENGTH'] = (string) (Request::MAX_BODY_BYTES + 1);

        $this->expectException(PayloadTooLargeException::class);
        Request::json();
    }

    public function testEmptyBodyDecodesToEmptyArray(): void
    {
        $_SERVER['CONTENT_LENGTH'] = '0';

        self::assertSame([], Request::json());
    }

    public function testHeadersAreReadCaseInsensitively(): void
    {
        $_SERVER['HTTP_X_TYPECHO_TOKEN'] = ' token ';

        self::assertSame('token', Request::header('X-Typecho-Token'));
        self::assertSame('token', Request::header('x-typecho-token'));
        self::assertSame('', Request::header('Authorization'));
    }

    public function testInjectedJsonBodyIsDecoded(): void
    {
        $_SERVER['CONTENT_LENGTH'] = '17';
        Request::setBody('{"name":"ttdf"}');

        self::assertSame(['name' => 'ttdf'], Request::json());
    }

    public function testInvalidJsonBodyIsRejected(): void
    {
        Request::setBody('{not-json');

        $this->expectException(\InvalidArgumentException::class);
        Request::json();
    }

    public function testBodySizeLimitAlsoAppliesToInjectedBodies(): void
    {
        Request::setBody(str_repeat('a', Request::MAX_BODY_BYTES + 1));

        $this->expectException(PayloadTooLargeException::class);
        Request::json();
    }
}
