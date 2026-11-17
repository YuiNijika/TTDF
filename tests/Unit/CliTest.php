<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TTDF\Cli\Application;
use TTDF\Cli\ConfigEditor;

final class CliTest extends TestCase
{
    private string $output = '';

    /** @var list<string> */
    private array $roots = [];

    protected function tearDown(): void
    {
        foreach ($this->roots as $root) {
            $this->removeDirectory($root);
        }

        $this->roots = [];
        $this->output = '';
    }

    public function testUsageIsPrintedWithoutCommand(): void
    {
        self::assertSame(0, $this->runCli(['ttdf']));
        self::assertStringContainsString('TTDF CLI', $this->output);
        self::assertStringContainsString('dev --vite', $this->output);
    }

    public function testViteDevModeDisablesWebpack(): void
    {
        $root = $this->makeThemeRoot();

        self::assertSame(0, $this->runCli(['ttdf', 'dev', '--vite', '--root=' . $root]));
        self::assertStringContainsString('pnpm --dir vite dev', $this->output);

        $state = ConfigEditor::state($root . '/app/config/app.php');
        self::assertTrue($state['vite']['enabled']);
        self::assertTrue($state['vite']['dev']);
        self::assertFalse($state['webpack']['enabled']);
        self::assertFalse($state['webpack']['dev']);
    }

    public function testWebpackDevModeDisablesVite(): void
    {
        $root = $this->makeThemeRoot();

        self::assertSame(0, $this->runCli(['ttdf', 'dev', '--webpack', '--root=' . $root]));
        self::assertStringContainsString('pnpm --dir webpack dev', $this->output);

        $state = ConfigEditor::state($root . '/app/config/app.php');
        self::assertTrue($state['webpack']['enabled']);
        self::assertTrue($state['webpack']['dev']);
        self::assertFalse($state['vite']['enabled']);
        self::assertFalse($state['vite']['dev']);
    }

    public function testOffDisablesDevButKeepsEnabled(): void
    {
        $config = str_replace(
            "'vite' => [\n        'enabled' => false,\n        'dev' => false,",
            "'vite' => [\n        'enabled' => true,\n        'dev' => true,",
            $this->config()
        );
        $root = $this->makeThemeRoot($config);

        self::assertSame(0, $this->runCli(['ttdf', 'dev', '--off', '--root=' . $root]));

        $state = ConfigEditor::state($root . '/app/config/app.php');
        self::assertTrue($state['vite']['enabled']);
        self::assertFalse($state['vite']['dev']);
        self::assertFalse($state['webpack']['dev']);
    }

    public function testDryRunDoesNotWriteTheConfig(): void
    {
        $root = $this->makeThemeRoot();
        $file = $root . '/app/config/app.php';
        $before = (string) file_get_contents($file);

        self::assertSame(0, $this->runCli(['ttdf', 'dev', '--vite', '--dry-run', '--root=' . $root]));
        self::assertSame($before, (string) file_get_contents($file));
        self::assertStringContainsString('Dry run', $this->output);
    }

    public function testStatusPrintsCurrentState(): void
    {
        $root = $this->makeThemeRoot();

        self::assertSame(0, $this->runCli(['ttdf', 'dev', '--status', '--root=' . $root]));
        self::assertStringContainsString('vite:    enabled=false dev=false', $this->output);
        self::assertStringContainsString('webpack: enabled=false dev=false', $this->output);
    }

    public function testMissingSectionsAreCreated(): void
    {
        $root = $this->makeThemeRoot("<?php\n\ndeclare(strict_types=1);\n\nreturn [\n    'debug' => false,\n];\n");

        self::assertSame(0, $this->runCli(['ttdf', 'dev', '--webpack', '--root=' . $root]));

        $state = ConfigEditor::state($root . '/app/config/app.php');
        self::assertTrue($state['webpack']['enabled']);
        self::assertTrue($state['webpack']['dev']);
        self::assertFalse($state['vite']['enabled']);
    }

    public function testBothBundlersAreRejected(): void
    {
        $root = $this->makeThemeRoot();

        self::assertSame(1, $this->runCli(['ttdf', 'dev', '--vite', '--webpack', '--root=' . $root]));
        self::assertSame(1, $this->runCli(['ttdf', 'dev', '--unknown', '--root=' . $root]));
    }

    public function testLineEndingsArePreserved(): void
    {
        $config = str_replace("\n", "\r\n", $this->config());
        $root = $this->makeThemeRoot($config);

        self::assertSame(0, $this->runCli(['ttdf', 'dev', '--vite', '--root=' . $root]));

        $raw = (string) file_get_contents($root . '/app/config/app.php');
        self::assertStringContainsString("\r\n", $raw);
        self::assertSame(substr_count($raw, "\n"), substr_count($raw, "\r\n"));
    }

    private function runCli(array $args): int
    {
        ob_start();
        $code = Application::run($args);
        $this->output = (string) ob_get_clean();

        return $code;
    }

    private function makeThemeRoot(?string $config = null): string
    {
        $root = sys_get_temp_dir() . '/ttdf-cli-' . uniqid('', true);
        mkdir($root . '/app/config', 0777, true);
        file_put_contents($root . '/app/config/app.php', $config ?? $this->config());
        $this->roots[] = $root;

        return $root;
    }

    private function config(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

return [
    'debug' => false,

    'vite' => [
        'enabled' => false,
        'dev' => false,
        'origin' => 'http://localhost:5173',
    ],

    'webpack' => [
        'enabled' => false,
        'dev' => false,
        'origin' => 'http://localhost:8090',
    ],
];
PHP;
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

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
