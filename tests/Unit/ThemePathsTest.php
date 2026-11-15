<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TTDF\Config\Config;
use TTDF\Theme\Options;
use TTDF\Theme\Paths;
use TTDF\Theme\ThemeConfig;

final class ThemePathsTest extends TestCase
{
    public function testRepositoryUsesConstrainedThemeLayout(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertFileExists($root . '/functions.php');
        self::assertFileExists(Paths::appConfig($root));
        self::assertFileExists(Paths::settings($root));
        self::assertFileExists(Paths::themeFields($root));
        self::assertDirectoryExists(Paths::router($root));
        self::assertFileDoesNotExist($root . '/app/app.config.php');
        self::assertFileDoesNotExist($root . '/app/config/setup.php');
        self::assertFileExists($root . '/app/config/fields.php');
        self::assertFileDoesNotExist($root . '/app/setup.php');
        self::assertDirectoryExists(Paths::pages($root));
        self::assertFileExists($root . '/index.php');
        self::assertFileDoesNotExist($root . '/archive.php');
        self::assertFileDoesNotExist($root . '/post.php');
        self::assertFileDoesNotExist($root . '/page.php');
        self::assertFileDoesNotExist($root . '/404.php');
        self::assertFileDoesNotExist($root . '/comments.php');
    }

    public function testThemeConfigUsesStaticRuntimeDefaultsAndIgnoresStaticAssets(): void
    {
        Config::load([
            'debug' => true,
            'rest' => [
                'enabled' => true,
                'token_env' => 'CUSTOM_REST_TOKEN',
                'cors' => ['allowed_origins' => ['https://a.test']],
            ],
            'assets' => ['cdn' => 'https://cdn.example.test/base'],
        ]);
        Options::reset();
        ThemeConfig::reset();

        try {
            self::assertTrue(ThemeConfig::debug());
            self::assertTrue(ThemeConfig::restEnabled());
            self::assertSame('CUSTOM_REST_TOKEN', ThemeConfig::tokenEnv());
            self::assertSame(['https://a.test'], ThemeConfig::corsOrigins());
            self::assertSame('', ThemeConfig::assetsCdn());
        } finally {
            Config::reset();
            Options::reset();
            ThemeConfig::reset();
        }
    }

    public function testAssetsCdnComesFromThemeSettingOnly(): void
    {
        $property = new \ReflectionProperty(Options::class, 'instance');
        $property->setValue(null, new Options(new InMemoryOptionsStore([
            'Theme_Assets_CDN' => '"https://cdn.example.test/theme/"',
        ]), 'Theme'));

        Config::load(['assets' => ['cdn' => 'https://ignored.example.test/']]);
        ThemeConfig::reset();

        try {
            self::assertSame('https://cdn.example.test/theme/', ThemeConfig::assetsCdn());
        } finally {
            Config::reset();
            Options::reset();
            ThemeConfig::reset();
        }
    }
}
