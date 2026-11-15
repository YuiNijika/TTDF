<?php

declare(strict_types=1);

namespace TTDF\Http\Rest;

final readonly class RouteMatch
{
    /**
     * @param array<string, string> $params
     */
    public function __construct(
        public Route $route,
        public array $params
    ) {
    }
}
