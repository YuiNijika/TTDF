<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TTDF\Compat\Upload;
use TTDF\Http\Response;
use TTDF\Http\ResponseException;
use TTDF\Http\Rest\MediaController;
use TTDF\Theme\Media;
use Typecho\Db;
use Typecho\Db\Query;

final class MediaTest extends TestCase
{
    protected function setUp(): void
    {
        Db::reset();
        Response::capture(true);
        $_GET = [];
    }

    protected function tearDown(): void
    {
        Response::capture(false);
        Db::reset();
        $_GET = [];
    }

    public function testThemeMediaResolvesAnAttachmentIdToItsUrl(): void
    {
        Db::$fetcher = static fn (Query $query): array => $query->table === 'table.contents'
            ? [[
                'cid' => 12,
                'title' => 'Brand &amp; Logo',
                'text' => json_encode([
                    'name' => 'logo.png',
                    'path' => '/usr/uploads/2026/11/logo.png',
                    'size' => 2048,
                    'type' => 'png',
                    'mime' => 'image/png',
                ], JSON_THROW_ON_ERROR),
            ]]
            : [];

        $metadata = Media::metadata('12');

        self::assertSame(12, $metadata['id']);
        self::assertSame('Brand & Logo', $metadata['title']);
        self::assertSame('https://example.test/usr/uploads/2026/11/logo.png', $metadata['url']);
        self::assertTrue($metadata['isImage']);
        self::assertSame($metadata['url'], Media::url('12'));
    }

    public function testThemeMediaAcceptsHttpUrlsAndRejectsUnsafeValues(): void
    {
        self::assertSame(
            'https://cdn.example.test/logo.png',
            Media::url('https://cdn.example.test/logo.png')
        );
        self::assertSame('/usr/uploads/2026/11/logo.png', Media::url('/usr/uploads/2026/11/logo.png'));
        self::assertSame('', Media::url('javascript:alert(1)'));
        self::assertSame('', Media::url('//cdn.example.test/logo.png'));
        self::assertSame('', Media::url('999'));
    }

    public function testTextMediaValuesKeepUrlsAndLegacyIds(): void
    {
        self::assertSame('https://cdn.example.test/logo.png', Media::normalizeUrl(' https://cdn.example.test/logo.png '));
        self::assertSame('/usr/uploads/2026/11/logo.png', Media::normalizeUrl('/usr/uploads/2026/11/logo.png'));
        self::assertSame('42', Media::normalizeUrl(' 42 '));
        self::assertSame('', Media::normalizeUrl('javascript:alert(1)'));
        self::assertSame('', Media::normalizeUrl('//cdn.example.test/logo.png'));
        self::assertSame('', Media::normalizeUrl('usr/uploads/logo.png'));
        self::assertSame('', Media::normalizeUrl(['https://cdn.example.test/logo.png']));
    }

    public function testTextareaMediaValuesKeepOneUrlPerLine(): void
    {
        self::assertSame(
            "https://cdn.example.test/a.png\n/usr/uploads/b.png",
            Media::normalizeUrlList(" https://cdn.example.test/a.png \r\njavascript:alert(1)\n\n/usr/uploads/b.png\n//evil.test/c.png")
        );
        self::assertSame('', Media::normalizeUrlList(['https://cdn.example.test/a.png']));
    }

