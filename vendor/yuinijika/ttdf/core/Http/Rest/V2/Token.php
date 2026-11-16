<?php

declare(strict_types=1);

namespace TTDF\Http\Rest\V2;

use TTDF\Compat\Typecho;

/**
 * Stateless session tokens for the v2 API.
 *
 * The signature key is derived from the user's `authCode`, so a password change
 * or an explicit "logout everywhere" invalidates every token that was issued
 * before it, without storing anything in the database.
 */
final class Token
{
    public const DEFAULT_TTL = 604800;

    public const MIN_TTL = 300;

    public const MAX_TTL = 2592000;

    private const PREFIX = 'ttdfv2';

    private const CONTEXT = 'ttdf-rest-v2';

    private const CLOCK_SKEW = 300;

    /**
     * @return array{token: string, expiresAt: int}
     */
    public static function issue(int $uid, string $authCode, int $ttl): array
    {
        $now = time();
        $expiresAt = $now + self::clampTtl($ttl);
        $payload = self::encode($uid . '.' . $expiresAt . '.' . $now);

        return [
            'token' => self::PREFIX . '.' . $payload . '.' . self::encode(self::sign($payload, $uid, $authCode)),
            'expiresAt' => $expiresAt,
        ];
    }

    /**
     * Read the claims without trusting them; callers must verify the signature
     * against the current `authCode` before the claims mean anything.
     *
     * @return array{uid: int, expiresAt: int, issuedAt: int, payload: string, signature: string}|null
     */
    public static function claims(string $token): ?array
    {
        $token = trim($token);
        if ($token === '' || strlen($token) > 512) {
            return null;
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3 || $parts[0] !== self::PREFIX) {
            return null;
        }

        $payload = self::decode($parts[1]);
        if ($payload === null || preg_match('/^(\d{1,10})\.(\d{1,10})\.(\d{1,10})$/', $payload, $matches) !== 1) {
            return null;
        }

        $signature = self::decode($parts[2]);
        if ($signature === null || $signature === '') {
            return null;
        }

        return [
            'uid' => (int) $matches[1],
            'expiresAt' => (int) $matches[2],
            'issuedAt' => (int) $matches[3],
            'payload' => $parts[1],
            'signature' => $signature,
        ];
    }

    /**
     * @param array{uid: int, expiresAt: int, issuedAt: int, payload: string, signature: string} $claims
     */
    public static function verify(array $claims, string $authCode): bool
    {
        if ($authCode === '' || $claims['uid'] <= 0) {
            return false;
        }

        $expected = self::sign($claims['payload'], $claims['uid'], $authCode);

        return hash_equals($expected, $claims['signature']);
    }

    /**
     * @param array{uid: int, expiresAt: int, issuedAt: int} $claims
     */
    public static function expired(array $claims): bool
    {
        return $claims['expiresAt'] <= time() || $claims['issuedAt'] > time() + self::CLOCK_SKEW;
    }

    public static function clampTtl(int $ttl): int
    {
        if ($ttl <= 0) {
            return self::DEFAULT_TTL;
        }

        return max(self::MIN_TTL, min(self::MAX_TTL, $ttl));
    }

    private static function sign(string $payload, int $uid, string $authCode): string
    {
        $key = hash('sha256', Typecho::siteUrl() . '|' . $uid . '|' . $authCode . '|' . self::CONTEXT);

        return hash_hmac('sha256', $payload, $key, true);
    }

    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function decode(string $value): ?string
    {
        $value = strtr($value, '-_', '+/');
        $padding = strlen($value) % 4;
        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($value, true);

        return is_string($decoded) ? $decoded : null;
    }
}
