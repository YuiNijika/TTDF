<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TTDF\Config\Config;
use TTDF\Config\ConfigLoader;
use TTDF\Framework;
use TTDF\Theme\Options;
use TTDF\Theme\ThemeConfig;
use TTDF\Theme\Vite;

final class ViteTest extends TestCase
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

    public function testDisabledVitePrintsNothing(): void
    {
        self::assertFalse(Vite::enabled());
        self::assertSame('', Vite::head());
    }

    public function testDevModeInjectsClientAndEntryModules(): void
    {
        Config::load([
            'vite' => [
                'enabled' => true,
                'dev' => true,
                'origin' => 'http://localhost:5173/',
                'entry' => 'src/main.ts',
            ],
        ]);

        $html = Vite::head();

        self::assertStringContainsString(
            '<script type="module" src="http://localhost:5173/@vite/client"></script>',
            $html
        );
        self::assertStringContainsString(
            '<script type="module" src="http://localhost:5173/src/main.ts"></script>',
            $html
        );
    }

    public function testInvalidDevOriginFallsBackToNoOutput(): void
    {
        Config::load([
            'vite' => [
                'enabled' => true,
                'dev' => true,
                'origin' => 'javascript:alert(1)',
            ],
        ]);

        self::assertSame('', Vite::head());
    }

    public function testDevPreambleRunsBeforeClientAndEntry(): void
    {
        Config::load([
            'vite' => [
                'enabled' => true,
                'dev' => true,
                'origin' => 'http://localhost:5173',
                'entry' => 'src/main.tsx',
                'dev_preamble' => '<script type="module">import Refresh from "{{origin}}/@react-refresh";</script>',
            ],
        ]);

        $html = Vite::head();
        $preamble = strpos($html, 'import Refresh from "http://localhost:5173/@react-refresh"');
        $client = strpos($html, 'http://localhost:5173/@vite/client');
        $entry = strpos($html, 'http://localhost:5173/src/main.tsx');

        self::assertNotFalse($preamble);
        self::assertNotFalse($client);
        self::assertNotFalse($entry);
        self::assertTrue($preamble < $client);
        self::assertTrue($client < $entry);
    }

    public function testInvalidDevPreambleIsIgnored(): void
    {
        Config::load([
            'vite' => [
                'enabled' => true,
                'dev' => true,
                'origin' => 'http://localhost:5173',
                'dev_preamble' => '<script>window.bad = true;</script>',
            ],
        ]);

        $html = Vite::head();

        self::assertStringNotContainsString('window.bad', $html);
        self::assertStringContainsString('http://localhost:5173/@vite/client', $html);
    }

    public function testProductionReadsManifestAndEmitsHashedAssets(): void
    {
        $this->root = $this->makeThemeRoot([
            'assets/dist/.vite/manifest.json' => json_encode([
                'src/main.ts' => [
                    'file' => 'assets/main-abc.js',
                    'src' => 'src/main.ts',
                    'isEntry' => true,
                    'css' => ['assets/main-def.css'],
                    'imports' => ['_shared-xyz.js'],
                ],
                '_shared-xyz.js' => [
                    'file' => 'assets/shared-xyz.js',
                    'css' => ['assets/shared-def.css'],
                ],
            ], JSON_THROW_ON_ERROR),
        ]);

        Config::load([
            'vite' => [
                'enabled' => true,
                'root' => 'vite',
                'entry' => 'src/main.ts',
                'out_dir' => 'assets/dist',
                'manifest' => '.vite/manifest.json',
            ],
        ]);

        $html = Vite::head();

        self::assertStringContainsString(
            '<link rel="stylesheet" href="https://example.test/usr/themes/ttdf/assets/dist/assets/main-def.css'
            . '?v=5.0.4-next">',
            $html
        );
        self::assertStringContainsString(
            '<link rel="stylesheet" href="https://example.test/usr/themes/ttdf/assets/dist/assets/shared-def.css'
            . '?v=5.0.4-next">',
            $html
        );
        self::assertStringContainsString(
            '<link rel="modulepreload" href="https://example.test/usr/themes/ttdf/assets/dist/assets/shared-xyz.js'
            . '?v=5.0.4-next" crossorigin>',
            $html
        );
        self::assertStringContainsString(
            '<script type="module" crossorigin '
            . 'src="https://example.test/usr/themes/ttdf/assets/dist/assets/main-abc.js?v=5.0.4-next"></script>',
            $html
        );
    }

    public function testMissingManifestDegradesWithoutMarkup(): void
    {
        $this->root = $this->makeThemeRoot([]);
        Config::load([
            'vite' => [
                'enabled' => true,
            ],
        ]);

        self::assertSame('', Vite::head());
    }

    public function testMalformedManifestDegradesWithoutMarkup(): void
    {
        $this->root = $this->makeThemeRoot([
            'assets/dist/.vite/manifest.json' => '{not json',
        ]);
        Config::load([
            'vite' => [
                'enabled' => true,
            ],
        ]);

        self::assertSame('', Vite::head());
    }

    public function testLegacyManifestLocationIsAccepted(): void
    {
        $this->root = $this->makeThemeRoot([
            'assets/dist/manifest.json' => json_encode([
                'src/main.ts' => [
                    'file' => 'assets/main-legacy.js',
                    'isEntry' => true,
                ],
            ], JSON_THROW_ON_ERROR),
        ]);
        Config::load([
            'vite' => [
                'enabled' => true,
            ],
        ]);

        self::assertStringContainsString('assets/dist/assets/main-legacy.js', Vite::head());
    }

    public function testManifestCannotEscapeThemeRoot(): void
    {
        $this->root = $this->makeThemeRoot([
            'assets/dist/.vite/manifest.json' => json_encode([
                'src/main.ts' => [
                    'file' => '../../outside.js',
                    'isEntry' => true,
                ],
            ], JSON_THROW_ON_ERROR),
        ]);
        Config::load([
            'vite' => [
                'enabled' => true,
            ],
        ]);

        self::assertSame('', Vite::head());
    }

    public function testConfigLoaderValidatesViteOptions(): void
    {
        $root = sys_get_temp_dir() . '/ttdf-vite-config-' . uniqid('', true);
        mkdir($root . '/app/config', 0777, true);

        try {
            file_put_contents($root . '/app/config/app.php', <<<'PHP'
<?php

return [
    'vite' => [
        'enabled' => 'yes',
        'dev' => 'off',
        'origin' => 'https://vite.example.test/',
        'root' => 'vite',
        'entry' => 'src/main.tsx',
        'out_dir' => 'assets/dist',
        'manifest' => '.vite/manifest.json',
    ],
    'rest' => [
        'prefix' => ['mobile' => 'v2'],
    ],
];
PHP);

            $config = ConfigLoader::load($root);

            self::assertTrue($config['vite']['enabled']);
            self::assertFalse($config['vite']['dev']);
            self::assertSame('https://vite.example.test', $config['vite']['origin']);
            self::assertSame(['mobile' => 'v2'], $config['rest']['prefix']);
        } finally {
            @unlink($root . '/app/config/app.php');
            @rmdir($root . '/app/config');
            @rmdir($root . '/app');
            @rmdir($root);
        }
    }

    public function testConfigLoaderRejectsTraversalAndInvalidOrigins(): void
    {
        $cases = [
            ['root' => '../assets'],
            ['entry' => 'src/../../main.ts'],
            ['out_dir' => 'C:\\build'],
            ['out_dir' => 'assets'],
            ['out_dir' => 'vite/dist'],
            ['origin' => 'http://localhost:5173/path'],
            ['origin' => 'file:///tmp/vite'],
        ];

        foreach ($cases as $case) {
            $root = sys_get_temp_dir() . '/ttdf-vite-config-' . uniqid('', true);
            mkdir($root . '/app/config', 0777, true);
            $vite = array_replace([
                'enabled' => true,
                'origin' => 'http://localhost:5173',
                'root' => 'vite',
                'entry' => 'src/main.ts',
                'out_dir' => 'assets/dist',
                'manifest' => '.vite/manifest.json',
            ], $case);
            file_put_contents(
                $root . '/app/config/app.php',
                "<?php\n\nreturn ['vite' => " . var_export($vite, true) . "];\n"
            );

            try {
                ConfigLoader::load($root);
                self::fail('Expected Vite config to be rejected: ' . json_encode($case));
            } catch (\RuntimeException $exception) {
                self::assertStringContainsString('vite', $exception->getMessage());
            } finally {
                @unlink($root . '/app/config/app.php');
                @rmdir($root . '/app/config');
                @rmdir($root . '/app');
                @rmdir($root);
            }
        }
    }

    /**
     * @param array<string, string> $files
     */
    private function makeThemeRoot(array $files): string
    {
        $root = sys_get_temp_dir() . '/ttdf-vite-' . uniqid('', true);
        mkdir($root, 0777, true);
        $files = array_replace([
            'index.php' => "<?php\n/**\n * @version 5.0.4-next\n */\n",
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
