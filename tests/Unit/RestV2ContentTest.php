<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use TTDF\Framework;
use TTDF\Http\Rest\V2\Users;

require_once __DIR__ . '/V2ApiTestCase.php';

final class RestV2ContentTest extends V2ApiTestCase
{
    public function testAdminDashboardReportsVersionsAndCounts(): void
    {
        $this->signInWithCookie(1);

        $payload = $this->dispatch('admin/site')->payload();

        $this->assertSuccess(200, $payload);
        self::assertSame(Framework::VERSION, $payload['data']['versions']['framework']);
        self::assertSame(1, $payload['data']['counts']['post']);
        self::assertSame(1, $payload['data']['counts']['page']);
        self::assertSame(3, $payload['data']['counts']['user']);
        self::assertSame(1, $payload['data']['counts']['category']);
        self::assertSame(0, $payload['data']['counts']['draft']);
    }

    public function testDiagnosticsReuseTheVersionIndependentController(): void
    {
        $this->signInWithCookie(1);

        $payload = $this->dispatch('admin/diagnostics')->payload();

        $this->assertSuccess(200, $payload);
        self::assertSame(Framework::VERSION, $payload['data']['frameworkVersion']);
    }

    public function testMediaIndexReusesTheVersionIndependentController(): void
    {
        $this->signInWithCookie(1);

        $payload = $this->dispatch('admin/media')->payload();

        $this->assertSuccess(200, $payload);
        self::assertSame([], $payload['data']['items']);
    }

    public function testCreatingAPostLinksImagesInsertedThroughTheApi(): void
    {
        $this->signInWithCookie(1);

        $tables = $this->tables();
        $tables['table.contents'][] = $this->attachmentRow(30, 1, '/usr/uploads/cover.png');
        $tables['table.contents'][] = $this->attachmentRow(31, 2, '/usr/uploads/other.png');
        V2FakeDb::install($tables);

        $payload = $this->dispatch(
            'admin/posts',
            'POST',
            [
                'title' => 'Linked media',
                'text' => '<p><img src="https://example.test/usr/uploads/cover.png" alt="cover"></p>',
            ],
            ['Origin' => 'https://example.test']
        )->payload();

        $this->assertSuccess(201, $payload);
        self::assertSame(
            (int) $payload['data']['cid'],
            (int) V2FakeDb::row('table.contents', 30)['parent']
        );
        self::assertSame(0, (int) V2FakeDb::row('table.contents', 31)['parent']);
    }

    public function testCreatingAPostLinksImagesReferencedByCustomFields(): void
    {
        $this->signInWithCookie(1);

        $tables = $this->tables();
        $tables['table.contents'][] = $this->attachmentRow(30, 1, '/usr/uploads/cover.png');
        V2FakeDb::install($tables);

        $payload = $this->dispatch(
            'admin/posts',
            'POST',
            [
                'title' => 'Field media',
                'text' => 'Body without an image',
                'fields' => [
                    'TTDF_Fields_Text' => 'https://example.test/usr/uploads/cover.png',
                ],
            ],
            ['Origin' => 'https://example.test']
        )->payload();

        $this->assertSuccess(201, $payload);
        self::assertSame(
            (int) $payload['data']['cid'],
            (int) V2FakeDb::row('table.contents', 30)['parent']
        );
    }

    public function testCreatingAPostStoresTermsAndReturnsTheDetail(): void
    {
        $this->signInWithCookie(1);

        $payload = $this->dispatch(
            'admin/posts',
            'POST',
            ['title' => 'Framework notes', 'text' => 'Body', 'tags' => ['php', 'ttdf']],
            ['Origin' => 'https://example.test']
        )->payload();

        $this->assertSuccess(201, $payload);
        self::assertSame('Framework notes', $payload['data']['title']);
        self::assertSame('framework-notes', $payload['data']['slug']);
        self::assertSame('post', $payload['data']['kind']);
        self::assertSame('publish', $payload['data']['status']);
        self::assertSame(1, $payload['data']['authorId']);
        self::assertSame('https://example.test/archives/12/', $payload['data']['permalink']);

        $tags = array_column($payload['data']['tags'], 'name');
        sort($tags);
        self::assertSame(['php', 'ttdf'], $tags);

        $stored = V2FakeDb::row('table.contents', 12);
        self::assertNotNull($stored);
        self::assertSame('post', $stored['type']);

        $linked = [];
        foreach (V2FakeDb::rows('table.relationships') as $row) {
            if ((int) $row['cid'] === 12) {
                $linked[] = (int) $row['mid'];
            }
        }
        sort($linked);

        $expected = array_map(static fn (array $tag): int => (int) $tag['mid'], $payload['data']['tags']);
        sort($expected);

        self::assertSame($expected, $linked);
    }

