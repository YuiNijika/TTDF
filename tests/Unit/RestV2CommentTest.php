<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

require_once __DIR__ . '/V2ApiTestCase.php';

final class RestV2CommentTest extends V2ApiTestCase
{
    public function testAnonymousCommentIsPublishedWhenNothingHoldsItBack(): void
    {
        $payload = $this->dispatch('comments', 'POST', [
            'cid' => 10,
            'text' => 'Nice post',
            'author' => 'Guest',
            'mail' => 'guest@example.test',
        ])->payload();

        $this->assertSuccess(201, $payload);
        self::assertSame(1, $payload['data']['coid']);
        self::assertSame(10, $payload['data']['cid']);
        self::assertSame('Guest', $payload['data']['author']);
        self::assertSame('approved', $payload['data']['status']);
        self::assertTrue($payload['data']['approved']);
        self::assertSame('Nice post', $payload['data']['html']);
        self::assertArrayNotHasKey('mail', $payload['data']);
        self::assertArrayNotHasKey('ip', $payload['data']);
        self::assertArrayNotHasKey('agent', $payload['data']);
        self::assertSame(1, V2FakeDb::row('table.contents', 10)['commentsNum']);
    }

    public function testCommentBodyIsValidated(): void
    {
        $missing = $this->dispatch('comments', 'POST', ['cid' => 10])->payload();
        self::assertSame(422, $missing['code']);
        self::assertSame(['text' => 'is required'], $missing['errors']);

        $unknown = $this->dispatch('comments', 'POST', ['cid' => 999, 'text' => 'Hi'])->payload();
        self::assertSame(404, $unknown['code']);
        self::assertSame('Content not found', $unknown['message']);

        $noAuthor = $this->dispatch('comments', 'POST', ['cid' => 10, 'text' => 'Hi'])->payload();
        self::assertSame(422, $noAuthor['code']);
        self::assertSame(['author' => '必须填写用户名'], $noAuthor['errors']);
    }

    public function testModerationHoldsNewCommentsBack(): void
    {
        $this->overrideSiteOptions(['commentsRequireModeration' => 1]);

        $payload = $this->dispatch('comments', 'POST', [
            'cid' => 10,
            'text' => 'Please review me',
            'author' => 'Guest',
            'mail' => 'guest@example.test',
        ])->payload();

        $this->assertSuccess(201, $payload);
        self::assertSame('waiting', $payload['data']['status']);
        self::assertFalse($payload['data']['approved']);
        self::assertSame(0, V2FakeDb::row('table.contents', 10)['commentsNum']);
    }

    public function testWhitelistWaitsForTheFirstCommentAndTrustsTheSecond(): void
    {
        $this->overrideSiteOptions(['commentsWhitelist' => 1]);

        $first = $this->dispatch('comments', 'POST', [
            'cid' => 10,
            'text' => 'First time here',
            'author' => 'Guest',
            'mail' => 'guest@example.test',
        ])->payload();

        self::assertSame('waiting', $first['data']['status']);

        $this->seedRows('table.comments', [[
            'coid' => 90,
            'cid' => 10,
            'author' => 'Guest',
            'mail' => 'guest@example.test',
            'url' => '',
            'ip' => '198.51.100.4',
            'agent' => 'tests',
            'text' => 'Older comment',
            'type' => 'comment',
            'status' => 'approved',
            'parent' => 0,
            'created' => 1_700_002_000,
            'authorId' => 0,
            'ownerId' => 1,
        ]]);

        $second = $this->dispatch('comments', 'POST', [
            'cid' => 10,
            'text' => 'Second visit',
            'author' => 'Guest',
            'mail' => 'guest@example.test',
        ])->payload();

        self::assertSame('approved', $second['data']['status']);
    }

    public function testGuestsCannotPostUnderARegisteredName(): void
    {
        $payload = $this->dispatch('comments', 'POST', [
            'cid' => 10,
            'text' => 'I am the admin',
            'author' => 'admin',
            'mail' => 'guest@example.test',
        ])->payload();

        self::assertSame(403, $payload['code']);
        self::assertSame('This user name is registered, please log in', $payload['message']);
    }

