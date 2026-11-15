<?php

declare(strict_types=1);

namespace TTDF\Http\Rest;

use TTDF\Http\Request;

final readonly class RouteRequest
{
    /**
     * @param array<string, string> $params
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $params,
        public string $scope
    ) {
    }

    public function param(string $name, string $default = ''): string
    {
        return $this->params[$name] ?? $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return Request::query($key, $default);
    }

    public function queryString(string $key, string $default = ''): string
    {
        return Request::queryString($key, $default);
    }

    public function queryInt(string $key, int $default = 0): int
    {
        return Request::queryInt($key, $default);
    }

    /**
     * @return array<string, mixed>
     */
    public function json(): array
    {
        return Request::json();
    }
}