    public function testCreatingAPostStoresMarkdownAndReturnsCleanEditorText(): void
    {
        $this->signInWithCookie(1);

        $payload = $this->dispatch(
            'admin/posts',
            'POST',
            ['title' => 'Markdown body', 'text' => '## Heading'],
            ['Origin' => 'https://example.test']
        )->payload();

        $this->assertSuccess(201, $payload);
        self::assertSame('## Heading', $payload['data']['text']);
        self::assertSame([], $payload['data']['attachments']);

        $stored = (string) V2FakeDb::row('table.contents', (int) $payload['data']['cid'])['text'];
        self::assertStringStartsWith('<!--markdown-->', $stored);
        self::assertStringNotContainsString('<!--markdown-->## Heading<!--markdown-->', $stored);
    }

    public function testCreatingAPostLinksAttachmentCidReferencesAndStoresDefinitions(): void
    {
        $this->signInWithCookie(1);

        $tables = $this->tables();
        $tables['table.contents'][] = $this->attachmentRow(30, 1, '/usr/uploads/cover.png');
        V2FakeDb::install($tables);

        $payload = $this->dispatch(
            'admin/posts',
            'POST',
            [
                'title' => 'Referenced media',
                'text' => "Before\n\n![cover.png][30]\n\nAfter",
                'attachments' => [30],
            ],
            ['Origin' => 'https://example.test']
        )->payload();

        $this->assertSuccess(201, $payload);
        self::assertSame('Referenced media', $payload['data']['title']);
        self::assertSame(
            "Before\n\n![cover.png][30]\n\nAfter",
            $payload['data']['text']
        );
        self::assertSame(30, $payload['data']['attachments'][0]['id']);
        self::assertSame(30, $payload['data']['attachments'][0]['cid']);

        $cid = (int) $payload['data']['cid'];
        self::assertSame($cid, (int) V2FakeDb::row('table.contents', 30)['parent']);

        $stored = (string) V2FakeDb::row('table.contents', $cid)['text'];
        self::assertStringStartsWith('<!--markdown-->', $stored);
        self::assertStringContainsString(
            '[30]: https://example.test/usr/uploads/cover.png',
            $stored
        );
    }

    public function testUpdatingAPostDetachesRemovedAttachmentReferences(): void
    {
        $this->signInWithCookie(1);

        $tables = $this->tables();
        $tables['table.contents'][] = $this->attachmentRow(30, 1, '/usr/uploads/cover.png');
        V2FakeDb::install($tables);

        $created = $this->dispatch(
            'admin/posts',
            'POST',
            [
                'title' => 'Detach media',
                'text' => '![cover.png][30]',
                'attachments' => [30],
            ],
            ['Origin' => 'https://example.test']
        )->payload();
        $this->assertSuccess(201, $created);

        $payload = $this->dispatch(
            'admin/posts/' . (int) $created['data']['cid'],
            'PATCH',
            ['text' => 'Body without images', 'attachments' => []],
            ['Origin' => 'https://example.test']
        )->payload();

        $this->assertSuccess(200, $payload);
        self::assertSame('Body without images', $payload['data']['text']);
        self::assertSame([], $payload['data']['attachments']);
        self::assertSame(0, (int) V2FakeDb::row('table.contents', 30)['parent']);
    }

