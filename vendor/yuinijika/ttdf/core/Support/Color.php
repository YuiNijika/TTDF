<?php

declare(strict_types=1);

namespace TTDF\Support;

/**
 * Hex color normalization and derivation.
 *
 * Theme settings accept a color picker value, and that value ends up in a
 * style block. Everything is therefore reduced to a plain `#rrggbb` token,
 * so a tampered settings JSON can never inject CSS.
 */
final class Color
{
    public const LIGHT_TEXT = '#ffffff';
    public const DARK_TEXT = '#0f1115';

    /**
     * Accepts #rgb, #rgba, #rrggbb, #rrggbbaa and rgb()/rgba() notation.
     * Returns an empty string when the value cannot be trusted.
     */
    public static function normalize(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $raw = strtolower(trim((string) $value));
        if ($raw === '') {
            return '';
        }

        if (preg_match('/^#([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/', $raw, $matches) === 1) {
            return self::fromHex($matches[1]);
        }

        if (preg_match('/^rgba?\(([^)]*)\)$/', $raw, $matches) === 1) {
            return self::fromRgbExpression($matches[1]);
        }

        return '';
    }

    public static function isDark(string $color): bool
    {
        $rgb = self::channels($color);
        if ($rgb === null) {
            return false;
        }

        $linear = static function (int $channel): float {
            $value = $channel / 255;

            return $value <= 0.03928
                ? $value / 12.92
                : (($value + 0.055) / 1.055) ** 2.4;
        };

        $luminance = 0.2126 * $linear($rgb[0]) + 0.7152 * $linear($rgb[1]) + 0.0722 * $linear($rgb[2]);

        return $luminance < 0.179;
    }

    /**
     * Readable text color for a solid background.
     */
    public static function contrast(string $color): string
    {
        return self::isDark($color) ? self::LIGHT_TEXT : self::DARK_TEXT;
    }

    /**
     * Blend toward a target color; ratio 0 keeps the input, 1 returns target.
     */
    public static function mix(string $color, string $target, float $ratio): string
    {
        $from = self::channels($color);
        $to = self::channels($target);
        if ($from === null || $to === null) {
            return '';
        }

        $ratio = max(0.0, min(1.0, $ratio));
        $mixed = [];
        for ($index = 0; $index < 3; $index++) {
            $mixed[] = (int) round($from[$index] + ($to[$index] - $from[$index]) * $ratio);
        }

        return sprintf('#%02x%02x%02x', $mixed[0], $mixed[1], $mixed[2]);
    }

    /**
     * RGBA token used for translucent accents and focus rings.
     */
    public static function alpha(string $color, float $alpha): string
    {
        $rgb = self::channels($color);
        if ($rgb === null) {
            return '';
        }

        $alpha = max(0.0, min(1.0, $alpha));

        return sprintf(
            'rgba(%d, %d, %d, %s)',
            $rgb[0],
            $rgb[1],
            $rgb[2],
            rtrim(rtrim(number_format($alpha, 3, '.', ''), '0'), '.') ?: '0'
        );
    }

    private static function fromHex(string $hex): string
    {
        if (strlen($hex) === 3 || strlen($hex) === 4) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return '#' . substr($hex, 0, 6);
    }

    private static function fromRgbExpression(string $expression): string
    {
        $parts = array_map('trim', explode(',', $expression));
        if (count($parts) < 3 || count($parts) > 4) {
            return '';
        }

        if (isset($parts[3]) && preg_match('/^[0-9]+(\.[0-9]+)?%?$/', $parts[3]) !== 1) {
            return '';
        }

        $channels = [];
        for ($index = 0; $index < 3; $index++) {
            if (preg_match('/^[0-9]{1,3}$/', $parts[$index]) !== 1) {
                return '';
            }

            $value = (int) $parts[$index];
            if ($value > 255) {
                return '';
            }

            $channels[] = $value;
        }

        return sprintf('#%02x%02x%02x', $channels[0], $channels[1], $channels[2]);
    }

    /**
     * @return array{0: int, 1: int, 2: int}|null
     */
    private static function channels(string $color): ?array
    {
        $normalized = self::normalize($color);
        if ($normalized === '') {
            return null;
        }

        $hex = substr($normalized, 1);

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }
}
