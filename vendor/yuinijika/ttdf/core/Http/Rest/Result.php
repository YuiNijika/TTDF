<?php

declare(strict_types=1);

namespace TTDF\Http\Rest;

use InvalidArgumentException;

final readonly class Result
{
    /**
     * @param array<mixed> $data
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $errors
     */
    private function __construct(
        public array $data,
        public array $meta,
        public int $status,
        public bool $empty,
        public ?string $error,
        public array $errors
    ) {
    }

    /**
     * @param array<mixed> $data
     * @param array<string, mixed> $meta
     */
    public static function success(array $data, array $meta = [], int $status = 200): self
    {
        if ($status < 200 || $status > 299 || $status === 204) {
            throw new InvalidArgumentException('REST result status must be a non-empty 2xx status');
        }

        return new self($data, $meta, $status, false, null, []);
    }

    public static function noContent(int $status = 204): self
    {
        if ($status < 200 || $status > 299) {
            throw new InvalidArgumentException('REST no-content status must be 2xx');
        }

        return new self([], [], $status, true, null, []);
    }

    /**
     * @param array<string, mixed> $errors
     */
    public static function error(string $message, int $status, array $errors = []): self
    {
        if ($status < 400 || $status > 599) {
            throw new InvalidArgumentException('REST error status must be 4xx or 5xx');
        }

        return new self([], [], $status, false, $message, $errors);
    }
}
