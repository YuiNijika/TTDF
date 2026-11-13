<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use TTDF\View\Components;

final class ComponentsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/ttdf-components-' . uniqid('', true);
        mkdir($this->root . '/app/components/nested', 0777, true);

        file_put_contents(
            $this->root . '/app/components/Hello.php',
            <<<'PHP'
<?php

declare(strict_types=1);

echo ($viewData['name'] ?? '')
    . '|' . ($route['path'] ?? '')
    . '|' . ($params['slug'] ?? '')
    . '|' . (is_object($archive) ? 'archive' : 'none');
PHP
        );
        file_put_contents(
            $this->root . '/app/components/nested/Item.php',
            '<?php declare(strict_types=1); echo "item";'
        );
    }

    protected function tearDown(): void
    {
        @unlink($this->root . '/app/components/Hello.php');
        @unlink($this->root . '/app/components/nested/Item.php');
        @rmdir($this->root . '/app/components/nested');
        @rmdir($this->root . '/app/components');
        @rmdir($this->root . '/app');
        @rmdir($this->root);
    }

    public function testCaptureExposesReservedVariablesAndViewData(): void
    {
        $html = Components::capture($this->root, 'Hello', [
            'name' => 'TTDF',
            'route' => ['path' => 'blog/hello'],
            'params' => ['slug' => 'hello'],
            'archive' => new \stdClass(),
        ]);

        self::assertSame('TTDF|blog/hello|hello|archive', $html);
    }

    public function testRenderEchoesComponentOutput(): void
    {
        ob_start();
        Components::render($this->root, 'nested/Item');
        $html = (string) ob_get_clean();

        self::assertSame('item', $html);
    }

    public function testExistsReportsAvailableComponents(): void
    {
        self::assertTrue(Components::exists($this->root, 'Hello'));
        self::assertTrue(Components::exists($this->root, 'nested/Item'));
        self::assertFalse(Components::exists($this->root, 'Missing'));
    }

    public function testInvalidAndTraversalNamesAreRejected(): void
    {
        foreach (['../Hello', 'Hello/../../secret', 'bad name', '', 'Hello.php'] as $name) {
            try {
                Components::render($this->root, $name);
                self::fail('Expected invalid component name: ' . $name);
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('Invalid component name', $exception->getMessage());
            }
        }
    }

    public function testMissingComponentThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Component not found');

        Components::render($this->root, 'Missing');
    }
}
