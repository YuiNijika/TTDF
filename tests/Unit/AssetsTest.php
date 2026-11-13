<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TTDF\Config\Config;
use TTDF\Theme\Assets;

final class AssetsTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load([]);
    }

    protected function tearDown(): void
    {
        Config::reset();
    }

    public function testThemeUrlIncludesVersionQuery(): void
    {
        self::assertSame(
            'https://example.test/usr/themes/ttdf/assets/app.css?v=5.0.0-next',
            Assets::url('assets/app.css')
        );
    }

    public function testCdnPrefixIsUsedWhenItIsHttp(): void
    {
        Config::load(['assets' => ['cdn' => 'https://cdn.example.test/base/']]);

        self::assertSame(
            'https://cdn.example.test/base/assets/app.css?v=5.0.0-next',
            Assets::url('/assets/app.css')
        );
    }

    public function testUnsafeCdnSchemeFallsBackToThemeUrl(): void
    {
        Config::load(['assets' => ['cdn' => 'javascript:alert(1)']]);

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
}
