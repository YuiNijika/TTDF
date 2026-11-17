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
        self::assertFileDoesNotExist($root . '/app/pages/comments.php');
        self::assertFileExists($root . '/app/components/content/Comments.php');
        self::assertFileExists($root . '/app/components/layout/AppHeader.php');
        self::assertFileExists($root . '/app/components/layout/AppFooter.php');
        self::assertFileDoesNotExist($root . '/app/components/Comments.php');
        self::assertFileDoesNotExist($root . '/app/components/AppHeader.php');
        self::assertFileExists($root . '/assets/style.css');
        self::assertFileDoesNotExist($root . '/assets/css/style.css');
    }

    public function testCommentsRenderOnlyThroughTheComponentLoader(): void
    {
        $root = dirname(__DIR__, 2);

        foreach ([
            'app/pages/post.php',
            'app/pages/page.php',
            'app/components/content/Post.php',
            'app/components/content/Page.php',
        ] as $file) {
            $source = file_get_contents($root . '/' . $file);
            self::assertIsString($source);
            self::assertStringNotContainsString("need('comments.php')", $source);
            self::assertStringContainsString("'content/Comments'", $source);
        }

        $component = file_get_contents($root . '/app/components/content/Comments.php');
        self::assertIsString($component);
        self::assertStringContainsString("\$viewData['archive']", $component);
        self::assertStringContainsString('ttdf-comment-form', $component);
    }

    public function testThemeConfigUsesStaticRuntimeDefaultsAndIgnoresStaticAssets(): void
    {
        Config::load([
            'debug' => true,
            'rest' => [
                'enabled' => true,
                'token_enabled' => true,
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
            self::assertTrue(ThemeConfig::tokenEnabled());
            self::assertSame('CUSTOM_REST_TOKEN', ThemeConfig::tokenEnv());
            self::assertSame(['https://a.test'], ThemeConfig::corsOrigins());
            self::assertSame('', ThemeConfig::assetsCdn());
        } finally {
            Config::reset();
            Options::reset();
            ThemeConfig::reset();
        }
    }

    public function testTokenSwitchSettingOverridesStaticDefault(): void
    {
        Config::load([
            'rest' => [
                'enabled' => true,
                'token_enabled' => true,
            ],
        ]);
        ThemeConfig::reset();

        try {
            self::assertTrue(ThemeConfig::tokenEnabled());

            $property = new \ReflectionProperty(Options::class, 'instance');
            $property->setValue(null, new Options(new InMemoryOptionsStore([
                'Theme' => '{"REST_Token_Switch":false}',
            ]), 'Theme'));
            ThemeConfig::reset();

            self::assertFalse(ThemeConfig::tokenEnabled());

            $property->setValue(null, new Options(new InMemoryOptionsStore([
                'Theme' => '{"REST_Token_Switch":true}',
            ]), 'Theme'));
            ThemeConfig::reset();

            self::assertTrue(ThemeConfig::tokenEnabled());
        } finally {
            Config::reset();
            Options::reset();
            ThemeConfig::reset();
        }
    }

    public function testPublicRestSwitchMastersBothApiVersions(): void
    {
        Config::load([
            'rest' => [
                'enabled' => false,
                'v2_enabled' => true,
            ],
        ]);
        ThemeConfig::reset();

        try {
            self::assertFalse(ThemeConfig::restEnabled());
            self::assertFalse(ThemeConfig::restV2Enabled());

            Config::load([
                'rest' => [
                    'enabled' => true,
                    'v2_enabled' => false,
                ],
            ]);
            ThemeConfig::reset();
            self::assertTrue(ThemeConfig::restEnabled());
            self::assertFalse(ThemeConfig::restV2Enabled());

            $property = new \ReflectionProperty(Options::class, 'instance');
            $property->setValue(null, new Options(new InMemoryOptionsStore([
                'Theme' => '{"RESTAPI_Switch":true,"RESTV2_Switch":true}',
            ]), 'Theme'));
            ThemeConfig::reset();
            self::assertTrue(ThemeConfig::restV2Enabled());
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
            'Theme' => '{"Assets_CDN":"https://cdn.example.test/theme/"}',
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
