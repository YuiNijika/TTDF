<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use TTDF\Support\Path;

final class PathTest extends TestCase
{
    public function testRelativePath(): void
    {
        self::assertSame('vendor/ttdf', Path::relative('/theme', '/theme/vendor/ttdf'));
        self::assertSame('vendor/ttdf', Path::relative('C:\\theme', 'C:\\theme\\vendor\\ttdf'));
        self::assertSame('', Path::relative('/theme', '/theme'));
        self::assertSame('', Path::relative('/theme', '/other/vendor'));
    }

    public function testContainment(): void
    {
        $root = sys_get_temp_dir() . '/ttdf-path-' . uniqid('', true);
        mkdir($root . '/inside', 0777, true);
        file_put_contents($root . '/inside/file.php', '<?php');

        self::assertTrue(Path::contains($root, $root . '/inside/file.php'));
        self::assertTrue(Path::contains($root . '/inside', $root . '/inside/file.php'));

        unlink($root . '/inside/file.php');
        rmdir($root . '/inside');
        rmdir($root);
    }

    public function testSafeIncludeExposesOnlyReservedVariables(): void
    {
        $root = sys_get_temp_dir() . '/ttdf-include-' . uniqid('', true);
        mkdir($root, 0777, true);
        $file = $root . '/template.php';
        file_put_contents(
            $file,
            '<?php echo ($route["path"] ?? "") . "|" . ($params["slug"] ?? "")'
            . ' . "|" . ($viewData["extra"] ?? "") . "|" . (isset($extra) ? "leaked" : "safe");'
        );

        ob_start();
        Path::safeInclude($root, $file, [
            'route' => ['path' => 'blog'],
            'params' => ['slug' => 'hello'],
            'extra' => 'value',
        ]);
        $output = (string) ob_get_clean();

        self::assertSame('blog|hello|value|safe', $output);

        unlink($file);
        rmdir($root);
    }

    public function testSafeIncludeRejectsFilesOutsideRoot(): void
    {
        $root = sys_get_temp_dir() . '/ttdf-include-' . uniqid('', true);
        $outside = sys_get_temp_dir() . '/ttdf-outside-' . uniqid('', true) . '.php';
        mkdir($root, 0777, true);
        file_put_contents($outside, '<?php');

        $this->expectException(RuntimeException::class);
        try {
            Path::safeInclude($root, $outside);
        } finally {
            unlink($outside);
            rmdir($root);
        }
    }

    public function testSafeIncludeRejectsNonPhpAndNullBytePaths(): void
    {
        $root = sys_get_temp_dir() . '/ttdf-include-' . uniqid('', true);
        mkdir($root, 0777, true);
        $text = $root . '/template.txt';
        file_put_contents($text, 'plain');

        try {
            try {
                Path::safeInclude($root, $text);
                self::fail('Expected non-PHP include to be rejected');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('invalid file', $exception->getMessage());
            }

            try {
                Path::safeInclude($root, $root . "/template\0.php");
                self::fail('Expected null byte include to be rejected');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('invalid file', $exception->getMessage());
            }
        } finally {
            unlink($text);
            rmdir($root);
        }
    }
}
