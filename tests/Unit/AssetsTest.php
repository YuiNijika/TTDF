<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TTDF\Config\Config;
use TTDF\Theme\Assets;
use TTDF\Theme\Options;
use TTDF\Theme\ThemeConfig;

final class AssetsTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load([]);
        Options::reset();
        ThemeConfig::reset();
    }

    protected function tearDown(): void
    {
        Config::reset();
        Options::reset();
        ThemeConfig::reset();
    }

    public function testThemeUrlIncludesVersionQuery(): void
    {
        self::assertSame(
            'https://example.test/usr/themes/ttdf/assets/app.css?v=5.0.0-next',
            Assets::url('assets/app.css')
        );
    }

    public function testCdnPrefixIsReadFromThemeSettingWhenItIsHttp(): void
    {
        $this->useThemeCdn('https://cdn.example.test/base/');

        self::assertSame(
            'https://cdn.example.test/base/assets/app.css?v=5.0.0-next',
            Assets::url('/assets/app.css')
        );
    }

    public function testUnsafeCdnSchemeFallsBackToThemeUrl(): void
    {
        $this->useThemeCdn('javascript:alert(1)');

        self::assertSame(
            'https://example.test/usr/themes/ttdf/assets/app.css?v=5.0.0-next',
            Assets::url('assets/app.css')
        );
    }

    public function testStaticAssetsConfigurationIsIgnored(): void
    {
        Config::load(['assets' => ['cdn' => 'https://ignored.example.test/base/']]);

        self::assertSame(
            'https://example.test/usr/themes/ttdf/assets/app.css?v=5.0.0-next',
            Assets::url('assets/app.css')
        );
    }

    public function testStaticUrlPointsAtVendoredFrameworkAssets(): void
    {
        self::assertSame(
            'https://example.test/usr/themes/ttdf/vendor/yuinijika/ttdf/static/options/options.css?v=5.0.0-next',
            Assets::staticUrl('options/options.css')
        );
    }

    public function testPrintHelpersEscapeAndEmitTags(): void
    {
        ob_start();
        Assets::printStyles(['assets/app.css']);
        Assets::printScripts(['assets/main.js']);
        $html = (string) ob_get_clean();

        self::assertStringContainsString(
            '<link rel="stylesheet" href="https://example.test/usr/themes/ttdf/assets/app.css?v=5.0.0-next">',
            $html
        );
        self::assertStringContainsString(
            '<script src="https://example.test/usr/themes/ttdf/assets/main.js?v=5.0.0-next"></script>',
            $html
        );
    }

    private function useThemeCdn(string $cdn): void
    {
        $property = new \ReflectionProperty(Options::class, 'instance');
        $property->setValue(null, new Options(new InMemoryOptionsStore([
            'Theme_Assets_CDN' => json_encode($cdn, JSON_THROW_ON_ERROR),
        ]), 'Theme'));
        ThemeConfig::reset();
    }
}
