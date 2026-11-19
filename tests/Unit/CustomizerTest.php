<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TTDF\Theme\Customizer;

final class CustomizerTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_GET['panel']);
    }

    public function testCanonicalUrlTargetsTheAdminBridge(): void
    {
        self::assertSame(
            'https://example.test/admin/ttdf-custom-theme/',
            Customizer::url()
        );
        self::assertStringEndsWith('/ttdf-custom-theme/', Customizer::bridgeUrl());
    }

    public function testPanelDetectionRequiresExactName(): void
    {
        $_GET['panel'] = 'ttdf-custom-theme';
        self::assertTrue(Customizer::isRequested());

        $_GET['panel'] = 'ttdf-custom-theme-extra';
        self::assertFalse(Customizer::isRequested());

        $_GET['panel'] = ['ttdf-custom-theme'];
        self::assertFalse(Customizer::isRequested());
    }

    public function testBridgeBootsTypechoAsTheCanonicalAdminScript(): void
    {
        $source = $this->source('vendor/yuinijika/ttdf/core/Theme/Customizer.php');

        self::assertStringContainsString("public const PANEL = 'ttdf-custom-theme';", $source);
        self::assertStringContainsString("public const BRIDGE_DIR = 'ttdf-custom-theme';", $source);
        self::assertStringContainsString('__TYPECHO_ADMIN_DIR__', $source);
        self::assertStringContainsString("'SCRIPT_NAME'", $source);
        self::assertStringContainsString('Customizer::renderDocument()', $source);
    }

    public function testStandaloneDocumentUsesOnlyBuiltAssets(): void
    {
        $source = $this->source('vendor/yuinijika/ttdf/core/Theme/Customizer.php');

        self::assertStringContainsString('<!DOCTYPE html>', $source);
        self::assertStringContainsString("printStaticStyles(['app.css'])", $source);
        self::assertStringContainsString("printStaticModules(['custom-theme.js'])", $source);
        self::assertStringNotContainsString('custom-theme.css', $source);
        self::assertStringNotContainsString('vue.global.prod.js', $source);
    }

    private function source(string $relative): string
    {
        $path = dirname(__DIR__, 2) . '/' . ltrim($relative, '/');
        self::assertFileExists($path);

        $source = file_get_contents($path);
        self::assertIsString($source);

        return $source;
    }
}
