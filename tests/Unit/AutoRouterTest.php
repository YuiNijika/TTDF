<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TTDF\Routing\AutoRouter;

final class AutoRouterTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/ttdf-router-' . uniqid('', true);
        mkdir($this->root . '/blog', 0777, true);
        mkdir($this->root . '/docs', 0777, true);
        mkdir($this->root . '/team/[member]', 0777, true);
        file_put_contents($this->root . '/blog/index.php', '<?php');
        file_put_contents($this->root . '/blog/post.php', '<?php');
        file_put_contents($this->root . '/blog/[slug].php', '<?php');
        file_put_contents($this->root . '/docs/[[page]].php', '<?php');
        file_put_contents($this->root . '/team/[member]/index.php', '<?php');
        file_put_contents($this->root . '/team/[member]/[post].php', '<?php');
        file_put_contents($this->root . '/[[page]].php', '<?php');
    }

    protected function tearDown(): void
    {
        foreach ([
            'blog/index.php',
            'blog/post.php',
            'blog/[slug].php',
            'docs/[[page]].php',
            'team/[member]/index.php',
            'team/[member]/[post].php',
            '[[page]].php',
        ] as $file) {
            @unlink($this->root . '/' . $file);
        }
        @rmdir($this->root . '/team/[member]');
        @rmdir($this->root . '/team');
        @rmdir($this->root . '/blog');
        @rmdir($this->root . '/docs');
        @rmdir($this->root);
    }

    public function testExactAndDynamicRoutesResolve(): void
    {
        $index = AutoRouter::resolve($this->root, 'blog');
        self::assertNotNull($index);
        self::assertStringEndsWith('/blog/index.php', $index['file']);

        $post = AutoRouter::resolve($this->root, 'blog/post');
        self::assertNotNull($post);
        self::assertStringEndsWith('/blog/post.php', $post['file']);

        $dynamic = AutoRouter::resolve($this->root, 'blog/hello');
        self::assertNotNull($dynamic);
        self::assertStringEndsWith('/blog/[slug].php', $dynamic['file']);
        self::assertSame('hello', $dynamic['params']['slug']);
    }

    public function testTraversalAndInvalidSegmentsAreRejected(): void
    {
        self::assertNull(AutoRouter::resolve($this->root, '../secret'));
        self::assertNull(AutoRouter::resolve($this->root, 'blog/../secret'));
        self::assertNull(AutoRouter::resolve($this->root, 'blog/%2e%2e/secret'));
        self::assertNull(AutoRouter::resolve($this->root, "blog/hello\0"));
        self::assertNull(AutoRouter::resolve($this->root, 'blog\\..\\secret'));
    }

    public function testOptionalRoutesMatchZeroSegments(): void
    {
        $root = AutoRouter::resolve($this->root, '');
        self::assertNotNull($root);
        self::assertStringEndsWith('/[[page]].php', $root['file']);
        self::assertSame([], $root['params']);

        $directory = AutoRouter::resolve($this->root, 'docs');
        self::assertNotNull($directory);
        self::assertStringEndsWith('/docs/[[page]].php', $directory['file']);
        self::assertSame([], $directory['params']);

        $dynamic = AutoRouter::resolve($this->root, 'docs/hello');
        self::assertNotNull($dynamic);
        self::assertSame(['page' => 'hello'], $dynamic['params']);
    }

    public function testIndexFileWinsOverOptionalRoute(): void
    {
        $blog = AutoRouter::resolve($this->root, 'blog');
        self::assertNotNull($blog);
        self::assertStringEndsWith('/blog/index.php', $blog['file']);
    }

    public function testDynamicDirectoriesResolveIndexAndNestedFiles(): void
    {
        $index = AutoRouter::resolve($this->root, 'team/alice');
        self::assertNotNull($index);
        self::assertStringEndsWith('/team/[member]/index.php', $index['file']);
        self::assertSame(['member' => 'alice'], $index['params']);

        $post = AutoRouter::resolve($this->root, 'team/alice/hello');
        self::assertNotNull($post);
        self::assertStringEndsWith('/team/[member]/[post].php', $post['file']);
        self::assertSame(['post' => 'hello', 'member' => 'alice'], $post['params']);
    }
}
