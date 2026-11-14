<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class OptionsUiTest extends TestCase
{
    public function testSettingsPanelIsVisibleWithoutTabToggle(): void
    {
        self::assertStringContainsString(
            'class="TTDF-tab-panel active"',
            $this->asset('options.js')
        );
    }

    public function testSettingsPanelHeightScalesWithTheViewport(): void
    {
        $css = $this->asset('options.css');

        self::assertStringContainsString('--ttdf-panel-height: clamp(520px, 48vh, 1080px);', $css);
        self::assertStringContainsString('height: var(--ttdf-panel-height);', $css);
        self::assertStringContainsString('min-height: var(--ttdf-panel-height);', $css);
    }

    public function testSettingsPanelWidensItsTypechoColumn(): void
    {
        $css = $this->asset('options.css');
        $script = $this->asset('options.js');

        self::assertStringContainsString('.typecho-page-main > .ttdf-options-column', $css);
        self::assertStringContainsString("mount.closest('.col-mb-12, .col-tb-8')", $script);
        self::assertStringContainsString("classList.add('ttdf-options-column')", $script);
    }

    public function testTemplateMapSelectedValuesRemainVisible(): void
    {
        $css = $this->asset('options.css');
        $script = $this->asset('options.js');

        self::assertStringContainsString('.TTDF-container .el-select__input', $css);
        self::assertStringContainsString('background-color: transparent !important;', $css);
        self::assertStringContainsString('componentOptions(slot.key)', $script);
    }

    public function testImportExportAndMigrationShareOneMenu(): void
    {
        $script = $this->asset('options.js');
        $css = $this->asset('options.css');

        self::assertStringContainsString('@command="handleAction"', $script);
        self::assertStringContainsString('class="TTDF-button-group"', $script);
        self::assertStringContainsString('<el-button-group', $script);
        self::assertStringContainsString('class="TTDF-more-button"', $script);
        self::assertStringContainsString('class="TTDF-chevron"', $script);
        self::assertStringContainsString('aria-label="更多操作"', $script);
        self::assertStringContainsString('command="export"', $script);
        self::assertStringContainsString('command="import"', $script);
        self::assertStringContainsString('command="migrate"', $script);
        self::assertStringContainsString('.TTDF-button-group', $css);
        self::assertStringContainsString('.ttdf-options-form .typecho-option-submit', $css);
    }

    public function testDefaultTypechoSubmitRowIsHidden(): void
    {
        $script = $this->asset('options.js');
        $css = $this->asset('options.css');

        self::assertStringContainsString("mount.closest('form')", $script);
        self::assertStringContainsString("classList.add('ttdf-options-form')", $script);
        self::assertStringContainsString('typecho-option-submit', $css);
        self::assertStringNotContainsString('▾', $script);
    }

    public function testHeaderSubtitleKeepsLightContrast(): void
    {
        $css = $this->asset('options.css');

        self::assertStringContainsString('.TTDF-content p {', $css);
        self::assertStringContainsString('.TTDF-subtitle {', $css);
        self::assertStringContainsString('color: rgba(255, 255, 255, 0.9);', $css);
    }

    public function testSaveSuccessMessageUsesChinesePrefix(): void
    {
        $script = $this->asset('options.js');

        self::assertStringContainsString("ElMessage.success('保存成功：'", $script);
        self::assertStringNotContainsString('Settings saved', $script);
    }

    private function asset(string $name): string
    {
        $path = dirname(__DIR__, 2) . '/vendor/yuinijika/ttdf/static/options/' . $name;
        self::assertFileExists($path);

        $source = file_get_contents($path);
        self::assertIsString($source);

        return $source;
    }
}
