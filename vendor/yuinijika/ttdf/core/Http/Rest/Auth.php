<?php

declare(strict_types=1);

namespace TTDF\Http\Rest;

use TTDF\Compat\Typecho;
use TTDF\Config\Config;
use TTDF\Http\Request;
use TTDF\Http\Response;

final class Auth
{
    public static function requireAdmin(): void
    {
        if (!Typecho::isAdmin()) {
            Response::error('Unauthorized', 401);
        }
    }

    public static function requireNonce(): void
    {
        $token = Request::header('X-Typecho-Token');

        if (!Typecho::checkToken($token)) {
            Response::error('Invalid security token', 403);
        }
    }

    public static function requireSameOrigin(): void
    {
        if (!self::isSameOrigin(Request::header('Origin'))) {
            Response::error('Invalid origin', 403);
        }
    }

    public static function isSameOrigin(string $origin): bool
    {
        $origin = strtolower(trim($origin));
        if ($origin === '') {
            return true;
        }

        $site = Typecho::siteUrl();
        $scheme = parse_url($site, PHP_URL_SCHEME);
        $host = parse_url($site, PHP_URL_HOST);
        $port = parse_url($site, PHP_URL_PORT);
        if (!is_string($scheme) || !is_string($host)) {
            return false;
        }

        $defaultPort = ($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80);
        $suffix = is_int($port) && !$defaultPort ? ':' . $port : '';
        $expected = strtolower($scheme . '://' . $host . $suffix);

        return hash_equals($expected, $origin);
    }

    public static function requirePublicAccess(): void
    {
        if (!State::enabled()) {
            Response::error('REST API is disabled', 404);
        }

        if (!State::tokenEnabled()) {
            return;
        }

        $envName = State::tokenEnv();
        $expected = $envName !== '' ? (string) getenv($envName) : '';
        if ($envName === '' || $expected === '' || strlen($expected) < 32) {
            Response::error('REST token is misconfigured', 500);
        }

        $provided = self::bearerToken();
        if ($provided === '' || !hash_equals($expected, $provided)) {
            Response::error('Invalid token', 401);
        }
    }

    public static function bearerToken(): string
    {
        $header = Request::header('Authorization');
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches) === 1) {
            return trim($matches[1]);
        }

        return '';
    }
}