    public function testLiveMarkerForThePostingInterval(): void
    {
        $this->overrideSiteOptions(['commentsPostIntervalEnable' => 1, 'commentsPostInterval' => 600]);
        $this->seedRows('table.comments', [[
            'coid' => 90,
            'cid' => 10,
            'author' => 'Guest',
            'mail' => 'guest@example.test',
            'url' => '',
            'ip' => '203.0.113.9',
            'agent' => 'tests',
            'text' => 'Just posted',
            'type' => 'comment',
            'status' => 'approved',
            'parent' => 0,
            'created' => time(),
            'authorId' => 0,
            'ownerId' => 1,
        ]]);

        $payload = $this->dispatch('comments', 'POST', [
            'cid' => 10,
            'text' => 'Too soon',
            'author' => 'Guest',
            'mail' => 'guest@example.test',
        ])->payload();

        self::assertSame(429, $payload['code']);
        self::assertSame('You are posting too fast, please retry later', $payload['message']);
    }

    public function testRequiredMailAndHomepageAreEnforced(): void
    {
        $this->overrideSiteOptions(['commentsRequireMail' => 1, 'commentsRequireUrl' => 1]);

        $noMail = $this->dispatch('comments', 'POST', [
            'cid' => 10,
            'text' => 'No mail',
            'author' => 'Guest',
        ])->payload();

        self::assertSame(422, $noMail['code']);
        self::assertSame(['mail' => '必须填写电子邮箱地址'], $noMail['errors']);

        $noUrl = $this->dispatch('comments', 'POST', [
            'cid' => 10,
            'text' => 'No homepage',
            'author' => 'Guest',
            'mail' => 'guest@example.test',
        ])->payload();

        self::assertSame(422, $noUrl['code']);
        self::assertSame(['url' => '必须填写个人主页'], $noUrl['errors']);
    }

    public function testCommentsAreClosedForProtectedOrOptedOutContent(): void
    {
        $this->seedRows('table.contents', [[
            'cid' => 13,
            'title' => 'Members only',
            'slug' => 'members-only',
            'type' => 'post',
            'status' => 'publish',
            'created' => 1_700_001_200,
            'modified' => 1_700_001_200,
            'text' => 'Body',
            'authorId' => 1,
            'commentsNum' => 0,
            'password' => 'secret',
            'template' => '',
            'order' => 0,
            'allowComment' => 1,
            'allowPing' => 1,
            'allowFeed' => 1,
            'parent' => 0,
        ], [
            'cid' => 14,
            'title' => 'Comments off',
            'slug' => 'comments-off',
            'type' => 'post',
            'status' => 'publish',
            'created' => 1_700_001_300,
            'modified' => 1_700_001_300,
            'text' => 'Body',
            'authorId' => 1,
            'commentsNum' => 0,
            'password' => '',
            'template' => '',
            'order' => 0,
            'allowComment' => 0,
            'allowPing' => 1,
            'allowFeed' => 1,
            'parent' => 0,
        ]]);

        foreach ([13, 14] as $cid) {
            $payload = $this->dispatch('comments', 'POST', [
                'cid' => $cid,
                'text' => 'Hello',
                'author' => 'Guest',
                'mail' => 'guest@example.test',
            ])->payload();

            self::assertSame(403, $payload['code']);
            self::assertSame('Comments are closed for this content', $payload['message']);
        }
    }

    public function testRepliesMustBelongToTheSameContent(): void
    {
        $this->overrideSiteOptions(['commentsThreaded' => true, 'commentsMaxNestingLevels' => 5]);

        $payload = $this->dispatch('comments', 'POST', [
            'cid' => 10,
            'text' => 'Reply to nobody',
            'author' => 'Guest',
            'mail' => 'guest@example.test',
            'parent' => 999,
        ])->payload();

        self::assertSame(422, $payload['code']);
        self::assertSame(['parent' => '父级评论不存在'], $payload['errors']);
    }

    public function testRepliesAreFlattenedWhenThreadingIsOff(): void
    {
        $this->overrideSiteOptions(['commentsThreaded' => false]);
        $this->seedComment(90, 0);

        $payload = $this->dispatch('comments', 'POST', [
            'cid' => 10,
            'text' => 'Reply',
            'author' => 'Guest',
            'mail' => 'guest@example.test',
            'parent' => 90,
        ])->payload();

        $this->assertSuccess(201, $payload);
        self::assertSame(0, $payload['data']['parent']);
    }

