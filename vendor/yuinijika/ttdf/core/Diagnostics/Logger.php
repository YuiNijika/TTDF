<?php

declare(strict_types=1);

namespace TTDF\Diagnostics;

final class Logger
{
    private const MAX_BYTES = 5_242_880;
    private const KEEP_FILES = 3;

    /** @var array<int, string> */
    private const REDACT_KEYS = [
        'password',
        'passwd',
        'token',
        'authorization',
        'cookie',
        'set-cookie',
        'mail',
        'email',
        'ip',
        'secret',
        'api_key',
        'apikey',
        'access_token',
        'refresh_token',
        'session',
        'sessionid',
    ];

    public function __construct(private readonly string $file)
    {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function write(string $level, string $message, array $context = []): void
    {
        $directory = dirname($this->file);
        if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
            return;
        }

        $this->rotate();

        $line = sprintf(
            "[%s] %s: %s",
            date('c'),
            strtoupper($level),
            str_replace(["\r", "\n"], ' ', $message)
        );

        if ($context !== []) {
            $line .= ' ' . $this->encode($this->redact($context));
        }

        $line .= PHP_EOL;
        $written = @file_put_contents($this->file, $line, FILE_APPEND | LOCK_EX);
        if ($written !== false) {
            @chmod($this->file, 0640);
        }
    }

    private function rotate(): void
    {
        if (!is_file($this->file) || (int) @filesize($this->file) < self::MAX_BYTES) {
            return;
        }

        for ($index = self::KEEP_FILES - 1; $index >= 1; $index--) {
            $source = $this->file . '.' . $index;
            $target = $this->file . '.' . ($index + 1);
            if (is_file($source)) {
                @rename($source, $target);
            }
        }

        @rename($this->file, $this->file . '.1');
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function redact(array $context): array
    {
        $result = [];
        foreach ($context as $key => $value) {
            $lower = strtolower((string) $key);
            if (
                in_array($lower, self::REDACT_KEYS, true)
                || preg_match('/(?:^|_)(?:token|secret|password|passwd|api_?key)$/', $lower) === 1
            ) {
                $result[$key] = '[redacted]';
                continue;
            }

            $result[$key] = is_array($value) ? $this->redact($value) : $value;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function encode(array $context): string
    {
        try {
            $encoded = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return '[unserializable context]';
        }

        return strlen($encoded) > 4000 ? substr($encoded, 0, 4000) . '...' : $encoded;
    }
}
