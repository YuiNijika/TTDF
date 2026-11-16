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
     * @return mixed
     */
    private function redact(mixed $value, int $depth = 0): mixed
    {
        if ($depth >= 8) {
            return is_object($value) ? '[object:' . $value::class . ']' : '[max-depth]';
        }

        if (is_object($value)) {
            if ($value instanceof \Throwable) {
                return [
                    'class' => $value::class,
                    'code' => $value->getCode(),
                ];
            }

            return [
                'class' => $value::class,
                'properties' => $this->redact(get_object_vars($value), $depth + 1),
            ];
        }

        if (!is_array($value)) {
            return $value;
        }

        $result = [];
        foreach ($value as $key => $item) {
            $lower = strtolower((string) $key);
            if ($this->isSensitiveKey($lower)) {
                $result[$key] = '[redacted]';
                continue;
            }

            $result[$key] = $this->redact($item, $depth + 1);
        }

        return $result;
    }

    private function isSensitiveKey(string $key): bool
    {
        if (in_array($key, self::REDACT_KEYS, true)) {
            return true;
        }

        foreach (['token', 'secret', 'password', 'passwd', 'apikey', 'api_key', 'email'] as $suffix) {
            if (str_ends_with($key, $suffix)) {
                return true;
            }
        }

        return false;
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
