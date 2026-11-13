<?php

declare(strict_types=1);

namespace TTDF\Config;

final class Config
{
    /** @var array<string, mixed> */
    private static array $items = [];

    private static bool $loaded = false;

    /**
     * @param array<string, mixed> $items
     */
    public static function load(array $items): void
    {
        self::$items = $items;
        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if ($key === '') {
            return self::$items;
        }

        $value = self::$items;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public static function has(string $key): bool
    {
        $sentinel = new \stdClass();
        return self::get($key, $sentinel) !== $sentinel;
    }

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return self::$items;
    }

    public static function isLoaded(): bool
    {
        return self::$loaded;
    }

    public static function reset(): void
    {
        self::$items = [];
        self::$loaded = false;
    }
}
