<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use TTDF\Diagnostics\ErrorHandler;

final class ErrorHandlerTest extends TestCase
{
    protected function tearDown(): void
    {
        http_response_code(200);
        ErrorHandler::reset();
        set_exception_handler(null);
    }

    public function testProductionPageIsCompleteAndHidesExceptionDetails(): void
    {
        http_response_code(500);

        $html = ErrorHandler::render(new RuntimeException('secret / <script>alert(1)</script>'), false);

        self::assertStringStartsWith('<!DOCTYPE html>', $html);
        self::assertStringContainsString('</html>', $html);
        self::assertStringContainsString('页面渲染失败', $html);
        self::assertStringNotContainsString('secret', $html);
        self::assertStringNotContainsString('script', $html);
        self::assertStringNotContainsString('TTDF error:', $html);
    }

    public function testDebugPageEscapesExceptionDetails(): void
    {
        http_response_code(500);

        $html = ErrorHandler::render(new RuntimeException('bad <script> & message'), true);

        self::assertStringContainsString('异常详情', $html);
        self::assertStringContainsString('bad &lt;script&gt; &amp; message', $html);
        self::assertStringNotContainsString('bad <script>', $html);
        self::assertStringContainsString('调用堆栈', $html);
    }

    public function testNotFoundStatusUsesNotFoundTitle(): void
    {
        http_response_code(404);

        $html = ErrorHandler::render(new RuntimeException('missing'), false);

        self::assertStringContainsString('<h1>页面没找到</h1>', $html);
        self::assertStringContainsString('<span class="code">404</span>', $html);
    }

    public function testNotFoundExceptionFallsBackToTypechoHandler(): void
    {
        $captured = null;
        set_exception_handler(function (\Throwable $exception) use (&$captured): void {
            $captured = $exception;
        });

        ErrorHandler::register(false, self::logFile());

        $exception = new RuntimeException("Path '/missing' not found", 404);
        ErrorHandler::handleException($exception);

        self::assertSame($exception, $captured);
    }

    public function testOtherExceptionsStayInsideTheFrameworkPage(): void
    {
        $called = false;
        set_exception_handler(function () use (&$called): void {
            $called = true;
        });

        ErrorHandler::register(false, self::logFile());

        ErrorHandler::handleException(new RuntimeException('boom', 500));

        self::assertFalse($called);
    }

    public function testExceptionCodeProvidesMissingStatus(): void
    {
        http_response_code(200);

        $html = ErrorHandler::render(new RuntimeException('missing', 404), false);

        self::assertStringContainsString('<h1>页面没找到</h1>', $html);
        self::assertStringContainsString('<span class="code">404</span>', $html);
    }

    private static function logFile(): string
    {
        $file = sys_get_temp_dir() . '/ttdf-error-handler-test-' . bin2hex(random_bytes(6)) . '.log';
        register_shutdown_function(static function () use ($file): void {
            if (is_file($file)) {
                unlink($file);
            }
        });

        return $file;
    }
}
