<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TTDF\Config\Config;
use TTDF\Config\ConfigLoader;
use TTDF\Framework;
use TTDF\Theme\Options;
use TTDF\Theme\ThemeConfig;
use TTDF\Theme\Webpack;

final class WebpackTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        Config::load([]);
        Options::reset();
        ThemeConfig::reset();
    }

    protected function tearDown(): void
    {
        $this->setRoot('');
        Config::reset();
        Options::reset();
        ThemeConfig::reset();

        if ($this->root !== '' && is_dir($this->root)) {
            $this->removeDirectory($this->root);
        }
    }

    public function testDisabledWebpackPrintsNothing(): void
    {
        self::assertFalse(Webpack::enabled());
        self::assertSame('', Webpack::head());
    }

    public function testDevModeInjectsTheDevServerBundle(): void
    {
        Config::load([
            'webpack' => [
                'enabled' => true,
                'dev' => true,
                'origin' => 'http://localhost:8090/',
                'entry_name' => 'app',
            ],
        ]);

        self::assertSame(
            '<script defer src="http://localhost:8090/app.js"></script>' . PHP_EOL,
            Webpack::head()
        );
    }

    public function testInvalidDevOriginFallsBackToNoOutput(): void
    {
        Config::load([
            'webpack' => [
                'enabled' => true,
                'dev' => true,
                'origin' => 'javascript:alert(1)',
            ],
        ]);

        self::assertSame('', Webpack::head());
    }

    public function testProductionReadsEntrypointManifest(): void
    {
        $this->root = $this->makeThemeRoot([
            'assets/dist/manifest.json' => json_encode([
                'entrypoints' => [
                    'main' => [
                        'assets' => [
                            'js' => ['main-abc.js'],
                            'css' => ['main-def.css'],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
        ]);

        Config::load([
            'webpack' => [
                'enabled' => true,
                'root' => 'webpack',
                'entry' => 'src/main.ts',
                'entry_name' => 'main',
                'out_dir' => 'assets/dist',
                'manifest' => 'manifest.json',
            ],
        ]);

        $html = Webpack::head();

        self::assertStringContainsString(
            '<link rel="stylesheet" href="https://example.test/usr/themes/ttdf/assets/dist/main-def.css'
            . '?v=5.0.7-next">',
            $html
        );
        self::assertStringContainsString(
            '<script defer src="https://example.test/usr/themes/ttdf/assets/dist/main-abc.js'
            . '?v=5.0.7-next"></script>',
            $html
        );
    }

    public function testProductionAcceptsTopLevelManifestKeys(): void
    {
        $this->root = $this->makeThemeRoot([
            'assets/dist/manifest.json' => json_encode([
                'main.js' => 'main-abc.js',
                'main.css' => 'main-def.css',
            ], JSON_THROW_ON_ERROR),
        ]);
        Config::load(['webpack' => ['enabled' => true]]);

        $html = Webpack::head();

        self::assertStringContainsString('main-def.css?v=5.0.7-next', $html);
        self::assertStringContainsString('main-abc.js?v=5.0.7-next', $html);
    }

    public function testProductionAcceptsAssetsMap(): void
    {
        $this->root = $this->makeThemeRoot([
            'assets/dist/manifest.json' => json_encode([
                'assets' => [
                    'main.js' => 'main-abc.js',
                    'main.css' => 'main-def.css',
                ],
            ], JSON_THROW_ON_ERROR),
        ]);
        Config::load(['webpack' => ['enabled' => true]]);

        $html = Webpack::head();

        self::assertStringContainsString('main-def.css?v=5.0.7-next', $html);
        self::assertStringContainsString('main-abc.js?v=5.0.7-next', $html);
    }

    public function testMissingManifestDegradesWithoutMarkup(): void
    {
        $this->root = $this->makeThemeRoot([]);
        Config::load(['webpack' => ['enabled' => true]]);

        self::assertSame('', Webpack::head());
    }

    public function testManifestCannotEscapeThemeRoot(): void
    {
        $this->root = $this->makeThemeRoot([
            'assets/dist/manifest.json' => json_encode([
                'main.js' => '../../outside.js',
                'main.css' => '../../outside.css',
            ], JSON_THROW_ON_ERROR),
        ]);
        Config::load(['webpack' => ['enabled' => true]]);

        $html = Webpack::head();

        self::assertStringNotContainsString('outside.js', $html);
        self::assertStringNotContainsString('outside.css', $html);
    }

    public function testConfigLoaderValidatesWebpackAndRejectsViteTogether(): void
    {
        $root = sys_get_temp_dir() . '/ttdf-webpack-config-' . uniqid('', true);
        mkdir($root . '/app/config', 0777, true);

        try {
            file_put_contents($root . '/app/config/app.php', <<<'PHP'
<?php

return [
    'vite' => ['enabled' => true],
    'webpack' => [
        'enabled' => true,
        'dev' => 'off',
        'origin' => 'http://localhost:8090/',
        'root' => 'webpack',
        'entry' => 'src/main.ts',
        'entry_name' => 'app',
        'out_dir' => 'assets/dist',
        'manifest' => 'manifest.json',
    ],
];
PHP);

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Only one frontend bundler');
            ConfigLoader::load($root);
        } finally {
            @unlink($root . '/app/config/app.php');
            @rmdir($root . '/app/config');
            @rmdir($root . '/app');
            @rmdir($root);
        }
    }

    public function testConfigLoaderRejectsInvalidWebpackEntryName(): void
    {
        $root = sys_get_temp_dir() . '/ttdf-webpack-config-' . uniqid('', true);
        mkdir($root . '/app/config', 0777, true);

        try {
            file_put_contents($root . '/app/config/app.php', <<<'PHP'
<?php

return [
    'webpack' => [
        'enabled' => true,
        'entry_name' => 'main/app',
    ],
];
PHP);

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('webpack.entry_name');
            ConfigLoader::load($root);
        } finally {
            @unlink($root . '/app/config/app.php');
            @rmdir($root . '/app/config');
            @rmdir($root . '/app');
            @rmdir($root);
        }
    }

    /**
     * @param array<string, string> $files
     */
    private function makeThemeRoot(array $files): string
    {
        $root = sys_get_temp_dir() . '/ttdf-webpack-' . uniqid('', true);
        mkdir($root, 0777, true);
        $files = array_replace([
            'index.php' => "<?php\n/**\n * @version 5.0.7-next\n */\n",
        ], $files);

        foreach ($files as $path => $contents) {
            $file = $root . '/' . $path;
            $directory = dirname($file);
            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }
            file_put_contents($file, $contents);
        }

        $this->setRoot($root);

        return $root;
    }

    private function setRoot(string $root): void
    {
        $property = new \ReflectionProperty(Framework::class, 'root');
        $property->setValue(null, $root);
    }

    private function removeDirectory(string $directory): void
    {
        $items = scandir($directory);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . '/' . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($directory);
    }
}
