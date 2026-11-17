<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TTDF\Config\Config;
use TTDF\Hooks\Hooks;
use TTDF\Http\Response;
use TTDF\Http\ResponseException;
use TTDF\Http\Rest\PublicController;
use Typecho\Db;
use Typecho\Db\Query;

final class PublicControllerTest extends TestCase
{
    protected function setUp(): void
    {
        $_GET = [];
        Config::load(['rest' => ['enabled' => true, 'v1_prefix' => 'ttdf/v1']]);
        Db::reset();
        Hooks::reset();
        Response::capture(true);
    }

    protected function tearDown(): void
    {
        Response::capture(false);
        Config::reset();
        Db::reset();
        Hooks::reset();
        \Helper::$optionsOverride = null;
        $_GET = [];
    }

    public function testPostListFiltersVisibilityAndClampsPagination(): void
    {
        $_GET = ['page' => '2', 'pageSize' => '999', 'category' => 'news'];
        Db::$fetcher = static function (Query $query): array {
            if (in_array('COUNT(*) AS num', $query->fields, true)) {
                return [['num' => 1]];
            }

            return match ($query->table) {
                'table.contents' => [[
                    'cid' => 7,
                    'title' => 'Hello',
                    'slug' => 'hello',
                    'created' => 1_700_000_000,
                    'modified' => 1_700_000_100,
                    'text' => '# Hello',
                    'commentsNum' => 2,
                    'authorId' => 1,
                    'type' => 'post',
                ]],
                'table.metas' => [[
                    'mid' => 3,
                    'name' => 'News',
                    'slug' => 'news',
                    'description' => '',
                    'order' => 1,
                ]],
                'table.fields' => [
                    ['name' => 'TTDF_Fields_Text', 'value' => 'cover.jpg'],
                    ['name' => 'private_note', 'value' => 'secret'],
                ],
                'table.users' => [['uid' => 1, 'name' => 'admin', 'screenName' => 'Admin']],
                default => [],
            };
        };

        Hooks::addFilter('rest.post.fields', static function (array $fields): array {
            $fields['cover'] = 'filtered.jpg';
            return $fields;
        });

        $payload = $this->capture(static fn () => (new PublicController())->posts())->payload();

        self::assertSame(200, $payload['code']);
        self::assertSame(2, $payload['meta']['page']);
        self::assertSame(50, $payload['meta']['pageSize']);
        self::assertSame(1, $payload['meta']['total']);
        self::assertSame(1, $payload['meta']['lastPage']);
        self::assertSame(7, $payload['data'][0]['cid']);
        self::assertSame('https://example.test/archives/7/', $payload['data'][0]['permalink']);
        self::assertSame('news', $payload['data'][0]['categories'][0]['slug']);
        self::assertSame('cover.jpg', $payload['data'][0]['fields']['TTDF_Fields_Text']);
        self::assertSame('filtered.jpg', $payload['data'][0]['fields']['cover']);
        self::assertArrayNotHasKey('private_note', $payload['data'][0]['fields']);
        self::assertSame('Admin', $payload['data'][0]['author']['screenName']);

        $query = $this->findQuery('table.contents', 'title');
        self::assertSame(50, $query->limit);
        self::assertSame(50, $query->offset);
        self::assertSame('created', $query->orders[0]['field']);
        self::assertSame('DESC', $query->orders[0]['order']);
        self::assertCondition($query, 'type = ?', ['post']);
        self::assertCondition($query, 'status = ?', ['publish']);
        self::assertCondition($query, '(password IS NULL OR password = ?)', ['']);
    }

    public function testCategoryFilterNeverInterpolatesUserInput(): void
    {
        $_GET = ['category' => "tag' OR 1=1 --"];
        Db::$fetcher = static function (Query $query): array {
            if (in_array('COUNT(*) AS num', $query->fields, true)) {
                return [['num' => 0]];
            }

            if ($query->table === 'table.relationships') {
                return [['cid' => 7]];
            }

            return [];
        };

        $this->capture(static fn () => (new PublicController())->posts());

        $terms = $this->findQuery('table.relationships');
        self::assertCondition($terms, 'table.metas.type = ?', ['category']);
        self::assertCondition($terms, 'table.metas.slug = ?', ["tag' OR 1=1 --"]);

        $query = $this->findQuery('table.contents', 'title');
        self::assertCondition($query, 'cid IN ?', [[7]]);
        foreach ($query->wheres as $where) {
            self::assertStringNotContainsString('OR 1=1', $where['condition']);
        }
    }

