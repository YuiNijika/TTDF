<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TTDF\Theme\Paths;
use TTDF\Theme\ThemeConfig;

final class ThemePathsTest extends TestCase
{
    public function testRepositoryUsesConstrainedThemeLayout(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertFileExists($root . '/functions.php');
        self::assertFileExists(Paths::appConfig($root));
        self::assertFileExists(Paths::setup($root));
        self::assertFileExists(Paths::themeFields($root));
        self::assertDirectoryExists(Paths::router($root));
        self::assertFileDoesNotExist($root . '/app/app.config.php');
        self::assertFileDoesNotExist($root . '/app/fields.php');
        self::assertFileDoesNotExist($root . '/app/setup.php');
        self::assertDirectoryDoesNotExist($root . '/app/pages');
    }

    public function testThemeConfigFallsBackToStaticConfiguration(): void
    {
        \TTDF\Config\Config::load([
            'debug' => true,
            'rest' => [
                'enabled' => true,
                'token_env' => 'CUSTOM_REST_TOKEN',
                'cors' => ['allowed_origins' => ['https://a.test']],
            ],
            'assets' => ['cdn' => 'https://cdn.example.test/base'],
        ]);
        \TTDF\Theme\Options::reset();
        ThemeConfig::reset();

        try {
            self::assertTrue(ThemeConfig::debug());
            self::assertTrue(ThemeConfig::restEnabled());
            self::assertSame('CUSTOM_REST_TOKEN', ThemeConfig::tokenEnv());
            self::assertSame(['https://a.test'], ThemeConfig::corsOrigins());
            self::assertSame('https://cdn.example.test/base', ThemeConfig::assetsCdn());
        } finally {
            \TTDF\Config\Config::reset();
            \TTDF\Theme\Options::reset();
            ThemeConfig::reset();
        }
    }
}
