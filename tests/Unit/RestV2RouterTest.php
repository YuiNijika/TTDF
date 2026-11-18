<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use TTDF\Config\Config;
use TTDF\Hooks\Hooks;
use TTDF\Http\Rest\Registry;
use TTDF\Http\Rest\V2\Router;
use TTDF\Http\Rest\V2\Token;
use TTDF\Theme\Options;

require_once __DIR__ . '/V2ApiTestCase.php';

final class RestV2RouterTest extends V2ApiTestCase
{
    public function testEveryEndpointIsNotFoundWhileTheV2SwitchIsOff(): void
    {
        $this->disableV2();

        self::assertFalse(Router::isRequest());

        $payload = $this->dispatch('site')->payload();

        self::assertSame(404, $payload['code']);
        self::assertSame('Endpoint not found', $payload['message']);
    }

    public function testPublicSiteReportsTheV2Prefix(): void
    {
        $payload = $this->dispatch('site')->payload();

        self::assertSame(200, $payload['code']);
        self::assertSame('ttdf/v2', $payload['data']['restPrefix']);
    }

    public function testAttachmentsRequireAuthentication(): void
    {
        $payload = $this->dispatch('attachments')->payload();

        self::assertSame(401, $payload['code']);
        self::assertSame('Authentication required', $payload['message']);
    }

    public function testAttachmentsOnlyListTheCurrentUsersUploads(): void
    {
        $this->seedRows('table.contents', [
            [
                'cid' => 30,
                'title' => 'mine',
                'text' => '{"name":"mine.png","path":"/usr/uploads/mine.png","size":128,'
                    . '"type":"png","mime":"image/png"}',
                'type' => 'attachment',
                'status' => 'publish',
                'created' => 1_700_000_000,
                'modified' => 1_700_000_000,
                'authorId' => 1,
                'parent' => 0,
            ],
            [
                'cid' => 31,
                'title' => 'theirs',
                'text' => '{"name":"theirs.png","path":"/usr/uploads/theirs.png","size":256,'
                    . '"type":"png","mime":"image/png"}',
                'type' => 'attachment',
                'status' => 'publish',
                'created' => 1_700_000_000,
                'modified' => 1_700_000_000,
                'authorId' => 2,
                'parent' => 0,
            ],
        ]);

        $this->signInWithCookie(1);
        $payload = $this->dispatch('attachments')->payload();

        $this->assertSuccess(200, $payload);
        self::assertSame(1, $payload['meta']['total']);
        self::assertSame(30, $payload['data'][0]['cid']);
        self::assertSame('mine.png', $payload['data'][0]['name']);
        self::assertArrayNotHasKey('path', $payload['data'][0]);
    }

    public function testWritersCanUseTheEditorMediaLibraryForTheirOwnUploads(): void
    {
        $this->seedRows('table.contents', [
            [
                'cid' => 40,
                'title' => 'editor.png',
                'text' => '{"name":"editor.png","path":"/usr/uploads/editor.png","size":128,'
                    . '"type":"png","mime":"image/png"}',
                'type' => 'attachment',
                'status' => 'publish',
                'created' => 1_700_000_000,
                'modified' => 1_700_000_000,
                'authorId' => 2,
                'parent' => 0,
            ],
            [
                'cid' => 41,
                'title' => 'admin.png',
                'text' => '{"name":"admin.png","path":"/usr/uploads/admin.png","size":256,'
                    . '"type":"png","mime":"image/png"}',
                'type' => 'attachment',
                'status' => 'publish',
                'created' => 1_700_000_000,
                'modified' => 1_700_000_000,
                'authorId' => 1,
                'parent' => 0,
            ],
        ]);

        $this->signInWithCookie(2);
        $payload = $this->dispatch('media')->payload();

        $this->assertSuccess(200, $payload);
        self::assertSame(1, $payload['data']['total']);
        self::assertSame(40, $payload['data']['items'][0]['id']);
        self::assertSame(2, $payload['data']['items'][0]['authorId']);
    }