    public function testSearchRequiresQuery(): void
    {
        $exception = $this->capture(static fn () => (new PublicController())->search());

        self::assertSame(400, $exception->status());
        self::assertSame('Missing q', $exception->payload()['message']);
    }

    public function testZeroExcerptLengthReturnsEmptyExcerpt(): void
    {
        $_GET = ['excerptLength' => '0'];
        Db::$fetcher = static function (Query $query): array {
            if (in_array('COUNT(*) AS num', $query->fields, true)) {
                return [['num' => 1]];
            }

            if ($query->table === 'table.contents') {
                return [[
                    'cid' => 7,
                    'title' => 'Hello',
                    'slug' => 'hello',
                    'created' => 1_700_000_000,
                    'modified' => 1_700_000_000,
                    'text' => 'Hello world',
                    'commentsNum' => 0,
                    'authorId' => 0,
                    'type' => 'post',
                ]];
            }

            return [];
        };

        $payload = $this->capture(static fn () => (new PublicController())->posts())->payload();

        self::assertSame('', $payload['data'][0]['excerpt']);
    }

    public function testPermalinksUseIndexPhpWhenRewriteIsDisabled(): void
    {
        $options = new \stdClass();
        $options->siteUrl = 'https://example.test/';
        $options->index = 'https://example.test/index.php/';
        \Helper::$optionsOverride = $options;

        Db::$fetcher = static function (Query $query): array {
            if (in_array('COUNT(*) AS num', $query->fields, true)) {
                return [['num' => 1]];
            }

            if ($query->table === 'table.contents') {
                return [[
                    'cid' => 7,
                    'title' => 'Hello',
                    'slug' => 'hello',
                    'created' => 1_700_000_000,
                    'modified' => 1_700_000_000,
                    'text' => 'Hello',
                    'commentsNum' => 0,
                    'authorId' => 0,
                    'type' => 'post',
                ]];
            }

            return [];
        };

        $payload = $this->capture(static fn () => (new PublicController())->posts())->payload();

        self::assertSame(
            'https://example.test/index.php/archives/7/',
            $payload['data'][0]['permalink']
        );
    }

    public function testCommentsOnlyExposeApprovedSafeFields(): void
    {
        $_GET = ['cid' => '5', 'order' => 'desc'];
        Db::$fetcher = static function (Query $query): array {
            if (in_array('COUNT(*) AS num', $query->fields, true)) {
                return [['num' => 1]];
            }

            if ($query->table === 'table.contents') {
                return [[
                    'cid' => 5,
                    'title' => 'Post',
                    'slug' => 'post',
                    'created' => 1_700_000_000,
                    'modified' => 1_700_000_000,
                    'text' => 'Body',
                    'commentsNum' => 1,
                    'authorId' => 1,
                    'type' => 'post',
                ]];
            }

            if ($query->table === 'table.comments') {
                return [[
                    'coid' => 9,
                    'cid' => 5,
                    'author' => 'Alice',
                    'url' => 'javascript:alert(1)',
                    'text' => '<script>alert(1)</script>Hi',
                    'parent' => 0,
                    'created' => 1_700_000_200,
                    'authorId' => 0,
                    'mail' => 'alice@example.test',
                    'ip' => '203.0.113.9',
                    'agent' => 'secret-agent',
                ]];
            }

            return [];
        };

        $payload = $this->capture(static fn () => (new PublicController())->comments())->payload();
        $comment = $payload['data'][0];

        self::assertSame(9, $comment['coid']);
        self::assertSame('', $comment['url']);
        self::assertStringNotContainsString('<script', $comment['text']);
        self::assertStringContainsString('Hi', $comment['text']);
        self::assertArrayHasKey('avatar', $comment);
        self::assertArrayNotHasKey('mail', $comment);
        self::assertArrayNotHasKey('ip', $comment);
        self::assertArrayNotHasKey('agent', $comment);

        $query = $this->findQuery('table.comments', 'coid');
        self::assertCondition($query, 'cid = ?', [5]);
        self::assertCondition($query, 'status = ?', ['approved']);
        self::assertSame('DESC', $query->orders[0]['order']);
        self::assertContains('mail', $query->fields);
        self::assertNotContains('ip', $query->fields);
        self::assertNotContains('agent', $query->fields);
    }

