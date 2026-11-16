<?php

declare(strict_types=1);

namespace TTDF\Http\Rest\V2;

use TTDF\Http\Request;
use TTDF\Http\Response;

/**
 * Small typed reader for v2 request payloads.
 *
 * Every accessor rejects unusable input with a 422 payload instead of letting
 * a wrong type reach the database layer.
 */
final class Payload
{
    /**
     * @return array<string, mixed>
     */
    public static function body(): array
    {
        return Request::json();
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function has(array $body, string $key): bool
    {
        return array_key_exists($key, $body);
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function string(array $body, string $key, string $default = '', int $maxLength = 0): string
    {
        if (!array_key_exists($key, $body) || $body[$key] === null) {
            return $default;
        }

        $value = $body[$key];
        if (is_bool($value)) {
            return $value ? '1' : '';
        }

        if (!is_scalar($value)) {
            self::fail($key, 'must be a string');
        }

        $value = trim((string) $value);
        if ($maxLength > 0 && self::length($value) > $maxLength) {
            self::fail($key, 'must not be longer than ' . $maxLength . ' characters');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function int(array $body, string $key, int $default = 0, ?int $min = null, ?int $max = null): int
    {
        if (!array_key_exists($key, $body) || $body[$key] === null || $body[$key] === '') {
            return $default;
        }

        $value = $body[$key];
        if (is_int($value)) {
            // Already an integer.
        } elseif (is_float($value) && $value === floor($value)) {
            $value = (int) $value;
        } elseif (is_string($value) && preg_match('/^-?\d{1,12}$/', trim($value)) === 1) {
            $value = (int) trim($value);
        } else {
            self::fail($key, 'must be an integer');
        }

        if (($min !== null && $value < $min) || ($max !== null && $value > $max)) {
            self::fail($key, 'is out of range');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function bool(array $body, string $key, bool $default = false): bool
    {
        if (!array_key_exists($key, $body) || $body[$key] === null) {
            return $default;
        }

        $value = $body[$key];
        if (is_bool($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
        }

        self::fail($key, 'must be a boolean');
    }

    /**
     * @param array<string, mixed> $body
     * @return list<string>
     */
    public static function strings(array $body, string $key, int $maxItems = 100, int $maxLength = 150): array
    {
        if (!array_key_exists($key, $body) || $body[$key] === null) {
            return [];
        }

        $value = $body[$key];
        if (is_string($value)) {
            $value = array_filter(array_map('trim', explode(',', $value)), static fn (string $item): bool => $item !== '');
        }

        if (!is_array($value)) {
            self::fail($key, 'must be an array of strings');
        }

        $items = [];
        foreach ($value as $item) {
            if (!is_scalar($item)) {
                self::fail($key, 'must only contain strings');
            }

            $item = trim((string) $item);
            if ($item === '') {
                continue;
            }

            if (self::length($item) > $maxLength) {
                self::fail($key, 'contains an item that is too long');
            }

            $items[] = $item;
            if (count($items) > $maxItems) {
                self::fail($key, 'contains too many items');
            }
        }

        return array_values(array_unique($items));
    }

    /**
     * @param array<string, mixed> $body
     * @return list<int>
     */
    public static function ints(array $body, string $key, int $maxItems = 100): array
    {
        if (!array_key_exists($key, $body) || $body[$key] === null) {
            return [];
        }

        $value = $body[$key];
        if (!is_array($value)) {
            self::fail($key, 'must be an array of integers');
        }

        $items = [];
        foreach ($value as $item) {
            if (is_bool($item) || !is_numeric($item)) {
                self::fail($key, 'must only contain integers');
            }

            $item = (int) $item;
            if ($item <= 0) {
                continue;
            }

            $items[] = $item;
            if (count($items) > $maxItems) {
                self::fail($key, 'contains too many items');
            }
        }

        return array_values(array_unique($items));
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, string>
     */
    public static function map(array $body, string $key): array
    {
        if (!array_key_exists($key, $body) || $body[$key] === null) {
            return [];
        }

        if (!is_array($body[$key])) {
            self::fail($key, 'must be an object');
        }

        $result = [];
        foreach ($body[$key] as $name => $value) {
            if (!is_string($name) || preg_match('/^[_a-z][_a-z0-9]*$/i', $name) !== 1) {
                continue;
            }

            if (!is_scalar($value)) {
                continue;
            }

            $result[$name] = (string) $value;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function require(array $body, string ...$keys): void
    {
        $errors = [];
        foreach ($keys as $key) {
            $value = $body[$key] ?? null;
            if ($value === null || (is_string($value) && trim($value) === '') || $value === []) {
                $errors[$key] = 'is required';
            }
        }

        if ($errors !== []) {
            Response::error('Invalid request body', 422, $errors);
        }
    }

    /**
     * @param array<string, mixed> $input
     * @param list<string> $allowed
     * @return array<string, mixed>
     */
    public static function only(array $input, array $allowed): array
    {
        $result = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $input)) {
                $result[$key] = $input[$key];
            }
        }

        return $result;
    }

    public static function clamp(int $value, int $min, int $max, int $default): int
    {
        if ($value <= 0) {
            return $default;
        }

        return max($min, min($max, $value));
    }

    private static function fail(string $key, string $message): never
    {
        Response::error('Invalid request body', 422, [$key => $message]);
    }

    private static function length(string $value): int
    {
        return function_exists('mb_strlen') ? (int) mb_strlen($value) : strlen($value);
    }
}