    public function testMediaListKeepsImageAttachmentsAndClampsPagination(): void
    {
        $_GET = ['page' => '2', 'pageSize' => '999', 'search' => 'logo'];
        Db::$fetcher = static function (Query $query): array {
            if (in_array('COUNT(*) AS num', $query->fields, true)) {
                return [['num' => 130]];
            }

            if ($query->table !== 'table.contents') {
                return [];
            }

            return [
                [
                    'cid' => 3,
                    'title' => 'Logo',
                    'text' => json_encode([
                        'name' => 'logo.png',
                        'path' => '/usr/uploads/logo.png',
                        'size' => 1024,
                        'type' => 'png',
                        'mime' => 'image/png',
                    ], JSON_THROW_ON_ERROR),
                    'created' => 100,
                    'modified' => 100,
                    'authorId' => 1,
                ],
                [
                    'cid' => 4,
                    'title' => 'Document',
                    'text' => json_encode([
                        'name' => 'doc.pdf',
                        'path' => '/usr/uploads/doc.pdf',
                        'size' => 1024,
                        'type' => 'pdf',
                        'mime' => 'application/pdf',
                    ], JSON_THROW_ON_ERROR),
                    'created' => 99,
                    'modified' => 99,
                    'authorId' => 1,
                ],
            ];
        };

        $payload = $this->capture(static fn () => (new MediaController())->index())->payload();

        self::assertSame(2, $payload['data']['page']);
        self::assertSame(60, $payload['data']['pageSize']);
        self::assertSame(130, $payload['data']['total']);
        self::assertSame(3, $payload['data']['lastPage']);
        self::assertCount(1, $payload['data']['items']);
        self::assertSame(3, $payload['data']['items'][0]['id']);

        $query = $this->findMediaQuery();
        self::assertSame(60, $query->limit);
        self::assertSame(60, $query->offset);
        self::assertSame('table.contents.created', $query->orders[0]['field']);
        self::assertCondition($query, 'table.contents.type = ?', ['attachment']);
        self::assertCondition($query, 'table.contents.status = ?', ['publish']);
        self::assertCondition($query, 'table.contents.title LIKE ?', ['%logo%']);
    }

    public function testMediaItemsIgnoreNonImageAttachments(): void
    {
        self::assertNull(MediaController::itemFromRow([
            'cid' => 9,
            'title' => 'Document',
            'text' => '{"path":"/usr/uploads/doc.pdf","type":"pdf","mime":"application/pdf"}',
        ]));

        $item = MediaController::itemFromRow([
            'cid' => 10,
            'title' => 'Photo &amp; Logo',
            'text' => '{"path":"/usr/uploads/photo.jpg","type":"jpg","mime":"image/jpeg","size":4096}',
        ]);

        self::assertIsArray($item);
        self::assertSame('Photo & Logo', $item['title']);
        self::assertSame('https://example.test/usr/uploads/photo.jpg', $item['url']);
    }

    public function testMediaTitleAndExtensionAreNormalized(): void
    {
        self::assertSame('Brand logo', MediaController::normalizeTitle("  Brand\n logo  "));
        self::assertSame(120, strlen(MediaController::normalizeTitle(str_repeat('a', 140))));
        self::assertSame('jpg', MediaController::imageExtension('LOGO.JPG'));
        self::assertSame('', MediaController::imageExtension('shell.php'));
    }

    public function testUploadDeleteGuardOnlyAcceptsTypechoUploadsDirectory(): void
    {
        self::assertTrue(Upload::isUploadPath('usr/uploads/2026/11/logo.png'));
        self::assertTrue(Upload::isUploadPath('/usr/uploads/2026/11/logo.png'));
        self::assertFalse(Upload::isUploadPath('usr/uploads/../config.inc.php'));
        self::assertFalse(Upload::isUploadPath('usr/uploadsX/logo.png'));
        self::assertFalse(Upload::isUploadPath(''));
    }

    private function findMediaQuery(): Query
    {
        $queries = array_values(array_filter(
            Db::$executed,
            static fn (Query $query): bool => $query->table === 'table.contents'
                && !in_array('COUNT(*) AS num', $query->fields, true)
        ));

        self::assertNotEmpty($queries);

        return $queries[array_key_last($queries)];
    }

    /**
     * @param list<mixed> $values
     */
    private function assertCondition(Query $query, string $condition, array $values): void
    {
        foreach ($query->wheres as $where) {
            if ($where['condition'] === $condition) {
                self::assertSame($values, $where['values']);
                return;
            }
        }

        self::fail('Condition not found: ' . $condition);
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
}
