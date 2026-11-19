<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class OptionsUiTest extends TestCase
{
    public function testSettingsAndCustomizerAreVueComponents(): void
    {
        $root = dirname(__DIR__, 2) . '/frontend/options';
        $components = [
            '/src/components/SettingsApp.vue',
            '/src/components/FormField.vue',
            '/src/components/MediaLibrary.vue',
            '/src/components/fields/BlocksField.vue',
            '/src/components/fields/ListField.vue',
            '/src/components/fields/MediaField.vue',
            '/src/components/fields/NavField.vue',
            '/src/components/fields/NavItemEditor.vue',
            '/src/components/fields/SlidesField.vue',
            '/src/components/fields/TagsField.vue',
            '/src/components/fields/TemplateMapField.vue',
            '/src/customizer/Customizer.vue',
            '/src/customizer/components/CustomizerTopBar.vue',
            '/src/customizer/components/CustomizerNav.vue',
            '/src/customizer/components/BlockEditor.vue',
            '/src/customizer/components/PreviewStage.vue',
            '/src/customizer/components/SettingsPanel.vue',
            '/src/customizer/composables/useBlockDrag.ts',
            '/src/customizer/composables/usePreviewBridge.ts',
            '/src/customizer/composables/useSettingsSchema.ts',
            '/src/customizer/lib/blocks.ts',
            '/src/customizer/types.ts',
        ];

        foreach ($components as $component) {
            self::assertFileExists($root . $component);
        }

        self::assertFileDoesNotExist($root . '/src/options/app.js');
        self::assertFileDoesNotExist($root . '/src/options/options.css');
    }

    public function testSettingsEntryMountsVueWithArcoAndGlobalStyles(): void
    {
        $source = $this->source('frontend/options/src/options/index.ts');

        self::assertStringContainsString("import '@arco-design/web-vue/dist/arco.css'", $source);
        self::assertStringContainsString("import '../styles/global.css'", $source);
        self::assertStringContainsString('createApp(SettingsApp', $source);
        self::assertStringContainsString('.use(ArcoVue)', $source);
        self::assertStringContainsString("getElementById('options-app')", $source);
    }

    public function testCustomizerEntryMountsFullPageVueApp(): void
    {
        $entry = $this->source('frontend/options/src/customizer/index.ts');
        $component = $this->source('frontend/options/src/customizer/Customizer.vue');
        $stage = $this->source('frontend/options/src/customizer/components/PreviewStage.vue');
        $topbar = $this->source('frontend/options/src/customizer/components/CustomizerTopBar.vue');
        $nav = $this->source('frontend/options/src/customizer/components/CustomizerNav.vue');
        $panel = $this->source('frontend/options/src/customizer/components/SettingsPanel.vue');

        self::assertStringContainsString("import '../styles/global.css'", $entry);
        self::assertStringContainsString('createApp(Customizer)', $entry);
        self::assertStringContainsString("getElementById('ttdf-custom-theme')", $entry);
        self::assertStringContainsString('<iframe', $stage);
        self::assertStringContainsString('cz-preview', $stage);
        self::assertStringContainsString('previewOpen', $component);
        self::assertStringContainsString('useBlockDrag', $component);
        self::assertStringContainsString('<a-drawer', $component);
        self::assertStringContainsString('isCompact', $component);
        self::assertStringContainsString("emit('open-menu')", $topbar);
        self::assertStringContainsString('cz-github', $topbar);
        self::assertStringContainsString('embedded', $nav);
        self::assertStringContainsString('embedded', $panel);
    }

    public function testGlobalStylesKeepTypechoAndArcoOverrides(): void
    {
        $css = $this->source('frontend/options/src/styles/global.css');

        self::assertStringContainsString('.typecho-page-main > .ttdf-options-column', $css);
        self::assertStringContainsString('.ttdf-options-form .typecho-option-submit', $css);
        self::assertStringContainsString('#options-app', $css);
        self::assertStringContainsString('#ttdf-custom-theme', $css);
        self::assertStringContainsString('.settings-app .arco-input-wrapper', $css);
        self::assertStringContainsString('body .arco-dropdown-menu', $css);
        self::assertStringContainsString('body .arco-modal', $css);
        self::assertStringNotContainsString('Element Plus', $css);
    }

    public function testPhpLoadsOnlyBuiltEntrypoints(): void
    {
        $settings = $this->source('vendor/yuinijika/ttdf/core/Theme/Settings.php');
        $customizer = $this->source('vendor/yuinijika/ttdf/core/Theme/Customizer.php');

        self::assertStringContainsString("printStaticStyles(['app.css'])", $settings);
        self::assertStringContainsString("printStaticModules(['options.js'])", $settings);
        self::assertStringContainsString("printStaticStyles(['app.css'])", $customizer);
        self::assertStringContainsString("printStaticModules(['custom-theme.js'])", $customizer);
        self::assertStringNotContainsString('options.css', $settings);
        self::assertStringNotContainsString('custom-theme.css', $customizer);
        self::assertStringNotContainsString('vue.global.prod.js', $settings . $customizer);
        self::assertStringNotContainsString('arco-vue.min.js', $settings . $customizer);
    }

    public function testBuiltAssetsLiveDirectlyUnderStatic(): void
    {
        $static = dirname(__DIR__, 2) . '/vendor/yuinijika/ttdf/static';

        self::assertFileExists($static . '/app.css');
        self::assertFileExists($static . '/options.js');
        self::assertFileExists($static . '/custom-theme.js');
        self::assertDirectoryExists($static . '/chunks');

        self::assertDirectoryDoesNotExist($static . '/assets');
        self::assertDirectoryDoesNotExist($static . '/options');
        self::assertDirectoryDoesNotExist($static . '/vue');
        self::assertDirectoryDoesNotExist($static . '/arco');
    }

    public function testFieldRendererUsesArcoComponentsOnly(): void
    {
        $form = $this->source('frontend/options/src/components/FormField.vue');
        $media = $this->source('frontend/options/src/components/MediaLibrary.vue');

        self::assertStringContainsString('<a-input', $form);
        self::assertStringContainsString('<a-select', $form);
        self::assertStringContainsString('<a-switch', $form);
        self::assertStringContainsString('<a-modal', $media);
        self::assertStringNotContainsString('<el-', $form);
        self::assertStringNotContainsString('element-plus', $form);
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
