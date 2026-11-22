<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use TTDF\Compat\Typecho;

require_once __DIR__ . '/V2ApiTestCase.php';

final class RestV2AccountTest extends V2ApiTestCase
{
    public function testHomepageRejectsExecutableSchemes(): void
    {
        $this->signInWithCookie(3);

        $bad = $this->dispatch(
            'me',
            'PATCH',
            ['url' => 'javascript://example.test/%0Aalert(1)'],
            ['Origin' => 'https://example.test']
        )->payload();

        self::assertSame(422, $bad['code']);
        self::assertSame(['url' => '个人主页地址必须使用 http 或 https'], $bad['errors']);
        self::assertSame('', V2FakeDb::row('table.users', 3)['url']);

        $valid = $this->dispatch(
            'me',
            'PATCH',
            ['url' => 'https://example.test/~reader'],
            ['Origin' => 'https://example.test']
        )->payload();

        $this->assertSuccess(200, $valid);
        self::assertSame('https://example.test/~reader', $valid['data']['url']);
        self::assertSame('https://example.test/~reader', V2FakeDb::row('table.users', 3)['url']);
    }

    public function testPasswordChangeRequiresTheCurrentPasswordAndRotatesTheAuthCode(): void
    {
        $this->signInWithCookie(3);
        $before = V2FakeDb::row('table.users', 3);
        self::assertNotNull($before);

        $wrong = $this->dispatch(
            'auth/password',
            'POST',
            [
                'currentPassword' => 'wrong-password',
                'password' => 'newsecret',
            ],
            ['Origin' => 'https://example.test']
        )->payload();

        self::assertSame(401, $wrong['code']);
        self::assertSame('Invalid credentials', $wrong['message']);

        $updated = $this->dispatch(
            'auth/password',
            'POST',
            [
                'currentPassword' => 'secret',
                'password' => 'newsecret',
            ],
            ['Origin' => 'https://example.test']
        )->payload();

        $this->assertSuccess(200, $updated);
        self::assertTrue($updated['data']['updated']);
        self::assertSame('reader', $updated['data']['user']['name']);

        $after = V2FakeDb::row('table.users', 3);
        self::assertNotNull($after);
        self::assertTrue(Typecho::hashValidate('newsecret', (string) $after['password']));
        self::assertNotSame($before['authCode'], $after['authCode']);
        self::assertSame(
            Typecho::hash((string) $after['authCode']),
            \Typecho\Cookie::$values['__typecho_authCode'] ?? ''
        );
    }
}
