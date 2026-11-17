<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TTDF\Config\Config;
use TTDF\Hooks\Hooks;
use TTDF\Theme\FrontendAssets;
use TTDF\Theme\Options;
use TTDF\Theme\ThemeConfig;

final class FrontendAssetsTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load([]);
        Options::reset();
        ThemeConfig::reset();
        Hooks::reset();
        FrontendAssets::reset();
    }

    protected function tearDown(): void
    {
        Config::reset();
        Options::reset();
        ThemeConfig::reset();
        Hooks::reset();
        FrontendAssets::reset();
    }

    public function testDisabledBundlersPrintNothing(): void
    {
        FrontendAssets::register();

        self::assertSame('', $this->runHead());
    }

    public function testWebpackIsInjectedThroughThemeHead(): void
    {
        Config::load([
            'webpack' => [
                'enabled' => true,
                'dev' => true,
                'origin' => 'http://localhost:8090',
                'entry_name' => 'main',
            ],
        ]);
        FrontendAssets::register();

        self::assertSame(
            '<script defer src="http://localhost:8090/main.js"></script>' . PHP_EOL,
            $this->runHead()
        );
    }

    public function testRegisterIsIdempotent(): void
    {
        Config::load([
            'webpack' => [
                'enabled' => true,
                'dev' => true,
                'origin' => 'http://localhost:8090',
                'entry_name' => 'main',
            ],
        ]);
        FrontendAssets::register();
        FrontendAssets::register();

        self::assertSame(1, substr_count($this->runHead(), '<script defer'));
    }

    public function testLegacyLoadHeadAliasAlsoRuns(): void
    {
        Config::load([
            'webpack' => [
                'enabled' => true,
                'dev' => true,
                'origin' => 'http://localhost:8090',
                'entry_name' => 'main',
            ],
        ]);
        FrontendAssets::register();

        ob_start();
        Hooks::doAction('load_head');
        $output = (string) ob_get_clean();

        self::assertStringContainsString(
            '<script defer src="http://localhost:8090/main.js"></script>',
            $output
        );
    }

    private function runHead(): string
    {
        ob_start();
        Hooks::doAction('theme.head');

        return (string) ob_get_clean();
    }
}