    public function testNestingLimitIsEnforced(): void
    {
        $this->overrideSiteOptions(['commentsThreaded' => true, 'commentsMaxNestingLevels' => 1]);
        $this->seedComment(91, 0);
        $this->seedComment(90, 91);

        $payload = $this->dispatch('comments', 'POST', [
            'cid' => 10,
            'text' => 'Too deep',
            'author' => 'Guest',
            'mail' => 'guest@example.test',
            'parent' => 90,
        ])->payload();

        self::assertSame(422, $payload['code']);
        self::assertSame('Comment nesting limit reached', $payload['message']);
    }

    public function testReplyInsideTheLimitIsAccepted(): void
    {
        $this->overrideSiteOptions(['commentsThreaded' => true, 'commentsMaxNestingLevels' => 5]);
        $this->seedComment(90, 0);

        $payload = $this->dispatch('comments', 'POST', [
            'cid' => 10,
            'text' => 'A reply',
            'author' => 'Guest',
            'mail' => 'guest@example.test',
            'parent' => 90,
        ])->payload();

        $this->assertSuccess(201, $payload);
        self::assertSame(90, $payload['data']['parent']);
    }

    public function testPublicCommentListHidesContactDetails(): void
    {
        $this->seedComment(90, 0);
        $this->seedRows('table.comments', [[
            'coid' => 91,
            'cid' => 10,
            'author' => 'Pending',
            'mail' => 'pending@example.test',
            'url' => '',
            'ip' => '198.51.100.7',
            'agent' => 'tests',
            'text' => 'Waiting',
            'type' => 'comment',
            'status' => 'waiting',
            'parent' => 0,
            'created' => 1_700_003_000,
            'authorId' => 0,
            'ownerId' => 1,
        ]]);

        $payload = $this->dispatch('comments?cid=10')->payload();

        $this->assertSuccess(200, $payload);
        self::assertSame(1, $payload['meta']['total']);
        self::assertSame(90, $payload['data'][0]['coid']);
        self::assertArrayNotHasKey('mail', $payload['data'][0]);
        self::assertArrayNotHasKey('ip', $payload['data'][0]);
        self::assertArrayNotHasKey('agent', $payload['data'][0]);
        self::assertArrayNotHasKey('mail', $payload['data'][0]);
        self::assertArrayNotHasKey('ip', $payload['data'][0]);
    }

    public function testAuthorsCanDeleteTheirOwnComment(): void
    {
        $this->signInWithCookie(3);
        $this->seedRows('table.comments', [[
            'coid' => 92,
            'cid' => 10,
            'author' => '读者',
            'mail' => 'reader@example.test',
            'url' => '',
            'ip' => '203.0.113.9',
            'agent' => 'tests',
            'text' => 'Mine',
            'type' => 'comment',
            'status' => 'approved',
            'parent' => 0,
            'created' => 1_700_004_000,
            'authorId' => 3,
            'ownerId' => 1,
        ]]);

        $payload = $this->dispatch('comments/92', 'DELETE', null, ['Origin' => 'https://example.test'])
            ->payload();

        $this->assertSuccess(200, $payload);
        self::assertSame(1, $payload['data']['deleted']);
        self::assertNull(V2FakeDb::row('table.comments', 92));
    }

    public function testUnrelatedUsersCannotDeleteSomeoneElsesComment(): void
    {
        $this->signInWithCookie(3);
        $this->seedComment(93, 0);

        $payload = $this->dispatch('comments/93', 'DELETE', null, ['Origin' => 'https://example.test'])
            ->payload();

        self::assertSame(403, $payload['code']);
        self::assertSame('Insufficient permissions', $payload['message']);
    }

    public function testEditorsCanModerateAnyComment(): void
    {
        $this->signInWithCookie(2);
        $this->seedComment(93, 0);

        $payload = $this->dispatch('comments/93', 'DELETE', null, ['Origin' => 'https://example.test'])
            ->payload();

        $this->assertSuccess(200, $payload);
        self::assertNull(V2FakeDb::row('table.comments', 93));
    }

    public function testDeletingACommentTakesItsRepliesWithIt(): void
    {
        $this->signInWithCookie(2);
        $this->seedComment(90, 0);
        $this->seedComment(91, 90);

        $payload = $this->dispatch('comments/90', 'DELETE', null, ['Origin' => 'https://example.test'])
            ->payload();

        $this->assertSuccess(200, $payload);
        self::assertSame(2, $payload['data']['deleted']);
        self::assertNull(V2FakeDb::row('table.comments', 90));
        self::assertNull(V2FakeDb::row('table.comments', 91));
        self::assertSame(0, V2FakeDb::row('table.contents', 10)['commentsNum']);
    }

