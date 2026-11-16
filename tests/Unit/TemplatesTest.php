<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TTDF\Theme\Options;
use TTDF\Theme\Schema;
use TTDF\Theme\Templates;

final class TemplatesTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        Options::reset();
        Schema::reset();
        $this->root = sys_get_temp_dir() . '/ttdf-templates-' . uniqid('', true);
        mkdir($this->root . '/app/config', 0777, true);
        mkdir($this->root . '/app/components/custom', 0777, true);
        file_put_contents(
            $this->root . '/app/components/Post.php',
            '<?php declare(strict_types=1); echo "post:" . $viewData["slot"];'
        );
        file_put_contents(
            $this->root . '/app/components/custom/Article.php',
            '<?php declare(strict_types=1); echo "article:" . (is_object($archive) ? "archive" : "none");'
        );
        file_put_contents(
            $this->root . '/app/components/Broken.php',
            '<?php declare(strict_types=1); echo "partial"; throw new \RuntimeException("broken component");'
        );
    }

    protected function tearDown(): void
    {
        Options::reset();
        Schema::reset();
        @unlink($this->root . '/app/config/theme.php');
        @unlink($this->root . '/app/components/Post.php');
        @unlink($this->root . '/app/components/custom/Article.php');
        @unlink($this->root . '/app/components/Broken.php');
        @rmdir($this->root . '/app/components/custom');
        @rmdir($this->root . '/app/components');
        @rmdir($this->root . '/app/config');
        @rmdir($this->root . '/app');
        @rmdir($this->root);
    }

    public function testMappingUsesSchemaDefaultBeforeSettingsAreSaved(): void
    {
        file_put_contents($this->root . '/app/config/theme.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'Templates' => [
        'title' => 'Templates',
        'fields' => [
            [
                'type' => 'TemplateMap',
                'name' => 'Templates',
                'value' => ['post' => 'Post', '404' => 'NotFound'],
                'components' => ['Post', 'Page', 'NotFound'],
            ],
        ],
    ],
];
PHP);

        $store = new InMemoryOptionsStore();
        $options = new Options($store, 'Theme');
        $instance = new \ReflectionProperty(Options::class, 'instance');
        $instance->setValue(null, $options);

        self::assertSame([
            'post' => 'Post',
            '404' => 'NotFound',
        ], Templates::mapping($this->root));

        $options->set('Templates', ['post' => 'Page']);

        self::assertSame([
            'post' => 'Page',
        ], Templates::mapping($this->root));
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
            ['Broken', 'Post', 'custom/Article'],
            Templates::available($this->root)
        );
    }

    public function testFilterMapDropsComponentsOutsideAllowlist(): void
    {
        $map = [
            'post' => 'Post',
            '404' => 'PostMeta',
            'page' => '',
        ];

        self::assertSame([
            'post' => 'Post',
            'page' => '',
        ], Templates::filterMap($map, ['Post', 'Page']));

        self::assertSame([], Templates::filterMap($map, []));
    }

    public function testFilterMapOnlyAllowsNotFoundForThe404Slot(): void
    {
        $map = [
            '404' => 'Post',
        ];

        self::assertSame([], Templates::filterMap($map, ['Post', 'NotFound']));
        self::assertSame(
            ['404' => 'custom/NotFound'],
            Templates::filterMap(['404' => 'custom/NotFound'], ['custom/NotFound'])
        );
        self::assertSame(
            ['404' => 'NotFound'],
            Templates::filterMap(['404' => 'NotFound'], ['Post', 'NotFound'])
        );
    }

    public function testAllowedComponentsSanitizesFieldAllowlist(): void
    {
        self::assertSame(
            ['Post', 'custom/Article'],
            Templates::allowedComponents([
                'components' => ['Post', ' custom\\Article ', '../Secret', 'bad name', 123, 'Post'],
            ])
        );

        self::assertSame([], Templates::allowedComponents(null));
        self::assertSame([], Templates::allowedComponents(['components' => 'Post']));
    }

    public function testRenderDiscardsPartialOutputWhenComponentFails(): void
    {
        ob_start();
        $rendered = Templates::render($this->root, '404', new \stdClass(), 'Broken', [
            '404' => 'Broken',
        ]);
        $html = (string) ob_get_clean();

        self::assertFalse($rendered);
        self::assertSame('', $html);
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
