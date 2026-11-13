<?php

declare(strict_types=1);

namespace TTDF\Support;

final class Escaper
{
    public static function html(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function attr(?string $value): string
    {
        return self::html($value);
    }

    public static function url(?string $value): string
    {
        $value = trim($value ?? '');
        if ($value === '') {
            return '';
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        if ($scheme !== '' && !in_array($scheme, ['http', 'https', 'mailto'], true)) {
            return '';
        }

        return self::attr($value);
    }

    public static function js(mixed $value): string
    {
        try {
            return Json::encodeScript($value);
        } catch (\Throwable) {
            return 'null';
        }
    }
}
