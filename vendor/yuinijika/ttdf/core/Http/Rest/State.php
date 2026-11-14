<?php

declare(strict_types=1);

namespace TTDF\Http\Rest;

use TTDF\Theme\ThemeConfig;

final class State
{
    public static function enabled(): bool
    {
        return ThemeConfig::restEnabled();
    }

    public static function tokenEnv(): string
    {
        return ThemeConfig::tokenEnv();
    }

    /**
     * @return list<string>
     */
    public static function allowedOrigins(): array
    {
        return ThemeConfig::corsOrigins();
    }
}
