<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use TTDF\Compat\Typecho;

require_once __DIR__ . '/V2ApiTestCase.php';

final class RestV2RegisterTest extends V2ApiTestCase
{
    public function testRegistrationIsDisabledUnlessTypechoAllowsIt(): void
    {
        $this->overrideSiteOptions(['allowRegister' => false]);

        $payload = $this->dispatch('auth/register', 'POST', [
            'name' => 'newbie',
            'mail' => 'newbie@example.test',
            'password' => 'secret1',
        ])->payload();

        self::assertSame(403, $payload['code']);
        self::assertSame('Registration is disabled', $payload['message']);
    }

    public function testRegistrationCreatesASubscriberAndStartsASession(): void
    {
        $this->overrideSiteOptions(['allowRegister' => true]);

        $payload = $this->dispatch('auth/register', 'POST', [
            'name' => 'newbie',
            'mail' => 'newbie@example.test',
            'password' => 'secret1',
        ])->payload();

        $this->assertSuccess(201, $payload);
        self::assertSame('subscriber', $payload['data']['user']['group']);
        self::assertSame('newbie', $payload['data']['user']['name']);
        self::assertStringContainsString('gravatar.com/avatar/', $payload['data']['user']['avatar']);
        self::assertNotEmpty($payload['data']['token']);
        self::assertArrayNotHasKey('password', $payload['data']['user']);
        self::assertArrayNotHasKey('authCode', $payload['data']['user']);

        $row = V2FakeDb::row('table.users', 4);
        self::assertNotNull($row);
        self::assertSame('newbie', $row['name']);
        self::assertSame('newbie@example.test', $row['mail']);
        self::assertSame('subscriber', $row['group']);
        self::assertNotSame('secret1', $row['password']);
        self::assertTrue(Typecho::hashValidate('secret1', (string) $row['password']));

        self::assertSame('4', \Typecho\Cookie::$values['__typecho_uid'] ?? '');
        self::assertNotSame('', \Typecho\Cookie::$values['__typecho_authCode'] ?? '');
    }

    public function testRegistrationRejectsDuplicateAccounts(): void
    {
        $this->overrideSiteOptions(['allowRegister' => true]);

        $name = $this->dispatch('auth/register', 'POST', [
            'name' => 'admin',
            'mail' => 'fresh@example.test',
            'password' => 'secret1',
        ])->payload();
        self::assertSame(422, $name['code']);
        self::assertSame(['name' => '已被占用'], $name['errors']);

        $mail = $this->dispatch('auth/register', 'POST', [
            'name' => 'fresh',
            'mail' => 'admin@example.test',
            'password' => 'secret1',
        ])->payload();
        self::assertSame(422, $mail['code']);
        self::assertSame(['mail' => '已被占用'], $mail['errors']);
    }

    public function testRegistrationValidatesTheInput(): void
    {
        $this->overrideSiteOptions(['allowRegister' => true]);

        $shortName = $this->dispatch('auth/register', 'POST', [
            'name' => 'a',
            'mail' => 'a@example.test',
            'password' => 'secret1',
        ])->payload();
        self::assertSame(422, $shortName['code']);

        $badMail = $this->dispatch('auth/register', 'POST', [
            'name' => 'fresh',
            'mail' => 'not-an-email',
            'password' => 'secret1',
        ])->payload();
        self::assertSame(422, $badMail['code']);

        $shortPassword = $this->dispatch('auth/register', 'POST', [
            'name' => 'fresh',
            'mail' => 'fresh@example.test',
            'password' => '12345',
        ])->payload();
        self::assertSame(422, $shortPassword['code']);

        $invalidPassword = $this->dispatch('auth/register', 'POST', [
            'name' => 'fresh',
            'mail' => 'fresh@example.test',
            'password' => ['secret1'],
        ])->payload();
        self::assertSame(422, $invalidPassword['code']);
        self::assertSame(['password' => '密码必须是字符串'], $invalidPassword['errors']);

        $missing = $this->dispatch('auth/register', 'POST', [
            'name' => 'fresh',
        ])->payload();
        self::assertSame(422, $missing['code']);
        self::assertArrayHasKey('mail', $missing['errors']);
        self::assertArrayHasKey('password', $missing['errors']);
    }
}
