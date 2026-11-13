<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TTDF\Autoloader;
use TTDF\Framework;
use TTDF\Support\Path;

final class AutoloaderTest extends TestCase
{
    public function testFrameworkClassIsAutoloaded(): void
    {
        self::assertTrue(class_exists(Framework::class));
    }

    public function testMissingClassIsNotLoaded(): void
    {
        self::assertFalse(class_exists('TTDF\\Missing\\ClassName'));
    }

    public function testThemeRootIsRegisteredForAppNamespace(): void
    {
        self::assertSame(
            Path::normalize((string) realpath(dirname(__DIR__, 2))),
            Path::normalize(Autoloader::themeRoot())
        );
    }

    public function testFrameworkCorePathIsVendored(): void
    {
        self::assertSame(
            Path::normalize(dirname(__DIR__, 2) . '/vendor/yuinijika/ttdf/core'),
            Path::normalize(Framework::corePath())
        );
    }
}