    public function testSubscribersCannotOpenTheEditorMediaLibrary(): void
    {
        $this->signInWithCookie(3);

        $payload = $this->dispatch('media')->payload();

        self::assertSame(403, $payload['code']);
        self::assertSame('Insufficient permissions', $payload['message']);
    }

    public function testDisabledRouterStaysSilentWhenTypechoDrivesTheRequest(): void
    {
        $this->disableV2();

        Router::handleIfRequested($this->root);

        self::assertFalse(Router::isRequest());
    }

    public function testPrefixMatchingStaysBoundaryAware(): void
    {
        self::assertTrue(Router::isRequest());

        $_SERVER['REQUEST_URI'] = '/ttdf/v2';
        self::assertTrue(Router::isRequest());

        $_SERVER['REQUEST_URI'] = '/ttdf/v2/';
        self::assertTrue(Router::isRequest());

        $_SERVER['REQUEST_URI'] = '/ttdf/v20/site';
        self::assertFalse(Router::isRequest());

        $_SERVER['REQUEST_URI'] = '/other/ttdf/v2/site';
        self::assertFalse(Router::isRequest());
    }

    public function testSubdirectoryInstallStillMatchesThePrefix(): void
    {
        $options = \Helper::options();
        $options->siteUrl = 'https://example.test/blog';
        \Helper::$optionsOverride = $options;

        $_SERVER['REQUEST_URI'] = '/blog/ttdf/v2/site';
        self::assertTrue(Router::isRequest());

        $_SERVER['REQUEST_URI'] = '/blog/ttdf/v20/site';
        self::assertFalse(Router::isRequest());
    }

    public function testTraversalSegmentsAreRejected(): void
    {
        $payload = $this->dispatch('posts/%2e%2e/admin/site')->payload();

        self::assertSame(404, $payload['code']);
        self::assertSame('Endpoint not found', $payload['message']);
    }

    public function testKnownPathWithTheWrongMethodAnswers405(): void
    {
        $payload = $this->dispatch('auth/login', 'GET')->payload();

        self::assertSame(405, $payload['code']);
        self::assertSame('Method not allowed', $payload['message']);
    }

    public function testRoutesFollowTheV2Switch(): void
    {
        Router::syncRoutes();
        self::assertTrue($this->hasRoute('/ttdf/v2'));
        self::assertTrue($this->hasRoute('/ttdf/v2/%path alphaslash 0%'));

        $this->disableV2();
        Router::syncRoutes();
        self::assertFalse($this->hasRoute('/ttdf/v2'));
        self::assertFalse($this->hasRoute('/ttdf/v2/%path alphaslash 0%'));
    }

    private function hasRoute(string $url): bool
    {
        foreach (\Typecho\Router::routes() as $route) {
            if (($route['url'] ?? '') === $url) {
                return true;
            }
        }

        return false;
    }

    public function testPublicReadsWorkWithoutTheV1ApiBeingEnabled(): void
    {
        $payload = $this->dispatch('site')->payload();

        $this->assertSuccess(200, $payload);
        self::assertSame('Example', $payload['data']['title']);
    }

    public function testWriteEndpointsRequireAuthentication(): void
    {
        $payload = $this->dispatch('me', 'PATCH', ['screenName' => 'Anon'])->payload();

        self::assertSame(401, $payload['code']);
        self::assertSame('Authentication required', $payload['message']);
    }

    public function testCookieWritesOutsideTheSiteOriginAreRejected(): void
    {
        $this->signInWithCookie(1);

        $payload = $this->dispatch('me', 'PATCH', ['screenName' => 'New name'])->payload();

        self::assertSame(403, $payload['code']);
        self::assertStringContainsString('same-origin', $payload['message']);
    }

    public function testCookieWritesFromTheSiteOriginAreAccepted(): void
    {
        $this->signInWithCookie(1);

        $payload = $this->dispatch(
            'me',
            'PATCH',
            ['screenName' => 'New name'],
            ['Origin' => 'https://example.test']
        )->payload();

        $this->assertSuccess(200, $payload);
        self::assertSame('New name', $payload['data']['screenName']);
    }

