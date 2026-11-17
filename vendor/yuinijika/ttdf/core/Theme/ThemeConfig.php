<?php

declare(strict_types=1);

namespace TTDF\Theme;

use TTDF\Config\Config;
use TTDF\Http\Rest\Prefixes;

/**
 * Theme-facing runtime configuration.
 *
 * Values from app/config/app.php provide runtime defaults for debug, REST,
 * CORS, token enforcement and the token environment. The asset CDN is read from the theme
 * setting only; saved theme settings take precedence where both sources exist.
 */
final class ThemeConfig
{
    /** @var array<string, mixed> */
    private static array $cache = [];

    public static function debug(): bool
    {
        $value = self::setting('Debug_Switch');

        return (bool) Config::get('debug', false)
            || (self::isConfigured($value) && self::toBool($value));
    }

    public static function restEnabled(): bool
    {
        $value = self::setting('RESTAPI_Switch');

        return (bool) Config::get('rest.enabled', false)
            || (self::isConfigured($value) && self::toBool($value));
    }

    public static function tokenEnabled(): bool
    {
        $value = self::setting('REST_Token_Switch');
        if (self::isConfigured($value)) {
            return self::toBool($value);
        }

        return self::toBool(Config::get('rest.token_enabled', false));
    }

    /**
     * The v2 API can write to the site, so it has its own child switch. The
     * public REST switch is the master switch for both v1 and v2; turning it
     * off must also unregister the write-capable v2 routes.
     */
    public static function restV2Enabled(): bool
    {
        $value = self::setting('RESTV2_Switch');

        return self::restEnabled()
            && ((bool) Config::get('rest.v2_enabled', false)
                || (self::isConfigured($value) && self::toBool($value)));
    }

    public static function restV2TokenTtl(): int
    {
        $value = self::setting('RESTV2_Token_TTL');
        if (is_numeric($value)) {
            return (int) $value;
        }

        $configured = Config::get('rest.v2_token_ttl', 0);

        return is_numeric($configured) ? (int) $configured : 0;
    }

    public static function restV1Prefix(): string
    {
        return Prefixes::v1();
    }

    public static function restV2Prefix(): string
    {
        return Prefixes::v2();
    }

    public static function tokenEnv(): string
    {
        $value = self::setting('REST_Token_Env');
        if (is_string($value)) {
            return trim($value);
        }

        return (string) Config::get('rest.token_env', 'TTDF_REST_TOKEN');
    }

    /**
     * @return list<string>
     */
    public static function corsOrigins(): array
    {
        $configured = Config::get('rest.cors.allowed_origins', []);
        $origins = is_array($configured)
            ? array_values(array_filter($configured, 'is_string'))
            : [];

        $stored = self::setting('CORS_Origins');
        if (is_string($stored)) {
            foreach (preg_split('/\R/', $stored) ?: [] as $origin) {
                $origin = trim($origin);
                if ($origin !== '') {
                    $origins[] = $origin;
                }
            }
        }

        return array_values(array_unique($origins));
    }

    public static function assetsCdn(): string
    {
        $value = self::setting('Assets_CDN');

        return is_string($value) ? trim($value) : '';
    }

    public static function reset(): void
    {
        self::$cache = [];
    }

    private static function setting(string $name): mixed
    {
        if (array_key_exists($name, self::$cache)) {
            return self::$cache[$name];
        }

        try {
            $value = Options::instance()->get($name, null);
        } catch (\Throwable) {
            $value = null;
        }

        return self::$cache[$name] = $value;
    }

    private static function isConfigured(mixed $value): bool
    {
        return is_bool($value)
            || (is_scalar($value) && (string) $value !== '');
    }

    private static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return is_scalar($value)
            && in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }
}
