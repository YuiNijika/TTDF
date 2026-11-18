<?php

declare(strict_types=1);

namespace TTDF\Http\Rest\V2;

use RuntimeException;

final class RateLimitException extends RuntimeException
{
    public function __construct(private readonly int $retryAfter)
    {
        parent::__construct('Verification code requested too frequently');
    }

    public function retryAfter(): int
    {
        return max(1, $this->retryAfter);
    }
}
