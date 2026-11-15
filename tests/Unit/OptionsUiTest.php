<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class OptionsUiTest extends TestCase
{
    public function testSettingsPanelUsesArcoVerticalTabs(): void
    {
        $script = $this->asset('options.js');

        self::assertStringContainsString('<a-tabs', $script);
        self::assertStringContainsString('direction="vertical"', $script);
        self::assertStringContainsString('<a-tab-pane', $script);
        self::assertStringContainsString('v-for="tab in tabList"', $script);
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

        self::assertStringContainsString('.arco-select-view-value', $css);
        self::assertStringContainsString('componentOptions(slot.key)', $script);
        self::assertStringContainsString(":model-value=\"templateMap[slot.key] || ''\"", $script);
    }

    public function testImportExportAndMigrationShareOneMenu(): void
    {
        $script = $this->asset('options.js');
        $css = $this->asset('options.css');

        self::assertStringContainsString('@select="handleAction"', $script);
        self::assertStringContainsString('class="TTDF-button-group"', $script);
        self::assertStringContainsString('<a-button-group', $script);
        self::assertStringContainsString('<a-dropdown', $script);
        self::assertStringContainsString('class="TTDF-more-button"', $script);
        self::assertStringContainsString('class="TTDF-chevron"', $script);
        self::assertStringContainsString('aria-label="更多操作"', $script);
        self::assertStringContainsString('value="export"', $script);
        self::assertStringContainsString('value="import"', $script);
        self::assertStringContainsString('value="migrate"', $script);
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

        self::assertStringContainsString('.TTDF-content-card p {', $css);
        self::assertStringContainsString('.TTDF-subtitle {', $css);
        self::assertStringContainsString('color: rgba(255, 255, 255, 0.9);', $css);
    }

    public function testSaveSuccessMessageUsesChinesePrefix(): void
    {
        $script = $this->asset('options.js');

        self::assertStringContainsString("message('success', '保存成功：'", $script);
        self::assertStringNotContainsString('Settings saved', $script);
    }

    public function testSettingsUiUsesArcoDesignWithoutElementPlus(): void
    {
        $script = $this->asset('options.js');
        $css = $this->asset('options.css');
        $static = dirname(__DIR__, 2) . '/vendor/yuinijika/ttdf/static';

        self::assertStringContainsString('ArcoVue', $script);
        self::assertStringContainsString('<a-spin', $script);
        self::assertStringContainsString('<a-dropdown', $script);
        self::assertStringContainsString('<a-select', $script);
        self::assertStringNotContainsString('Element Plus', $script);
        self::assertStringNotContainsString('element-plus', $script);
        self::assertStringNotContainsString('ElMessage', $script);
        self::assertStringNotContainsString('<el-', $script);
        self::assertStringNotContainsString('.el-', $css);
        self::assertFileExists($static . '/arco/index.css');
        self::assertFileExists($static . '/arco/arco-vue.min.js');
        self::assertFileExists($static . '/arco/arco-vue-icon.min.js');
        self::assertFileDoesNotExist($static . '/element-plus/index.css');
        self::assertFileDoesNotExist($static . '/element-plus/index.full.min.js');
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
