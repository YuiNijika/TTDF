<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use TTDF\Compat\Typecho;

require_once __DIR__ . '/V2ApiTestCase.php';

final class RestV2AccountTest extends V2ApiTestCase
{
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