    public function testForeignOriginIsAdjusted(): void
    {
        $this->signInWithCookie(1);

        $payload = $this->dispatch(
            'me',
            'PATCH',
            ['screenName' => 'New name'],
            ['Origin' => 'https://evil.test']
        )->payload();

        self::assertSame(403, $payload['code']);
    }

    public function testBearerTokenWritesDoNotNeedAnOriginHeader(): void
    {
        $token = $this->signIn(1);

        $payload = $this->dispatch(
            'me',
            'PATCH',
            ['screenName' => 'Token user'],
            ['Authorization' => 'Bearer ' . $token]
        )->payload();

        $this->assertSuccess(200, $payload);
        self::assertSame('Token user', $payload['data']['screenName']);
        self::assertSame('Token user', V2FakeDb::row('table.users', 1)['screenName']);
    }

    public function testLoginReturnsATokenAndSetsTheCookieSession(): void
    {
        $payload = $this->dispatch('auth/login', 'POST', [
            'name' => 'admin',
            'password' => 'secret',
        ])->payload();

        $this->assertSuccess(200, $payload);
        self::assertSame('Bearer', $payload['data']['tokenType']);
        self::assertNotEmpty($payload['data']['token']);
        self::assertSame(1, $payload['data']['user']['uid']);
        $this->assertTtl(Token::DEFAULT_TTL, $payload['data']['expiresIn']);
        self::assertSame('1', \Typecho\Cookie::$values['__typecho_uid']);
        self::assertArrayNotHasKey('authCode', $payload['data']['user']);
    }

    public function testConfiguredTokenTtlDrivesIssuedTokens(): void
    {
        Config::load([
            'rest' => [
                'enabled' => true,
                'v1_prefix' => 'ttdf/v1',
                'v2_enabled' => true,
                'v2_prefix' => 'ttdf/v2',
                'v2_token_ttl' => 900,
            ],
        ]);
        Options::reset();

        $payload = $this->dispatch('auth/login', 'POST', [
            'name' => 'editor',
            'password' => 'secret',
        ])->payload();

        $this->assertSuccess(200, $payload);
        $this->assertTtl(900, $payload['data']['expiresIn']);

        $session = $this->dispatch(
            'auth/session',
            'GET',
            null,
            ['Authorization' => 'Bearer ' . $payload['data']['token']]
        )->payload();

        self::assertSame(900, $session['data']['tokenTtl']);
    }

    public function testExplicitTtlOutsideTheWindowIsClamped(): void
    {
        $payload = $this->dispatch('auth/login', 'POST', [
            'name' => 'admin',
            'password' => 'secret',
            'ttl' => 999999999,
        ])->payload();

        self::assertSame(422, $payload['code']);
    }

    public function testRememberExtendsTheTokenToTheMaximumWindow(): void
    {
        $payload = $this->dispatch('auth/login', 'POST', [
            'name' => 'admin',
            'password' => 'secret',
            'remember' => true,
        ])->payload();

        $this->assertSuccess(200, $payload);
        $this->assertTtl(Token::MAX_TTL, $payload['data']['expiresIn']);
    }

    public function testLoginFallsBackToTheStoredHashWhenTheWidgetRefuses(): void
    {
        \Widget\User::$credentialsValid = false;

        $payload = $this->dispatch('auth/login', 'POST', [
            'name' => 'editor',
            'password' => 'secret',
        ])->payload();

        $this->assertSuccess(200, $payload);
        self::assertSame(2, $payload['data']['user']['uid']);
    }

    public function testLoginRejectsWrongCredentials(): void
    {
        \Widget\User::$credentialsValid = false;

        $payload = $this->dispatch('auth/login', 'POST', [
            'name' => 'admin',
            'password' => 'wrong',
        ])->payload();

        self::assertSame(401, $payload['code']);
        self::assertSame('Invalid credentials', $payload['message']);
    }