    public function testAdminEditingAnotherAuthorsPostLinksOwnAttachments(): void
    {
        $this->signInWithCookie(1);

        $tables = $this->tables();
        $tables['table.contents'][] = $this->attachmentRow(32, 1, '/usr/uploads/admin-cover.png');
        V2FakeDb::install($tables);

        $created = $this->dispatch(
            'admin/posts',
            'POST',
            [
                'title' => 'Managed by an administrator',
                'text' => 'Original body',
                'authorId' => 2,
            ],
            ['Origin' => 'https://example.test']
        )->payload();
        $this->assertSuccess(201, $created);
        self::assertSame(2, (int) $created['data']['authorId']);

        $cid = (int) $created['data']['cid'];
        $payload = $this->dispatch(
            'admin/posts/' . $cid,
            'PATCH',
            [
                'text' => "Before\n\n![admin-cover.png][32]\n\nAfter",
                'attachments' => [32],
            ],
            ['Origin' => 'https://example.test']
        )->payload();

        $this->assertSuccess(200, $payload);
        self::assertSame(32, $payload['data']['attachments'][0]['id']);
        self::assertSame($cid, (int) V2FakeDb::row('table.contents', 32)['parent']);
        self::assertStringContainsString(
            '[32]: https://example.test/usr/uploads/admin-cover.png',
            (string) V2FakeDb::row('table.contents', $cid)['text']
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function attachmentRow(int $cid, int $authorId, string $path): array
    {
        return [
            'cid' => $cid,
            'title' => basename($path),
            'slug' => 'media-' . $cid,
            'type' => 'attachment',
            'status' => 'publish',
            'created' => 1_700_003_000,
            'modified' => 1_700_003_000,
            'text' => json_encode([
                'name' => basename($path),
                'path' => $path,
                'size' => 128,
                'type' => 'png',
                'mime' => 'image/png',
            ], JSON_THROW_ON_ERROR),
            'authorId' => $authorId,
            'commentsNum' => 0,
            'password' => '',
            'template' => '',
            'order' => 0,
            'allowComment' => 0,
            'allowPing' => 0,
            'allowFeed' => 0,
            'parent' => 0,
        ];
    }

    public function testCreatingAPostRequiresATitle(): void
    {
        $this->signInWithCookie(1);

        $payload = $this->dispatch(
            'admin/posts',
            'POST',
            ['text' => 'Body'],
            ['Origin' => 'https://example.test']
        )->payload();

        self::assertSame(422, $payload['code']);
        self::assertSame(['title' => 'is required'], $payload['errors']);
    }

    public function testCreatingAPostRejectsAnUnknownStatus(): void
    {
        $this->signInWithCookie(1);

        $payload = $this->dispatch(
            'admin/posts',
            'POST',
            ['title' => 'Nope', 'status' => 'archived'],
            ['Origin' => 'https://example.test']
        )->payload();

        self::assertSame(422, $payload['code']);
        self::assertSame('Invalid content status', $payload['message']);
    }

    public function testSlugsAreMadeUniqueAcrossTypes(): void
    {
        $this->signInWithCookie(1);

        $payload = $this->dispatch(
            'admin/posts',
            'POST',
            ['title' => 'Hello world'],
            ['Origin' => 'https://example.test']
        )->payload();

        $this->assertSuccess(201, $payload);
        self::assertSame('hello-world-2', $payload['data']['slug']);
    }

    public function testADraftKeepsItsRowAndItsSlug(): void
    {
        $this->signInWithCookie(1);

        $draft = $this->dispatch(
            'admin/posts',
            'POST',
            ['title' => 'Slug clash', 'status' => 'draft'],
            ['Origin' => 'https://example.test']
        )->payload();

        $this->assertSuccess(201, $draft);
        self::assertSame('draft', $draft['data']['status']);
        self::assertSame('post', $draft['data']['kind']);
        self::assertSame('', $draft['data']['permalink']);
        self::assertSame('post_draft', V2FakeDb::row('table.contents', 12)['type']);

        $published = $this->dispatch(
            'admin/posts',
            'POST',
            ['title' => 'Slug clash'],
            ['Origin' => 'https://example.test']
        )->payload();

        self::assertSame('slug-clash-2', $published['data']['slug']);
    }

    public function testPublishingADraftTurnsItBackIntoAPost(): void
    {
        $this->signInWithCookie(1);

        $this->dispatch(
            'admin/posts',
            'POST',
            ['title' => 'Promoted', 'status' => 'draft'],
            ['Origin' => 'https://example.test']
        );

        $payload = $this->dispatch(
            'admin/posts/12',
            'PATCH',
            ['status' => 'publish'],
            ['Origin' => 'https://example.test']
        )->payload();

        $this->assertSuccess(200, $payload);
        self::assertSame('publish', $payload['data']['status']);
        self::assertSame('post', $payload['data']['kind']);
        self::assertSame('post', V2FakeDb::row('table.contents', 12)['type']);
        self::assertSame(0, V2FakeDb::row('table.contents', 12)['parent']);
    }

    public function testUpdatingAPostLeavesUntouchedFieldsAndTermsAlone(): void
    {
        $this->signInWithCookie(1);

        $payload = $this->dispatch(
            'admin/posts/10',
            'PATCH',
            ['title' => 'Renamed'],
            ['Origin' => 'https://example.test']
        )->payload();

        $this->assertSuccess(200, $payload);
        self::assertSame('Renamed', $payload['data']['title']);
        self::assertSame('hello-world', $payload['data']['slug']);
        self::assertSame('Body', $payload['data']['text']);
        self::assertSame([20], array_column($payload['data']['categories'], 'mid'));
        self::assertSame(1, count(V2FakeDb::rows('table.relationships')));
    }

    public function testReplacingCategoriesOnlyKeepsTheRequestedOnes(): void
    {
        $this->signInWithCookie(1);

        $payload = $this->dispatch(
            'admin/posts/10',
            'PATCH',
            ['categories' => []],
            ['Origin' => 'https://example.test']
        )->payload();

        $this->assertSuccess(200, $payload);
        self::assertSame([], $payload['data']['categories']);
        self::assertSame([], V2FakeDb::rows('table.relationships'));
    }

    public function testUpdatingAPostRequiresTheMatchingType(): void
    {
        $this->signInWithCookie(1);

        $payload = $this->dispatch(
            'admin/pages/10',
            'PATCH',
            ['title' => 'Wrong endpoint'],
            ['Origin' => 'https://example.test']
        )->payload();

        self::assertSame(404, $payload['code']);
        self::assertSame('Content not found', $payload['message']);
    }

    public function testDeletingAPostRemovesItsDependentRows(): void
    {
        $this->signInWithCookie(1);

        V2FakeDb::install([
            'table.users' => V2FakeDb::rows('table.users'),
            'table.contents' => array_merge(
                V2FakeDb::rows('table.contents'),
                [
                    [
                        'cid' => 14,
                        'title' => 'cover.png',
                        'slug' => 'media-14',
                        'type' => 'attachment',
                        'status' => 'publish',
                        'created' => 1_700_003_000,
                        'modified' => 1_700_003_000,
                        'text' => json_encode([
                            'name' => 'cover.png',
                            'path' => '/usr/uploads/cover.png',
                            'size' => 128,
                            'type' => 'png',
                            'mime' => 'image/png',
                        ], JSON_THROW_ON_ERROR),
                        'authorId' => 1,
                        'commentsNum' => 0,
                        'password' => '',
                        'template' => '',
                        'order' => 1,
                        'allowComment' => 0,
                        'allowPing' => 0,
                        'allowFeed' => 0,
                        'parent' => 10,
                    ],
                ]
            ),
            'table.metas' => V2FakeDb::rows('table.metas'),
            'table.relationships' => V2FakeDb::rows('table.relationships'),
            'table.comments' => [
                [
                    'coid' => 90,
                    'cid' => 10,
                    'author' => 'Guest',
                    'mail' => 'guest@example.test',
                    'url' => '',
                    'ip' => '203.0.113.9',
                    'agent' => 'tests',
                    'text' => 'Nice',
                    'type' => 'comment',
                    'status' => 'approved',
                    'parent' => 0,
                    'created' => 1_700_002_000,
                    'authorId' => 0,
                    'ownerId' => 1,
                ],
            ],
            'table.fields' => [
                ['cid' => 10, 'name' => 'thumb', 'type' => 'str', 'str_value' => 'x', 'int_value' => 0, 'float_value' => 0],
            ],
            'table.options' => V2FakeDb::rows('table.options'),
        ]);

        $payload = $this->dispatch(
            'admin/posts/10',
            'DELETE',
            null,
            ['Origin' => 'https://example.test']
        )->payload();

        $this->assertSuccess(200, $payload);
        self::assertSame(10, $payload['data']['deleted']);

        self::assertNull(V2FakeDb::row('table.contents', 10));
        self::assertSame([], V2FakeDb::rows('table.relationships'));
        self::assertSame([], V2FakeDb::rows('table.fields'));
        self::assertSame([], V2FakeDb::rows('table.comments'));
        self::assertSame(0, (int) V2FakeDb::row('table.contents', 14)['parent']);
    }

    public function testContentListingFiltersByType(): void
    {
        $this->signInWithCookie(1);

        $payload = $this->dispatch('admin/contents?type=page')->payload();

        $this->assertSuccess(200, $payload);
        self::assertSame(1, $payload['meta']['total']);
        self::assertSame(['About'], array_column($payload['data'], 'title'));
    }

    public function testContentListingHidesDraftsFromStatusFilters(): void
    {
        $this->signInWithCookie(1);

        $this->dispatch(
            'admin/posts',
            'POST',
            ['title' => 'Hidden draft', 'status' => 'draft'],
            ['Origin' => 'https://example.test']
        );

        $published = $this->dispatch('admin/contents?status=publish')->payload();
        $drafts = $this->dispatch('admin/contents?status=draft')->payload();

        self::assertSame(2, $published['meta']['total']);
        self::assertSame(1, $drafts['meta']['total']);
        self::assertSame('Hidden draft', $drafts['data'][0]['title']);
    }

    public function testCategoryCrudKeepsChildrenAtTheRoot(): void
    {
        $this->signInWithCookie(1);

        $created = $this->dispatch(
            'admin/categories',
            'POST',
            ['name' => 'Child', 'parent' => 20],
            ['Origin' => 'https://example.test']
        )->payload();

        $this->assertSuccess(201, $created);
        self::assertSame('category', $created['data']['type']);
        self::assertSame(20, $created['data']['parent']);
        $childMid = (int) $created['data']['mid'];

        $renamed = $this->dispatch(
            'admin/categories/' . $childMid,
            'PATCH',
            ['name' => 'Child renamed'],
            ['Origin' => 'https://example.test']
        )->payload();

        $this->assertSuccess(200, $renamed);
        self::assertSame('Child renamed', $renamed['data']['name']);

        $deleted = $this->dispatch(
            'admin/categories/20',
            'DELETE',
            null,
            ['Origin' => 'https://example.test']
        )->payload();

        $this->assertSuccess(200, $deleted);
        self::assertSame(20, $deleted['data']['deleted']);
        self::assertSame(1, $deleted['data']['contents']);
        self::assertSame(0, V2FakeDb::row('table.metas', $childMid)['parent']);
    }

    public function testCategoryNamesAreUnique(): void
    {
        $this->signInWithCookie(1);

        $payload = $this->dispatch(
            'admin/categories',
            'POST',
            ['name' => '默认分类'],
            ['Origin' => 'https://example.test']
        )->payload();

        self::assertSame(422, $payload['code']);
        self::assertSame('Term already exists', $payload['message']);
    }

    public function testTagEndpointsStoreIntoTheSameTermTable(): void
    {
        $this->signInWithCookie(1);

        $payload = $this->dispatch(
            'admin/tags',
            'POST',
            ['name' => 'PHP'],
            ['Origin' => 'https://example.test']
        )->payload();

        $this->assertSuccess(201, $payload);
        self::assertSame('tag', $payload['data']['type']);
        self::assertSame('tag', V2FakeDb::row('table.metas', (int) $payload['data']['mid'])['type']);
    }

    public function testMissingTermsAnswer404(): void
    {
        $this->signInWithCookie(1);

        $payload = $this->dispatch(
            'admin/tags/999',
            'DELETE',
            null,
            ['Origin' => 'https://example.test']
        )->payload();

        self::assertSame(404, $payload['code']);
        self::assertSame('Term not found', $payload['message']);
    }

    public function testCreatingAUserHidesSecretsAndValidatesInput(): void
    {
        $this->signInWithCookie(1);

        $created = $this->dispatch(
            'admin/users',
            'POST',
            ['name' => 'writer', 'mail' => 'writer@example.test', 'password' => 'secret1'],
            ['Origin' => 'https://example.test']
        )->payload();

        $this->assertSuccess(201, $created);
        self::assertSame('writer', $created['data']['name']);
        self::assertSame('contributor', $created['data']['group']);
        self::assertArrayNotHasKey('password', $created['data']);
        self::assertArrayNotHasKey('authCode', $created['data']);

        $bad = $this->dispatch(
            'admin/users',
            'POST',
            ['name' => 'not valid', 'mail' => 'x@example.test', 'password' => 'secret1'],
            ['Origin' => 'https://example.test']
        )->payload();

        self::assertSame(422, $bad['code']);

        $short = $this->dispatch(
            'admin/users',
            'POST',
            ['name' => 'shorty', 'mail' => 'shorty@example.test', 'password' => '123'],
            ['Origin' => 'https://example.test']
        )->payload();

        self::assertSame(422, $short['code']);

        $badMail = $this->dispatch(
            'admin/users',
            'POST',
            ['name' => 'mailer', 'mail' => 'not-an-email', 'password' => 'secret1'],
            ['Origin' => 'https://example.test']
        )->payload();

        self::assertSame(422, $badMail['code']);
        self::assertSame(['mail' => '电子邮箱格式错误'], $badMail['errors']);
    }

    public function testUserListingNeverExposesCredentials(): void
    {
        $this->signInWithCookie(1);

        $payload = $this->dispatch('admin/users')->payload();

        $this->assertSuccess(200, $payload);
        self::assertSame(3, $payload['meta']['total']);

        foreach ($payload['data'] as $user) {
            self::assertArrayNotHasKey('password', $user);
            self::assertArrayNotHasKey('authCode', $user);
        }
    }

    public function testChangingAPasswordRotatesTheAuthCode(): void
    {
        $this->signInWithCookie(1);

        $before = V2FakeDb::row('table.users', 2)['authCode'];

        $payload = $this->dispatch(
            'admin/users/2',
            'PATCH',
            ['password' => 'another-secret'],
            ['Origin' => 'https://example.test']
        )->payload();

        $this->assertSuccess(200, $payload);
        self::assertNotSame($before, V2FakeDb::row('table.users', 2)['authCode']);
        self::assertArrayNotHasKey('authCode', $payload['data']);
    }

    public function testUserUpdatesRejectInvalidEmailAddresses(): void
    {
        $this->signInWithCookie(1);

        $payload = $this->dispatch(
            'admin/users/2',
            'PATCH',
            ['mail' => 'broken-mail'],
            ['Origin' => 'https://example.test']
        )->payload();

        self::assertSame(422, $payload['code']);
        self::assertSame(['mail' => '电子邮箱格式错误'], $payload['errors']);
    }

    public function testAdministratorCannotDemoteOrDeleteThemselves(): void
    {
        $this->signInWithCookie(1);

        $demote = $this->dispatch(
            'admin/users/1',
            'PATCH',
            ['group' => 'editor'],
            ['Origin' => 'https://example.test']
        )->payload();

        self::assertSame(409, $demote['code']);
        self::assertSame('You cannot demote your own account', $demote['message']);

        $delete = $this->dispatch(
            'admin/users/1',
            'DELETE',
            null,
            ['Origin' => 'https://example.test']
        )->payload();

        self::assertSame(409, $delete['code']);
        self::assertSame('You cannot delete your own account', $delete['message']);
    }

    public function testUsersCanBeDeleted(): void
    {
        $this->signInWithCookie(1);

        $payload = $this->dispatch(
            'admin/users/3',
            'DELETE',
            null,
            ['Origin' => 'https://example.test']
        )->payload();

        $this->assertSuccess(200, $payload);
        self::assertSame(3, $payload['data']['deleted']);
        self::assertNull(V2FakeDb::row('table.users', 3));
    }

    public function testTheLastAdministratorIsProtectedInsideTheRepository(): void
    {
        \Widget\User::reset();

        $exception = $this->capture(static fn () => (new Users())->delete(1));

        self::assertSame(409, $exception->status());
        self::assertStringContainsString('last administrator', $exception->payload()['message']);
        self::assertNotNull(V2FakeDb::row('table.users', 1));
    }

    public function testProfileUpdatesValidateTheHomepageUrl(): void
    {
        $this->signInWithCookie(3);

        $payload = $this->dispatch(
            'me',
            'PATCH',
            ['url' => 'not-a-url'],
            ['Origin' => 'https://example.test']
        )->payload();

        self::assertSame(422, $payload['code']);
        self::assertSame(['url' => '个人主页地址必须使用 http 或 https'], $payload['errors']);
    }

    public function testProfileUpdatesRejectAnEmptyBody(): void
    {
        $this->signInWithCookie(3);

        $payload = $this->dispatch(
            'me',
            'PATCH',
            ['nope' => 'value'],
            ['Origin' => 'https://example.test']
        )->payload();

        self::assertSame(422, $payload['code']);
    }

    public function testAuthorProfileExposesOnlyPublicColumns(): void
    {
        $payload = $this->dispatch('users/1')->payload();

        $this->assertSuccess(200, $payload);
        self::assertSame('管理员', $payload['data']['screenName']);
        self::assertArrayNotHasKey('password', $payload['data']);
        self::assertArrayNotHasKey('authCode', $payload['data']);
    }

    public function testUnknownAuthorAnswers404(): void
    {
        $payload = $this->dispatch('users/404')->payload();

        self::assertSame(404, $payload['code']);
        self::assertSame('User not found', $payload['message']);
    }
}
