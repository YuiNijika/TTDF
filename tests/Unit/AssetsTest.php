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
            'https://example.test/usr/themes/ttdf/assets/app.css?v=5.0.3-next',
            Assets::url('assets/app.css')
        );
    }

    public function testCdnPrefixIsReadFromThemeSettingWhenItIsHttp(): void
    {
        $this->useThemeCdn('https://cdn.example.test/base/');

        self::assertSame(
            'https://cdn.example.test/base/assets/app.css?v=5.0.3-next',
            Assets::url('/assets/app.css')
        );
    }

    public function testUnsafeCdnSchemeFallsBackToThemeUrl(): void
    {
        $this->useThemeCdn('javascript:alert(1)');

        self::assertSame(
            'https://example.test/usr/themes/ttdf/assets/app.css?v=5.0.3-next',
            Assets::url('assets/app.css')
        );
    }

    public function testStaticAssetsConfigurationIsIgnored(): void
    {
        Config::load(['assets' => ['cdn' => 'https://ignored.example.test/base/']]);

        self::assertSame(
            'https://example.test/usr/themes/ttdf/assets/app.css?v=5.0.3-next',
            Assets::url('assets/app.css')
        );
    }

    public function testExistingQueryAndFragmentArePreserved(): void
    {
        self::assertSame(
            'https://example.test/usr/themes/ttdf/assets/app.css?theme=dark&amp;v=5.0.3-next#main',
            Assets::url('assets/app.css?theme=dark#main')
        );
    }

    public function testStaticUrlPointsAtVendoredFrameworkAssets(): void
    {
        self::assertSame(
            'https://example.test/usr/themes/ttdf/vendor/yuinijika/ttdf/static/options/options.css?v=5.0.3-next',
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
            '<link rel="stylesheet" href="https://example.test/usr/themes/ttdf/assets/app.css?v=5.0.3-next">',
            $html
        );
        self::assertStringContainsString(
            '<script src="https://example.test/usr/themes/ttdf/assets/main.js?v=5.0.3-next"></script>',
            $html
        );
    }

    public function testAssetVersionComesFromTheThemeAndCoversFrameworkStatics(): void
    {
        $root = sys_get_temp_dir() . '/ttdf-assets-' . uniqid('', true);
        mkdir($root, 0777, true);
        file_put_contents($root . '/index.php', "<?php\n/**\n * @version 9.9.9-theme\n */\n");

        $property = new \ReflectionProperty(\TTDF\Framework::class, 'root');
        $property->setValue(null, $root);

        try {
            self::assertSame(
                'https://example.test/usr/themes/ttdf/assets/app.css?v=9.9.9-theme',
                Assets::url('assets/app.css')
            );
            self::assertSame(
                'https://example.test/usr/themes/ttdf/vendor/yuinijika/ttdf/static/options/options.css'
                . '?v=9.9.9-theme',
                Assets::staticUrl('options/options.css')
            );
            self::assertStringNotContainsString('5.0.3-next', Assets::staticUrl('options/options.css'));
        } finally {
            $property->setValue(null, '');
            @unlink($root . '/index.php');
            @rmdir($root);
        }
    }

    public function testUrlAppliesTheDefaultAssetsBase(): void
    {
        self::assertSame(
            'https://example.test/usr/themes/ttdf/assets/css/theme.css?v=5.0.3-next',
            Assets::url('css/theme.css')
        );
        self::assertSame(
            'https://example.test/usr/themes/ttdf/assets/css/theme.css?v=5.0.3-next',
            Assets::url('css/theme.css', 'assets')
        );
        self::assertSame(
            'https://example.test/usr/themes/ttdf/assets/app.css?theme=dark&amp;v=5.0.3-next#main',
            Assets::url('assets/app.css?theme=dark#main')
        );
        self::assertSame(
            'https://example.test/usr/themes/ttdf/assets/css/theme.css?theme=dark&amp;v=5.0.3-next#main',
            Assets::url('theme.css?theme=dark#main', 'assets/css')
        );
    }

    public function testUrlAcceptsACustomBaseWithoutDoublePrefixing(): void
    {
        self::assertSame(
            'https://example.test/usr/themes/ttdf/vendor/lib/app.js?v=5.0.3-next',
            Assets::url('lib/app.js', 'vendor')
        );
        self::assertSame(
            'https://example.test/usr/themes/ttdf/assets/app.css?v=5.0.3-next',
            Assets::url('assets/app.css')
        );
        self::assertSame(
            'https://example.test/usr/themes/ttdf/assets/app.css?v=5.0.3-next',
            Assets::url('/assets/app.css', 'assets/')
        );
    }

    public function testUnsafeBaseDirectoryIsIgnored(): void
    {
        self::assertSame(
            'https://example.test/usr/themes/ttdf/css/theme.css?v=5.0.3-next',
            Assets::url('css/theme.css', '../assets')
        );
        self::assertSame(
            'https://example.test/usr/themes/ttdf/css/theme.css?v=5.0.3-next',
            Assets::url('css/theme.css', '')
        );
    }

    public function testPrintHelpersAcceptABaseDirectory(): void
    {
        ob_start();
        Assets::printStyles(['css/theme.css']);
        Assets::printScripts(['js/main.js'], 'vendor');
        $html = (string) ob_get_clean();

        self::assertStringContainsString(
            '<link rel="stylesheet" href="https://example.test/usr/themes/ttdf/assets/css/theme.css'
            . '?v=5.0.3-next">',
            $html
        );
        self::assertStringContainsString(
            '<script src="https://example.test/usr/themes/ttdf/vendor/js/main.js?v=5.0.3-next"></script>',
            $html
        );
    }

    private function useThemeCdn(string $cdn): void
    {
        $property = new \ReflectionProperty(Options::class, 'instance');
        $property->setValue(null, new Options(new InMemoryOptionsStore([
            'Theme' => json_encode(['Assets_CDN' => $cdn], JSON_THROW_ON_ERROR),
        ]), 'Theme'));
        ThemeConfig::reset();
    }
}