    public function testLoginRequiresBothFields(): void
    {
        $payload = $this->dispatch('auth/login', 'POST', ['name' => 'admin'])->payload();

        self::assertSame(422, $payload['code']);
        self::assertSame(['password' => 'is required'], $payload['errors']);
    }

    public function testSessionEndpointDescribesTheCurrentIdentity(): void
    {
        $this->signInWithCookie(3);

        $payload = $this->dispatch('auth/session')->payload();

        $this->assertSuccess(200, $payload);
        self::assertTrue($payload['data']['authenticated']);
        self::assertSame('cookie', $payload['data']['user']['source']);
        self::assertSame(3, $payload['data']['user']['uid']);
        self::assertArrayNotHasKey('mail', $payload['data']['user']);
    }

    public function testExpiredOrTamperedTokensAreRejected(): void
    {
        $token = $this->signIn(1);
        $tampered = substr($token, 0, -4) . 'zzzz';

        $payload = $this->dispatch('me', 'GET', null, ['Authorization' => 'Bearer ' . $tampered])->payload();

        self::assertSame(401, $payload['code']);
        self::assertSame('Invalid token', $payload['message']);
    }

    public function testLoggingOutEverywhereInvalidatesIssuedTokens(): void
    {
        $token = $this->signIn(1);

        $logout = $this->dispatch(
            'auth/logout',
            'POST',
            ['all' => true],
            ['Authorization' => 'Bearer ' . $token]
        )->payload();

        $this->assertSuccess(200, $logout);
        self::assertTrue($logout['data']['loggedOut']);
        self::assertSame('all-sessions', $logout['data']['revoked']);

        $payload = $this->dispatch('me', 'GET', null, ['Authorization' => 'Bearer ' . $token])->payload();

        self::assertSame(401, $payload['code']);
    }

    public function testPasswordChangeRotatesTheAuthCodeAndIssuesANewToken(): void
    {
        $token = $this->signIn(1);

        $payload = $this->dispatch(
            'auth/password',
            'POST',
            ['currentPassword' => 'secret', 'password' => 'brand-new'],
            ['Authorization' => 'Bearer ' . $token]
        )->payload();

        $this->assertSuccess(200, $payload);
        self::assertTrue($payload['data']['updated']);
        self::assertNotEmpty($payload['data']['token']);
        self::assertNotSame($token, $payload['data']['token']);

        self::assertSame(401, $this->dispatch('me', 'GET', null, ['Authorization' => 'Bearer ' . $token])
            ->payload()['code']);
    }

    public function testPasswordChangeRejectsAWrongCurrentPassword(): void
    {
        $token = $this->signIn(1);

        $payload = $this->dispatch(
            'auth/password',
            'POST',
            ['currentPassword' => 'nope', 'password' => 'brand-new'],
            ['Authorization' => 'Bearer ' . $token]
        )->payload();

        self::assertSame(401, $payload['code']);
    }

    public function testAdminEndpointsRejectNonAdministrators(): void
    {
        $this->signInWithCookie(2);

        $payload = $this->dispatch('admin/options')->payload();

        self::assertSame(403, $payload['code']);
        self::assertSame('Insufficient permissions', $payload['message']);
    }

    public function testAdminEndpointsRejectAnonymousCallers(): void
    {
        $payload = $this->dispatch('admin/options')->payload();

        self::assertSame(401, $payload['code']);
    }

    public function testWriterRoutesRejectSubscribers(): void
    {
        $this->signInWithCookie(3);

        $payload = $this->dispatch(
            'admin/posts',
            'POST',
            ['title' => 'From a subscriber'],
            ['Origin' => 'https://example.test']
        )->payload();

        self::assertSame(403, $payload['code']);
    }

