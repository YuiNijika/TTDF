<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use TTDF\Hooks\Hooks;
use TTDF\Http\Response;
use TTDF\Http\Rest\V2\VerificationCodes;

require_once __DIR__ . '/V2ApiTestCase.php';

final class RestV2VerificationTest extends V2ApiTestCase
{
    public function testEmailCodeRequiresADeliveryHook(): void
    {
        $this->overrideSiteOptions(['allowRegister' => true]);

        $payload = $this->dispatch('auth/email-code', 'POST', [
            'mail' => 'fresh@example.test',
            'purpose' => 'register',
        ])->payload();

        self::assertSame(503, $payload['code']);
        self::assertSame('Email delivery is unavailable', $payload['message']);
    }

    public function testEmailCodeDeliveryHookReceivesTheCodeAndEnforcesTheCooldown(): void
    {
        $this->overrideSiteOptions(['allowRegister' => true]);
        $captured = '';

        Hooks::addAction(
            'rest.v2.email_code.send',
            static function (string $mail, string $purpose, string $code) use (&$captured): void {
                self::assertSame('fresh@example.test', $mail);
                self::assertSame('register', $purpose);
                $captured = $code;
            }
        );

        $payload = $this->dispatch('auth/email-code', 'POST', [
            'mail' => 'fresh@example.test',
            'purpose' => 'register',
        ])->payload();

        $this->assertSuccess(200, $payload);
        self::assertTrue($payload['data']['sent']);
        self::assertMatchesRegularExpression('/^\d{6}$/', $captured);
        self::assertArrayNotHasKey('code', $payload['data']);

        $again = $this->dispatch('auth/email-code', 'POST', [
            'mail' => 'fresh@example.test',
            'purpose' => 'register',
        ])->payload();

        self::assertSame(429, $again['code']);
        self::assertSame('Please wait before requesting another code', $again['message']);
    }

    public function testResetPasswordConsumesTheVerificationCodeAndRotatesTheAuthCode(): void
    {
        $captured = '';
        Hooks::addAction(
            'rest.v2.email_code.send',
            static function (string $mail, string $purpose, string $code) use (&$captured): void {
                if ($purpose === 'reset') {
                    $captured = $code;
                }
            }
        );

        $issued = $this->dispatch('auth/email-code', 'POST', [
            'mail' => 'reader@example.test',
            'purpose' => 'reset',
        ])->payload();
        $this->assertSuccess(200, $issued);
        self::assertMatchesRegularExpression('/^\d{6}$/', $captured);

        $before = V2FakeDb::row('table.users', 3);
        self::assertNotNull($before);

        $reset = $this->dispatch('auth/reset-password', 'POST', [
            'mail' => 'reader@example.test',
            'code' => $captured,
            'password' => 'newsecret',
        ])->payload();

        $this->assertSuccess(200, $reset);
        self::assertTrue($reset['data']['reset']);

        $after = V2FakeDb::row('table.users', 3);
        self::assertNotNull($after);
        self::assertTrue(\TTDF\Compat\Typecho::hashValidate('newsecret', (string) $after['password']));
        self::assertNotSame($before['authCode'], $after['authCode']);

        $reused = $this->dispatch('auth/reset-password', 'POST', [
            'mail' => 'reader@example.test',
            'code' => $captured,
            'password' => 'anotherpass',
        ])->payload();
        self::assertSame(422, $reused['code']);
    }

    public function testRegistrationHookCanRequireAnEmailCode(): void
    {
        $this->overrideSiteOptions(['allowRegister' => true]);
        $captured = '';

        Hooks::addAction(
            'rest.v2.email_code.send',
            static function (string $mail, string $purpose, string $code) use (&$captured): void {
                if ($purpose === 'register') {
                    $captured = $code;
                }
            }
        );
        Hooks::addFilter(
            'rest.v2.register.validate',
            static function (mixed $allowed, array $input): bool {
                if ($allowed !== true) {
                    return false;
                }

                if (!VerificationCodes::verify((string) $input['mail'], 'register', (string) $input['code'])) {
                    Response::error('Invalid verification code', 422, ['code' => '验证码无效或已过期']);
                }

                return true;
            }
        );

        $issued = $this->dispatch('auth/email-code', 'POST', [
            'mail' => 'newbie@example.test',
            'purpose' => 'register',
        ])->payload();
        $this->assertSuccess(200, $issued);

        $wrong = $this->dispatch('auth/register', 'POST', [
            'name' => 'newbie',
            'mail' => 'newbie@example.test',
            'password' => 'secret1',
            'code' => '000000',
        ])->payload();
        self::assertSame(422, $wrong['code']);

        $created = $this->dispatch('auth/register', 'POST', [
            'name' => 'newbie',
            'mail' => 'newbie@example.test',
            'password' => 'secret1',
            'code' => $captured,
        ])->payload();

        $this->assertSuccess(201, $created);
        self::assertSame('newbie', $created['data']['user']['name']);
    }

    public function testAccountEmailHookCanVerifyTheNewAddress(): void
    {
        $captured = '';
        Hooks::addAction(
            'rest.v2.email_code.send',
            static function (string $mail, string $purpose, string $code) use (&$captured): void {
                if ($purpose === 'email') {
                    $captured = $code;
                }
            }
        );
        Hooks::addFilter(
            'rest.v2.account.email.verify',
            static function (
                mixed $allowed,
                string $oldMail,
                string $newMail,
                string $code
            ): bool {
                if ($allowed !== true) {
                    return false;
                }

                if (!VerificationCodes::verify($newMail, 'email', $code)) {
                    Response::error('Invalid verification code', 422, ['emailCode' => '验证码无效或已过期']);
                }

                return true;
            }
        );

        $this->signInWithCookie(2);
        $issued = $this->dispatch(
            'auth/email-code',
            'POST',
            ['mail' => 'editor-new@example.test', 'purpose' => 'email'],
            ['Origin' => 'https://example.test']
        )->payload();
        $this->assertSuccess(200, $issued);

        $updated = $this->dispatch(
            'me',
            'PATCH',
            ['mail' => 'editor-new@example.test', 'emailCode' => $captured],
            ['Origin' => 'https://example.test']
        )->payload();

        $this->assertSuccess(200, $updated);
        self::assertSame('editor-new@example.test', $updated['data']['mail']);
        self::assertSame('editor-new@example.test', V2FakeDb::row('table.users', 2)['mail']);
    }
}