    public function testAttachmentsOnlyExposeVisibleParentsAndUnattachedFiles(): void
    {
        Db::$fetcher = static function (Query $query): array {
            if ($query->table === 'table.contents' && $query->fields === ['cid']) {
                return [['cid' => 1], ['cid' => 2]];
            }

            if (in_array('COUNT(*) AS num', $query->fields, true)) {
                return [['num' => 3]];
            }

            if ($query->table === 'table.contents' && in_array('title', $query->fields, true)) {
                return [
                    [
                        'cid' => 10,
                        'title' => 'photo',
                        'text' => '{"name":"photo.jpg","path":"/usr/uploads/photo.jpg","size":2048,"type":"jpg","mime":"image/jpeg"}',
                        'parent' => 1,
                        'created' => 1_700_000_000,
                        'modified' => 1_700_000_000,
                    ],
                    [
                        'cid' => 11,
                        'title' => 'doc',
                        'text' => '{"name":"doc.pdf","path":"/usr/uploads/doc.pdf","size":4096,"type":"pdf","mime":"application/pdf"}',
                        'parent' => 2,
                        'created' => 1_700_000_000,
                        'modified' => 1_700_000_000,
                    ],
                    [
                        'cid' => 14,
                        'title' => 'orphan',
                        'text' => '{"name":"orphan.png","path":"/usr/uploads/orphan.png","size":512,"type":"png","mime":"image/png"}',
                        'parent' => 0,
                        'created' => 1_700_000_000,
                        'modified' => 1_700_000_000,
                    ],
                ];
            }

            return [];
        };

        $payload = $this->capture(static fn () => (new PublicController())->attachments())->payload();

        self::assertSame(3, $payload['meta']['total']);
        self::assertCount(3, $payload['data']);
        self::assertTrue($payload['data'][0]['isImage']);
        self::assertSame('https://example.test/usr/uploads/photo.jpg', $payload['data'][0]['url']);
        self::assertFalse($payload['data'][1]['isImage']);
        self::assertSame(0, $payload['data'][2]['parent']);

        $query = $this->findQuery('table.contents', 'title');
        $visibility = $this->findWhere($query, 'parent IN ?');
        self::assertSame('(parent = 0 OR parent IN ?)', $visibility['condition']);
        self::assertSame([[1, 2]], $visibility['values']);
    }

    public function testAttachmentsFallBackToUnattachedWhenNoParentIsVisible(): void
    {
        Db::$fetcher = static function (Query $query): array {
            if (in_array('COUNT(*) AS num', $query->fields, true)) {
                return [['num' => 0]];
            }

            return [];
        };

        $this->capture(static fn () => (new PublicController())->attachments());

        $query = $this->findQuery('table.contents', 'title');
        self::assertCondition($query, 'parent = 0', []);
    }

    /**
     * @param callable(): void $callback
     */
    private function capture(callable $callback): ResponseException
    {
        try {
            $callback();
        } catch (ResponseException $exception) {
            return $exception;
        }

        self::fail('Expected a captured response');
    }

    private function findQuery(string $table, ?string $field = null): Query
    {
        foreach (Db::$executed as $query) {
            if ($query->table !== $table) {
                continue;
            }

            if ($field !== null && !in_array($field, $query->fields, true)) {
                continue;
            }

            return $query;
        }

        self::fail(sprintf('Query for %s was not executed', $table));
    }

    /**
     * @param list<mixed> $values
     */
    private function assertCondition(Query $query, string $condition, array $values): void
    {
        $where = $this->findWhere($query, $condition);
        self::assertSame($values, $where['values']);
    }

    /**
     * @return array{condition: string, values: list<mixed>}
     */
    private function findWhere(Query $query, string $fragment): array
    {
        foreach ($query->wheres as $where) {
            if (str_contains($where['condition'], $fragment)) {
                return $where;
            }
        }

        self::fail(sprintf('Condition containing "%s" was not found', $fragment));
    }
}
