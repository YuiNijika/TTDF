<?php

declare(strict_types=1);

namespace TTDF\Http\Rest;

use Closure;

final readonly class Route
{
    public Closure $handler;

    public function __construct(
        public string $method,
        public string $path,
        callable $handler,
        public string $auth,
        public int $priority,
        public int $sequence,
        public string $regex,
        public int $specificity
    ) {
        $this->handler = Closure::fromCallable($handler);
    }

    /**
     * @return array<string, string>|null
     */
    public function params(string $path): ?array
    {
        if (preg_match($this->regex, $path, $matches) !== 1) {
            return null;
        }

        $params = [];
        foreach ($matches as $name => $value) {
            if (is_string($name)) {
                $params[$name] = $value;
            }
        }

        return $params;
    }
}
