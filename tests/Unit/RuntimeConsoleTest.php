<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TTDF\Config\Config;
use TTDF\Diagnostics\RuntimeConsole;
use TTDF\Hooks\Hooks;
use TTDF\Theme\Options;

final class RuntimeConsoleTest extends TestCase
{
    protected function setUp(): void
    {
        Config::reset();
        Hooks::reset();
        Options::reset();
        RuntimeConsole::reset();
    }

    protected function tearDown(): void
    {
        Config::reset();
        Hooks::reset();
        Options::reset();
        RuntimeConsole::reset();
    }

    public function testElapsedLabelUsesMilliseconds(): void
    {
        RuntimeConsole::start(microtime(true) - 0.25);

        self::assertMatchesRegularExpression('/^\d+\.\d{3} ms$/', RuntimeConsole::elapsedLabel());
    }

    public function testRenderPrintsLegacyBannerOnce(): void
    {
        RuntimeConsole::start(microtime(true) - 0.01);

        ob_start();
        RuntimeConsole::render();
        RuntimeConsole::render();
        $output = (string) ob_get_clean();

        self::assertStringContainsString('window.frameWorkConfig', $output);
        self::assertStringContainsString('TTDF v', $output);
        self::assertStringContainsString('页面加载耗时', $output);
        self::assertStringContainsString('"framework":"5.0.1-next"', $output);
        self::assertStringNotContainsString('TyAjax', $output);
        self::assertStringNotContainsString('RESTAPI', $output);
        self::assertSame(1, substr_count($output, 'window.frameWorkConfig ='));
    }

    public function testRegisteredBannerRunsThroughLegacyLoadFoot(): void
    {
        RuntimeConsole::start(microtime(true) - 0.01);
        RuntimeConsole::register();

        ob_start();
        Hooks::doAction('load_foot');
        $output = (string) ob_get_clean();

        self::assertStringContainsString('window.frameWorkConfig', $output);
    }
}