    public function testMyCommentsOnlyListsTheCurrentAccount(): void
    {
        $this->signInWithCookie(3);
        $this->seedRows('table.comments', [[
            'coid' => 90,
            'cid' => 10,
            'author' => '读者',
            'mail' => 'reader@example.test',
            'url' => '',
            'ip' => '203.0.113.9',
            'agent' => 'tests',
            'text' => 'Mine',
            'type' => 'comment',
            'status' => 'approved',
            'parent' => 0,
            'created' => 1_700_005_000,
            'authorId' => 3,
            'ownerId' => 1,
        ], [
            'coid' => 91,
            'cid' => 10,
            'author' => '管理员',
            'mail' => 'admin@example.test',
            'url' => '',
            'ip' => '203.0.113.9',
            'agent' => 'tests',
            'text' => 'Theirs',
            'type' => 'comment',
            'status' => 'approved',
            'parent' => 0,
            'created' => 1_700_005_100,
            'authorId' => 1,
            'ownerId' => 1,
        ]]);

        $payload = $this->dispatch('me/comments')->payload();

        $this->assertSuccess(200, $payload);
        self::assertSame(1, $payload['meta']['total']);
        self::assertSame(90, $payload['data'][0]['coid']);
    }

    public function testAdminCommentModerationEndpoints(): void
    {
        $this->signInWithCookie(1);
        $this->seedComment(90, 0);

        $list = $this->dispatch('admin/comments')->payload();
        $this->assertSuccess(200, $list);
        self::assertSame(1, $list['meta']['total']);
        self::assertArrayHasKey('mail', $list['data'][0]);
        self::assertArrayHasKey('ip', $list['data'][0]);
        self::assertArrayHasKey('agent', $list['data'][0]);

        $spam = $this->dispatch(
            'admin/comments/90',
            'PATCH',
            ['status' => 'spam'],
            ['Origin' => 'https://example.test']
        )->payload();

        $this->assertSuccess(200, $spam);
        self::assertSame('spam', $spam['data']['status']);

        $invalid = $this->dispatch(
            'admin/comments/90',
            'PATCH',
            ['status' => 'archived'],
            ['Origin' => 'https://example.test']
        )->payload();

        self::assertSame(422, $invalid['code']);
    }

    public function testAdminBulkCommentActions(): void
    {
        $this->signInWithCookie(1);
        $this->seedComment(90, 0);
        $this->seedComment(91, 0);

        $approved = $this->dispatch(
            'admin/comments/bulk',
            'POST',
            ['ids' => [90, 91], 'action' => 'approve'],
            ['Origin' => 'https://example.test']
        )->payload();

        $this->assertSuccess(200, $approved);
        self::assertSame('approved', $approved['data']['status']);
        self::assertSame(2, $approved['data']['affected']);
        self::assertSame(2, V2FakeDb::row('table.contents', 10)['commentsNum']);

        $unknown = $this->dispatch(
            'admin/comments/bulk',
            'POST',
            ['ids' => [90], 'action' => 'archive'],
            ['Origin' => 'https://example.test']
        )->payload();

        self::assertSame(422, $unknown['code']);

        $deleted = $this->dispatch(
            'admin/comments/bulk',
            'POST',
            ['ids' => [90, 91], 'action' => 'delete'],
            ['Origin' => 'https://example.test']
        )->payload();

        $this->assertSuccess(200, $deleted);
        self::assertSame(2, $deleted['data']['affected']);
        self::assertSame([], V2FakeDb::rows('table.comments'));
    }

    public function testAdminCommentRoutesRejectAnonymousCallers(): void
    {
        $this->seedComment(90, 0);

        self::assertSame(401, $this->dispatch('admin/comments')->payload()['code']);
        self::assertSame(
            401,
            $this->dispatch('admin/comments/bulk', 'POST', ['ids' => [90], 'action' => 'approve'])
                ->payload()['code']
        );
    }

    private function seedComment(int $coid, int $parent): void
    {
        $this->seedRows('table.comments', [[
            'coid' => $coid,
            'cid' => 10,
            'author' => 'Guest',
            'mail' => 'guest@example.test',
            'url' => '',
            'ip' => '198.51.100.7',
            'agent' => 'tests',
            'text' => 'Seeded comment ' . $coid,
            'type' => 'comment',
            'status' => 'approved',
            'parent' => $parent,
            'created' => 1_700_006_000,
            'authorId' => 0,
            'ownerId' => 1,
        ]]);
    }
}
