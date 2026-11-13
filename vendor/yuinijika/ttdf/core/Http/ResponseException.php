<?php

declare(strict_types=1);

namespace TTDF\Http;

use RuntimeException;

/**
 * Thrown instead of exiting when Response capture mode is enabled.
 *
 * This exists so controllers that terminate the request can still be covered
 * by PHPUnit. Production requests never enable capture mode.
 */
final class ResponseException extends RuntimeException
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private readonly array $payload,
        private readonly int $status
    ) {
        parent::__construct((string) ($payload['message'] ?? 'Response captured'), $status);
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    public function status(): int
    {
        return $this->status;
    }
}
