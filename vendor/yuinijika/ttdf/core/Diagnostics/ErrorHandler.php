<?php

declare(strict_types=1);

namespace TTDF\Diagnostics;

use ErrorException;
use Throwable;

final class ErrorHandler
{
    private static bool $registered = false;

    private static bool $debug = false;

    private static ?Logger $logger = null;

    /** @var callable|null */
    private static mixed $previousExceptionHandler = null;

    public static function register(bool $debug, string $logFile): void
    {
        if (self::$registered) {
            return;
        }

        self::$debug = $debug;
        self::$logger = new Logger($logFile);
        self::$registered = true;

        set_error_handler([self::class, 'handleError']);
        self::$previousExceptionHandler = set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleError(int $severity, string $message, string $file = '', int $line = 0): bool
    {
        if ((error_reporting() & $severity) === 0) {
            return false;
        }

        self::$logger?->write('warning', $message, [
            'file' => $file,
            'line' => $line,
            'severity' => $severity,
        ]);

        return false;
    }

    public static function handleException(Throwable $exception): void
    {
        self::$logger?->write('error', $exception->getMessage(), [
            'class' => $exception::class,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => self::$debug ? $exception->getTraceAsString() : '[hidden]',
        ]);

        if (is_callable(self::$previousExceptionHandler)) {
            (self::$previousExceptionHandler)($exception);
            return;
        }

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, 'TTDF error: ' . $exception->getMessage() . PHP_EOL);
            return;
        }

        if (!headers_sent()) {
            header('HTTP/1.1 500 Internal Server Error', true, 500);
            header('Content-Type: text/plain; charset=UTF-8');
        }

        echo self::$debug
            ? 'TTDF error: ' . $exception->getMessage()
            : 'The site encountered an internal error.';
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return;
        }

        self::$logger?->write('fatal', $error['message'], [
            'file' => $error['file'],
            'line' => $error['line'],
        ]);
    }

    public static function reset(): void
    {
        self::$registered = false;
        self::$logger = null;
        self::$previousExceptionHandler = null;
    }
}
