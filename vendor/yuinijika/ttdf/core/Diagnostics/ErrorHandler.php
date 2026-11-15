<?php

declare(strict_types=1);

namespace TTDF\Diagnostics;

use Throwable;

final class ErrorHandler
{
    private const FATAL_ERRORS = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];

    private static bool $registered = false;

    private static bool $debug = false;

    private static ?Logger $logger = null;

    private static int $baseBufferLevel = 0;

    private static bool $handling = false;

    private static bool $emitted = false;

    /**
     * Typecho's own handler, kept so a 404 can still render the theme's 404
     * template instead of the framework error page.
     *
     * @var callable|null
     */
    private static $previousExceptionHandler = null;

    public static function register(bool $debug, string $logFile): void
    {
        if (self::$registered) {
            return;
        }

        self::$debug = $debug;
        self::$logger = new Logger($logFile);
        self::$registered = true;
        self::$baseBufferLevel = ob_get_level();

        if (PHP_SAPI !== 'cli') {
            ob_start();
        }

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
        if (self::$handling || self::$emitted) {
            return;
        }

        $previous = self::$previousExceptionHandler;

        if ($exception->getCode() === 404 && $previous !== null) {
            self::$handling = true;
            self::$emitted = true;

            try {
                self::report($exception, 'not_found', 'warning');
                $previous($exception);
            } finally {
                self::$handling = false;
            }

            return;
        }

        self::$handling = true;

        try {
            self::report($exception, 'uncaught');

            if (PHP_SAPI === 'cli') {
                fwrite(STDERR, 'TTDF error: ' . $exception->getMessage() . PHP_EOL);
                return;
            }

            self::discardOutput();
            self::emit($exception);
        } finally {
            self::$handling = false;
        }
    }

    public static function handleShutdown(): void
    {
        if (self::$emitted) {
            return;
        }

        $error = error_get_last();
        if ($error === null || !in_array($error['type'], self::FATAL_ERRORS, true)) {
            return;
        }

        $exception = new \ErrorException(
            $error['message'],
            0,
            $error['type'],
            $error['file'],
            $error['line']
        );
        self::report($exception, 'fatal');

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, 'TTDF fatal: ' . $error['message'] . PHP_EOL);
            return;
        }

        self::discardOutput();
        self::emit($exception);
    }

    public static function report(Throwable $exception, string $context = '', string $level = 'error'): void
    {
        self::$logger?->write($level, $exception->getMessage(), [
            'class' => $exception::class,
            'context' => $context,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => self::$debug ? $exception->getTraceAsString() : '[hidden]',
        ]);
    }

    public static function isDebug(): bool
    {
        return self::$debug;
    }

    public static function render(Throwable $exception, ?bool $debug = null): string
    {
        $debug ??= self::$debug;
        $status = self::statusCode($exception);
        $title = self::title($status);
        $description = self::description($status);
        $request = $_SERVER['REQUEST_URI'] ?? '';
        $request = is_string($request) ? $request : '';

        $details = '';
        if ($debug) {
            $rows = [
                '异常类型' => $exception::class,
                '异常信息' => $exception->getMessage(),
                '发生位置' => $exception->getFile() . ':' . $exception->getLine(),
                '请求地址' => $request !== '' ? $request : '[unknown]',
            ];

            $detailRows = '';
            foreach ($rows as $label => $value) {
                $detailRows .= '<dt>' . self::escape($label) . '</dt><dd>' . self::escape($value) . '</dd>';
            }

            $details = '<section class="details" aria-label="异常详情">'
                . '<h2>异常详情</h2>'
                . '<dl>' . $detailRows . '</dl>'
                . '<h3>调用堆栈</h3>'
                . '<pre>' . self::escape($exception->getTraceAsString()) . '</pre>'
                . '</section>';
        }

        return '<!DOCTYPE html>'
            . '<html lang="zh-Hans">'
            . '<head>'
            . '<meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<meta name="robots" content="noindex, nofollow">'
            . '<title>' . self::escape($title) . '</title>'
            . '<style>'
            . ':root{color-scheme:light dark;--bg:#f6f6f7;--card:#fff;--ink:#18181b;--muted:#71717a;--line:#e4e4e7;--soft:#f4f4f5}'
            . '@media (prefers-color-scheme:dark){:root{--bg:#111113;--card:#18181b;--ink:#fafafa;--muted:#a1a1aa;--line:#27272a;--soft:#202023}}'
            . '*,*::before,*::after{box-sizing:border-box}'
            . 'html,body{min-height:100%}'
            . 'body{margin:0;padding:clamp(24px,7vw,96px) 20px;background:var(--bg);color:var(--ink);'
            . 'font:15px/1.7 -apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Hiragino Sans GB","Microsoft YaHei",sans-serif}'
            . 'main{width:min(760px,100%);margin:0 auto}'
            . 'article{padding:clamp(28px,5vw,48px);background:var(--card);border:1px solid var(--line);border-radius:18px}'
            . '.code{display:inline-flex;align-items:center;justify-content:center;min-width:3.5rem;height:2rem;'
            . 'padding:0 .75rem;border-radius:999px;background:var(--soft);color:var(--muted);font:600 13px/1 ui-monospace,SFMono-Regular,Menlo,monospace}'
            . 'h1{margin:18px 0 10px;font-size:clamp(26px,4vw,36px);line-height:1.2;letter-spacing:-.02em}'
            . 'p{margin:0;color:var(--muted)}'
            . '.actions{margin-top:28px}'
            . '.actions a{display:inline-flex;align-items:center;min-height:40px;padding:0 16px;border-radius:10px;'
            . 'border:1px solid var(--line);background:var(--soft);color:var(--ink);text-decoration:none;font-weight:600}'
            . '.actions a:hover{border-color:var(--muted)}'
            . '.actions a:focus-visible{outline:2px solid var(--ink);outline-offset:2px}'
            . '.details{margin-top:36px;padding-top:28px;border-top:1px solid var(--line)}'
            . '.details h2{margin:0 0 16px;font-size:16px}'
            . '.details h3{margin:24px 0 10px;font-size:14px;color:var(--muted)}'
            . 'dl{display:grid;grid-template-columns:minmax(88px,max-content) 1fr;gap:8px 16px;margin:0}'
            . 'dt{color:var(--muted)}dd{margin:0;overflow-wrap:anywhere}'
            . 'pre{margin:0;padding:16px;overflow:auto;border-radius:12px;background:var(--soft);'
            . 'font:12px/1.6 ui-monospace,SFMono-Regular,Menlo,monospace;white-space:pre-wrap;overflow-wrap:anywhere}'
            . '@media (max-width:520px){dl{grid-template-columns:1fr;gap:4px}dd{margin-bottom:10px}}'
            . '</style>'
            . '</head>'
            . '<body><main><article>'
            . '<span class="code">' . self::escape((string) $status) . '</span>'
            . '<h1>' . self::escape($title) . '</h1>'
            . '<p>' . self::escape($description) . '</p>'
            . '<div class="actions"><a href="/">返回首页</a></div>'
            . $details
            . '</article></main></body></html>';
    }

    public static function reset(): void
    {
        if (self::$registered) {
            restore_error_handler();
            restore_exception_handler();
        }

        while (self::$registered && ob_get_level() > self::$baseBufferLevel) {
            ob_end_clean();
        }

        self::$registered = false;
        self::$debug = false;
        self::$logger = null;
        self::$baseBufferLevel = 0;
        self::$handling = false;
        self::$emitted = false;
        self::$previousExceptionHandler = null;
    }

    private static function emit(Throwable $exception): void
    {
        if (self::$emitted) {
            return;
        }

        self::$emitted = true;

        if (!headers_sent()) {
            http_response_code(self::statusCode($exception));
            header('Content-Type: text/html; charset=UTF-8');
            header('Cache-Control: no-store, max-age=0');
            header('X-Content-Type-Options: nosniff');
            header('Referrer-Policy: no-referrer');
        }

        echo self::render($exception);
    }

    private static function discardOutput(): void
    {
        $target = self::$registered ? self::$baseBufferLevel : 0;

        while (ob_get_level() > $target) {
            ob_end_clean();
        }
    }

    private static function statusCode(?Throwable $exception = null): int
    {
        $status = http_response_code();
        if (is_int($status) && $status >= 400 && $status <= 599) {
            return $status;
        }

        $code = $exception?->getCode();
        if (is_int($code) && $code >= 400 && $code <= 599) {
            return $code;
        }

        return 500;
    }

    private static function title(int $status): string
    {
        return match ($status) {
            401 => '需要登录',
            403 => '无权访问',
            404 => '页面没找到',
            405 => '请求方法不被允许',
            429 => '请求过于频繁',
            503 => '服务暂不可用',
            default => '页面渲染失败',
        };
    }

    private static function description(int $status): string
    {
        return match ($status) {
            401 => '请先登录后再访问该页面。',
            403 => '你没有访问该页面的权限。',
            404 => '你访问的页面不存在，或已经被移动。',
            405 => '当前请求方法不被服务器接受。',
            429 => '请求次数过多，请稍后再试。',
            503 => '服务暂时不可用，请稍后再试。',
            default => '页面在处理请求时遇到问题，请稍后再试。',
        };
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
