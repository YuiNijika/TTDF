<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use TTDF\Autoloader;
use TTDF\Framework;
use TTDF\Support\Path;

final class AutoloaderTest extends TestCase
{
    public function testFrameworkClassIsAutoloaded(): void
    {
        self::assertTrue(class_exists(Framework::class));
    }

    public function testMissingClassIsNotLoaded(): void
    {
        self::assertFalse(class_exists('TTDF\\Missing\\ClassName'));
    }

    public function testThemeRootIsRegisteredForAppNamespace(): void
    {
        self::assertSame(
            Path::normalize((string) realpath(dirname(__DIR__, 2))),
            Path::normalize(Autoloader::themeRoot())
        );
    }

    public function testFrameworkCorePathIsVendored(): void
    {
        self::assertSame(
            Path::normalize(dirname(__DIR__, 2) . '/vendor/yuinijika/ttdf/core'),
            Path::normalize(Framework::corePath())
        );
    }

    public function testInvalidThemeNamespaceIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Autoloader::register(null, 'Invalid Namespace');
    }

    public function testThemeNamespaceCannotShadowFramework(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Autoloader::register(null, 'TTDF\\Theme');
    }

    #[RunInSeparateProcess]
    public function testThemeNamespaceResolvesLowercaseDirectories(): void
    {
        $root = sys_get_temp_dir() . '/ttdf-autoload-' . bin2hex(random_bytes(6));
        $directory = $root . '/app/support/nested';
        self::assertTrue(mkdir($directory, 0777, true));

        $file = $directory . '/Greeter.php';
        self::assertNotFalse(
            file_put_contents(
                $file,
                "<?php\n\ndeclare(strict_types=1);\n\nnamespace FixtureTheme\\Support\\Nested;\n\n"
                . "final class Greeter\n{\n    public static function greet(): string\n    {\n"
                . "        return 'hello';\n    }\n}\n"
            )
        );

        try {
            Autoloader::register($root, 'FixtureTheme');

            self::assertSame('FixtureTheme', Autoloader::themeNamespace());
            self::assertTrue(class_exists('FixtureTheme\\Support\\Nested\\Greeter'));
            self::assertSame('hello', \FixtureTheme\Support\Nested\Greeter::greet());
        } finally {
            @unlink($file);
            @rmdir($directory);
            @rmdir($root . '/app/support');
            @rmdir($root . '/app');
            @rmdir($root);
        }
    }
}