    public function testOptionsPatchOnlyWritesWhitelistedKeys(): void
    {
        $this->signInWithCookie(1);

        $payload = $this->dispatch(
            'admin/options',
            'PATCH',
            ['title' => 'Rewritten', 'siteUrl' => 'https://evil.test'],
            ['Origin' => 'https://example.test']
        )->payload();

        $this->assertSuccess(200, $payload);
        self::assertSame(['title'], $payload['data']['saved']);
        self::assertSame('Rewritten', $payload['data']['options']['title']);
        self::assertSame('Rewritten', $this->optionValue('title'));
        self::assertSame('Example site', $this->optionValue('description'));

        foreach (V2FakeDb::rows('table.options') as $row) {
            self::assertNotSame('siteUrl', $row['name']);
        }
    }

    public function testOptionsPatchRejectsAnEmptyBody(): void
    {
        $this->signInWithCookie(1);

        $payload = $this->dispatch(
            'admin/options',
            'PATCH',
            ['siteUrl' => 'https://evil.test'],
            ['Origin' => 'https://example.test']
        )->payload();

        self::assertSame(422, $payload['code']);
    }

    public function testBuiltInRoutesWinOverExtensionRoutes(): void
    {
        Hooks::addAction('rest.routes', static function (Registry $registry): void {
            $registry->get('users/{uid}', static fn (): array => ['shadowed' => true]);
        });

        $payload = $this->dispatch('users/1')->payload();

        $this->assertSuccess(200, $payload);
        self::assertSame(1, $payload['data']['uid']);
        self::assertSame('admin', $payload['data']['name']);
        self::assertArrayNotHasKey('shadowed', $payload['data']);
    }

    public function testPublicAuthorProfileOmitsPrivateFields(): void
    {
        $payload = $this->dispatch('users/1')->payload();

        $this->assertSuccess(200, $payload);
        self::assertSame(1, $payload['data']['uid']);
        self::assertSame('admin', $payload['data']['name']);
        self::assertArrayNotHasKey('mail', $payload['data']);
        self::assertArrayNotHasKey('authCode', $payload['data']);
        self::assertArrayNotHasKey('logged', $payload['data']);
    }

    public function testExtensionRoutesServePathsTheBuiltInsDoNotCover(): void
    {
        Hooks::addAction('rest.routes', static function (Registry $registry): void {
            $registry->get(
                'users/{uid}/notes',
                static fn ($request): array => ['notes' => [], 'uid' => $request->param('uid')]
            );
        });

        $payload = $this->dispatch('users/7/notes')->payload();

        $this->assertSuccess(200, $payload);
        self::assertSame('7', $payload['data']['uid']);
    }

    public function testExtensionRoutesStillRequireAdminAuthForAdminPaths(): void
    {
        Hooks::addAction('rest.routes', static function (Registry $registry): void {
            $registry->get('admin/overview', static fn (): array => ['ok' => true], Registry::AUTH_ADMIN);
        });

        self::assertSame(401, $this->dispatch('admin/overview')->payload()['code']);

        $this->signInWithCookie(1);
        $payload = $this->dispatch('admin/overview')->payload();

        $this->assertSuccess(200, $payload);
        self::assertTrue($payload['data']['ok']);
    }

    /**
     * Log in over the API and return the issued bearer token.
     */
    private function signIn(int $uid): string
    {
        $name = (string) V2FakeDb::row('table.users', $uid)['name'];

        $payload = $this->dispatch('auth/login', 'POST', [
            'name' => $name,
            'password' => 'secret',
        ])->payload();

        $this->assertSuccess(200, $payload);

        return (string) $payload['data']['token'];
    }

    private function optionValue(string $name): ?string
    {
        foreach (V2FakeDb::rows('table.options') as $row) {
            if ($row['name'] === $name) {
                return (string) $row['value'];
            }
        }

        return null;
    }

    /**
     * `expiresIn` is derived from the wall clock, so allow for a second tick.
     */
    private function assertTtl(int $expected, int $actual): void
    {
        self::assertGreaterThan($expected - 2, $actual);
        self::assertLessThanOrEqual($expected, $actual);
    }
}
