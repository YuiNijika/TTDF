<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

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
