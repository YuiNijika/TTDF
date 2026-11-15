<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TTDF\Theme\PageTemplates;
use TTDF\Theme\Paths;

final class PageTemplatesTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/ttdf-pages-' . uniqid('', true);
        mkdir($this->root . '/app/pages/category', 0777, true);

        foreach ([
            'index.php',
            'archive.php',
            'single.php',
            'post.php',
            'page.php',
            'search.php',
            '404.php',
            'wide.php',
            'category/default.php',
        ] as $file) {
            file_put_contents($this->root . '/app/pages/' . $file, '<?php echo "page";');
        }
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo) {
                continue;
            }

            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        @rmdir($this->root);
    }

    public function testUsesTypechoFallbackChainInsidePagesDirectory(): void
    {
        $archive = new FakeArchive('index');
        self::assertSame('index.php', PageTemplates::apply($archive, $this->root));
        self::assertSame(
            str_replace('\\', '/', (string) realpath($this->root . '/app/pages')) . '/',
            $archive->themeDir
        );

        self::assertSame('post.php', PageTemplates::apply(new FakeArchive('post', 'hello'), $this->root));
        self::assertSame('page.php', PageTemplates::apply(new FakeArchive('page', 'about'), $this->root));
        self::assertSame('search.php', PageTemplates::apply(new FakeArchive('search', 'ttdf'), $this->root));
        self::assertSame(
            'category/default.php',
            PageTemplates::apply(new FakeArchive('category', 'default'), $this->root)
        );
    }

    public function testKeepsSafeCustomTemplateAndRejectsTraversal(): void
    {
        $custom = new FakeArchive('post', 'hello', 'wide.php');
        self::assertSame('wide.php', PageTemplates::apply($custom, $this->root));
        self::assertSame('wide.php', $custom->currentThemeFile());

        $traversal = new FakeArchive('post', 'hello', '../secret.php');
        self::assertSame('post.php', PageTemplates::apply($traversal, $this->root));
        self::assertSame('post.php', $traversal->currentThemeFile());
    }

    public function testKeeps404AndFallsBackForAttachments(): void
    {
        $notFound = new FakeArchive('archive', null, '404.php');
        self::assertSame('404.php', PageTemplates::apply($notFound, $this->root));

        unlink($this->root . '/app/pages/page.php');
        self::assertSame('post.php', PageTemplates::apply(new FakeArchive('attachment'), $this->root));
    }

    public function testUsesSingleOnlyWhenNoSpecificTemplateExists(): void
    {
        self::assertSame(
            'single.php',
            PageTemplates::apply(new FakeArchive('custom', null, null, true), $this->root)
        );
    }

    public function testMissingPagesDirectoryIsRejected(): void
    {
        $archive = new FakeArchive('index');
        $archive->themeDir = 'unchanged';

        self::assertNull(PageTemplates::apply($archive, $this->root . '/missing'));
        self::assertSame('unchanged', $archive->themeDir);
    }

    public function testSafeFileValidation(): void
    {
        foreach (['post.php', 'category/default.php', 'custom_page-2.php'] as $file) {
            self::assertTrue(PageTemplates::isSafeFile($file));
        }

        foreach (['', '../post.php', '/post.php', 'C:/post.php', 'post\\page.php', 'post.txt', 'bad name.php'] as $file) {
            self::assertFalse(PageTemplates::isSafeFile($file));
        }

        foreach (['header.php', 'footer.php', 'comments.php', 'sidebar.php', 'Header.php'] as $file) {
            self::assertFalse(PageTemplates::isSafeFile($file));
        }
    }

    public function testComponentFragmentsInPagesDirectoryAreNeverSelected(): void
    {
        foreach (['header.php', 'footer.php', 'comments.php', 'sidebar.php'] as $file) {
            file_put_contents($this->root . '/app/pages/' . $file, '<?php echo "fragment";');
        }

        $archive = new FakeArchive('custom', null, 'header.php');
        self::assertSame('archive.php', PageTemplates::apply($archive, $this->root));
        self::assertSame('archive.php', $archive->currentThemeFile());
    }

    public function testPathsExposePagesDirectory(): void
    {
        $root = str_replace('\\', '/', $this->root);
        self::assertSame($root . '/app/pages', Paths::pages($this->root));
        self::assertSame($root . '/app/pages/post.php', Paths::page($this->root, 'post.php'));
    }
}

final class FakeArchive
{
    public string $themeDir = '';

    private string $themeFile;

    private string $archiveType;

    private ?string $archiveSlug;

    private bool $single;

    public function __construct(
        string $archiveType,
        ?string $archiveSlug = null,
        ?string $themeFile = null,
        bool $single = false
    ) {
        $this->archiveType = $archiveType;
        $this->archiveSlug = $archiveSlug;
        $this->single = $single;

        if ($themeFile !== null) {
            $this->themeFile = $themeFile;
        }
    }

    public function setThemeDir(string $themeDir): void
    {
        $this->themeDir = $themeDir;
    }

    public function getThemeFile(): ?string
    {
        return $this->themeFile;
    }

    public function currentThemeFile(): string
    {
        return $this->themeFile;
    }

    public function setThemeFile(string $themeFile): void
    {
        $this->themeFile = $themeFile;
    }

    public function getArchiveType(): string
    {
        return $this->archiveType;
    }

    public function getArchiveSlug(): ?string
    {
        return $this->archiveSlug;
    }

    public function is(string $type): bool
    {
        return $type === ($this->single ? 'single' : 'archive');
    }
}
