<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TTDF\Theme\Templates;

final class TemplatesTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/ttdf-templates-' . uniqid('', true);
        mkdir($this->root . '/app/components/custom', 0777, true);
        file_put_contents(
            $this->root . '/app/components/Post.php',
            '<?php declare(strict_types=1); echo "post:" . $viewData["slot"];'
        );
        file_put_contents(
            $this->root . '/app/components/custom/Article.php',
            '<?php declare(strict_types=1); echo "article:" . (is_object($archive) ? "archive" : "none");'
        );
    }

    protected function tearDown(): void
    {
        @unlink($this->root . '/app/components/Post.php');
        @unlink($this->root . '/app/components/custom/Article.php');
        @rmdir($this->root . '/app/components/custom');
        @rmdir($this->root . '/app/components');
        @rmdir($this->root . '/app');
        @rmdir($this->root);
    }

    public function testNormalizeMapKeepsOnlyKnownSlotsAndSafeNames(): void
    {
        $map = Templates::normalizeMap([
            'post' => 'custom/Article',
            'page' => ' Page ',
            'unknown' => 'Post',
            'tag' => '../Secret',
            'search' => 'bad name',
            '404' => '',
            'author' => 123,
        ]);

        self::assertSame([
            'post' => 'custom/Article',
            'page' => 'Page',
            '404' => '',
        ], $map);
    }

    public function testRenderUsesMappedComponentAndFallsBack(): void
    {
        ob_start();
        $rendered = Templates::render($this->root, 'post', new \stdClass(), 'Post', [
            'post' => 'custom/Article',
        ]);
        $html = (string) ob_get_clean();

        self::assertTrue($rendered);
        self::assertSame('article:archive', $html);

        ob_start();
        $missing = Templates::render($this->root, 'post', new \stdClass(), 'Missing', []);
        ob_end_clean();

        self::assertFalse($missing);

        ob_start();
        $cleared = Templates::render($this->root, 'post', new \stdClass(), 'Post', [
            'post' => '',
        ]);
        ob_end_clean();

        self::assertFalse($cleared);
    }

    public function testAvailableListsComponentsRecursively(): void
    {
        self::assertSame(
            ['Post', 'custom/Article'],
            Templates::available($this->root)
        );
    }

    public function testArchiveSlotFallsBackToArchive(): void
    {
        $archive = new class {
            public function is(string $type): bool
            {
                return $type === 'category';
            }
        };

        self::assertSame('category', Templates::archiveSlot($archive));
        self::assertSame('archive', Templates::archiveSlot(new \stdClass()));
    }
}
