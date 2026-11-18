<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class OptionsUiTest extends TestCase
{
    public function testSettingsPanelUsesSemanticSectionNavigation(): void
    {
        $script = $this->asset('options.js');

        self::assertStringContainsString('<nav class="TTDF-nav" aria-label="设置分类">', $script);
        self::assertStringContainsString('v-for="tab in tabList"', $script);
        self::assertStringContainsString('class="TTDF-nav-item"', $script);
        self::assertStringContainsString('type="button"', $script);
        self::assertStringContainsString('id="ttdf-panel-settings"', $script);
    }

    public function testSettingsPanelRendersNestedTopTabs(): void
    {
        $script = $this->asset('options.js');
        $css = $this->asset('options.css');

        self::assertStringContainsString('const activeSection = ref', $script);
        self::assertStringContainsString('const sectionsForTab', $script);
        self::assertStringContainsString('class="TTDF-subnav"', $script);
        self::assertStringContainsString('class="TTDF-subnav-item"', $script);
        self::assertStringContainsString('@click="switchSection(section.id)"', $script);
        self::assertStringContainsString('aria-label="设置子分类"', $script);
        self::assertStringContainsString('.TTDF-subnav-item.is-active', $css);
        self::assertStringContainsString('.TTDF-subnav-item:focus-visible', $css);
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
        self::assertStringContainsString('field.components', $script);
        self::assertStringContainsString('ignoredMappings', $script);
        self::assertStringContainsString('slotPlaceholder', $script);
        self::assertStringContainsString('留空使用 404 内联回退', $script);
        self::assertStringContainsString('留空回退到 archive 槽位', $script);
        self::assertStringNotContainsString('allow-create', $script);
    }

    public function testImportExportBackupRestoreAndMigrationShareOneMenu(): void
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
        self::assertStringContainsString('value="backup"', $script);
        self::assertStringContainsString('value="backups"', $script);
        self::assertStringContainsString('value="migrate"', $script);
        self::assertStringContainsString('title="主题设置备份"', $script);
        self::assertStringContainsString('request(\'/restore\'', $script);
        self::assertStringContainsString('.TTDF-button-group', $css);
        self::assertStringContainsString('.ttdf-backup-item', $css);
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

    public function testPanelUsesNeutralPaletteWithoutBlueBars(): void
    {
        $css = $this->asset('options.css');

        self::assertStringContainsString('.TTDF-content-card p {', $css);
        self::assertStringContainsString('.TTDF-subtitle {', $css);
        self::assertStringContainsString('--ttdf-ink: #18181b;', $css);
        self::assertStringContainsString('color: var(--ttdf-muted);', $css);
        self::assertStringNotContainsString('#2271b1', $css);
        self::assertStringNotContainsString('#1a73e8', $css);
        self::assertStringNotContainsString('--ttdf-nav-accent', $css);
    }

    public function testLoadingUsesSkeletonBlocksInsteadOfSpinner(): void
    {
        $script = $this->asset('options.js');
        $css = $this->asset('options.css');

        self::assertStringContainsString('class="TTDF-skeleton"', $script);
        self::assertStringContainsString('TTDF-skeleton-nav', $script);
        self::assertStringContainsString('TTDF-skeleton-control', $script);
        self::assertStringContainsString('.TTDF-skeleton {', $css);
        self::assertStringContainsString('@keyframes ttdf-skeleton-shimmer', $css);
        self::assertStringNotContainsString('<a-spin', $script);
        self::assertStringNotContainsString('TTDF-spin', $css);
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
        self::assertStringContainsString('<a-empty', $script);
        self::assertStringContainsString('<a-form', $script);
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

    public function testFormControlsKeepVisibleDefaultBorders(): void
    {
        $css = $this->asset('options.css');

        self::assertStringContainsString('.TTDF-container .arco-input-wrapper,', $css);
        self::assertStringContainsString('.TTDF-container .arco-select-view,', $css);
        self::assertStringContainsString('border: 1px solid var(--ttdf-line-strong);', $css);
        self::assertStringNotContainsString('border-color: transparent', $css);
        self::assertStringContainsString('.arco-select-view input:focus-visible', $css);
        self::assertStringNotContainsString('input:not(.arco-input):focus-visible', $css);
    }

    public function testInlineFieldsCanDeclareIndependentRows(): void
    {
        $script = $this->asset('options.js');

        self::assertStringContainsString('field.inline_group', $script);
        self::assertStringContainsString('rowGroup !== group', $script);
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
