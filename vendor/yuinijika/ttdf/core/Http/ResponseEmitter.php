<?php

declare(strict_types=1);

namespace TTDF\Http;

/**
 * Emits terminal HTTP responses without relying on Typecho's incomplete status
 * code table. Typecho flushes its own response object from an output-buffer
 * callback, so unsupported codes must be sent after that buffer is detached.
 */
final class ResponseEmitter
{
    /** @var array<int, string> */
    private const REASONS = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Content Too Large',
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',
        422 => 'Unprocessable Content',
        425 => 'Too Early',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
    ];

    /**
     * Typecho 1.2.1 and 1.3.x only recognise this subset. Newer Typecho
     * releases may recognise more codes, so the fallback remains compatible.
     *
     * @var array<int, true>
     */
    private const TYPECHO_STATUSES = [
        100 => true,
        101 => true,
        200 => true,
        201 => true,
        202 => true,
        203 => true,
        204 => true,
        205 => true,
        206 => true,
        300 => true,
        301 => true,
        302 => true,
        303 => true,
        304 => true,
        305 => true,
        307 => true,
        400 => true,
        401 => true,
        402 => true,
        403 => true,
        404 => true,
        405 => true,
        406 => true,
        407 => true,
        408 => true,
        409 => true,
        410 => true,
        411 => true,
        412 => true,
        413 => true,
        414 => true,
        415 => true,
        416 => true,
        417 => true,
        500 => true,
        501 => true,
        502 => true,
        503 => true,
        504 => true,
        505 => true,
    ];

    /**
     * @param array<string, string> $headers
     */
    public static function send(
        string $body,
        int $status,
        array $headers = []
    ): never {
        if ($status < 100 || $status > 599) {
            $status = 500;
        }

        $headers = self::headers($headers);
        $typecho = class_exists('Typecho\\Response');
        $typechoStatus = $typecho && isset(self::TYPECHO_STATUSES[$status]);

        if ($typecho) {
            $response = \Typecho\Response::getInstance();
            $response->setStatus($status);
            if (!$typechoStatus) {
                foreach ($headers as $name => $value) {
                    $response->setHeader($name, $value);
                }
            }
        }

        if (!headers_sent()) {
            self::statusLine($status);

            if (!$typecho || $typechoStatus) {
                foreach ($headers as $name => $value) {
                    header($name . ': ' . $value);
                }
            }
        }

        if ($typecho && !$typechoStatus && !headers_sent()) {
            self::detachTypechoBuffers();
        }

        if ($body !== '') {
            echo $body;
        }

        exit;
    }

    /**
     * Typecho buffers output behind a callback that always calls
     * Response::sendHeaders(). Older Typecho releases then dereference the
     * unsupported status in a private reason table. Set the real status and
     * custom headers first, detach the callback under a narrow warning guard,
     * and let Typecho flush its queued cookies and headers before the body.
     */
    private static function detachTypechoBuffers(): void
    {
        set_error_handler(
            static function (int $severity, string $message): bool {
                return $severity === E_WARNING
                    && str_contains($message, 'Undefined array key');
            }
        );

        try {
            while (ob_get_level() > 0) {
                if (!ob_end_clean()) {
                    break;
                }
            }
        } finally {
            restore_error_handler();
        }
    }

    private static function statusLine(int $status): void
    {
        $reason = self::REASONS[$status] ?? 'Unknown Status';
        header('HTTP/1.1 ' . $status . ' ' . $reason, true, $status);
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private static function headers(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $name => $value) {
            if (
                preg_match('/^[A-Za-z0-9-]+$/', $name) !== 1
                || str_contains($value, "\r")
                || str_contains($value, "\n")
            ) {
                continue;
            }

            $normalized[$name] = $value;
        }

        return $normalized;
    }
}
