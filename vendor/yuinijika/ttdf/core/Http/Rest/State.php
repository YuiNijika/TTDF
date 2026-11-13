<?php

declare(strict_types=1);

namespace TTDF\Http\Rest;

use TTDF\Config\Config;
use TTDF\Theme\Options;

final class State
{
    public static function enabled(): bool
    {
        if ((bool) Config::get('rest.enabled', false)) {
            return true;
        }

        try {
            return (bool) Options::instance()->get('RESTAPI_Switch', false);
        } catch (\Throwable) {
            return false;
        }
    }

    public static function tokenEnv(): string
    {
        try {
            $stored = Options::instance()->get('REST_Token_Env', null);
            if (is_string($stored)) {
                return $stored;
            }
        } catch (\Throwable) {
            // Fall back to configuration.
        }

        return (string) Config::get('rest.token_env', 'TTDF_REST_TOKEN');
    }

    /**
     * @return list<string>
     */
    public static function allowedOrigins(): array
    {
        $origins = Config::get('rest.cors.allowed_origins', []);
        $origins = is_array($origins) ? array_values(array_filter($origins, 'is_string')) : [];

        try {
            $stored = Options::instance()->get('CORS_Origins', '');
            if (is_string($stored)) {
                foreach (preg_split('/\R/', $stored) ?: [] as $origin) {
                    $origin = trim($origin);
                    if ($origin !== '') {
                        $origins[] = $origin;
                    }
                }
            }
        } catch (\Throwable) {
            // Configuration-only CORS is still valid.
        }

        return array_values(array_unique($origins));
    }
}
