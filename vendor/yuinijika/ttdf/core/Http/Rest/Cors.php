<?php

declare(strict_types=1);

namespace TTDF\Http\Rest;

use TTDF\Http\Response;

final class Cors
{
    public static function apply(): void
    {
        $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
        if ($origin === '' || !self::isAllowed($origin)) {
            return;
        }

        if (!headers_sent()) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
            header('Access-Control-Allow-Methods: GET, OPTIONS');
            header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With');
            header('Access-Control-Max-Age: 600');
        }
    }

    public static function preflight(): never
    {
        self::apply();
        Response::noContent();
    }

    public static function isAllowed(string $origin): bool
    {
        if (preg_match('#^https?://[A-Za-z0-9.\-]+(:\d{1,5})?$#', $origin) !== 1) {
            return false;
        }

        foreach (State::allowedOrigins() as $candidate) {
            if ($candidate !== '' && hash_equals($candidate, $origin)) {
                return true;
            }
        }

        return false;
    }
}
