<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TTDF\Http\Rest\V2\Token;

final class RestV2TokenTest extends TestCase
{
    protected function setUp(): void
    {
        \Helper::$optionsOverride = null;
    }

    protected function tearDown(): void
    {
        \Helper::$optionsOverride = null;
    }

    public function testIssuedTokenVerifiesAgainstItsAuthCode(): void
    {
        $token = Token::issue(7, 'auth-code-a', Token::DEFAULT_TTL);

        $claims = Token::claims($token['token']);
        self::assertNotNull($claims);
        self::assertSame(7, $claims['uid']);
        self::assertSame($token['expiresAt'], $claims['expiresAt']);
        self::assertTrue(Token::verify($claims, 'auth-code-a'));
        self::assertFalse(Token::verify($claims, 'auth-code-b'));
        self::assertFalse(Token::expired($claims));
    }

    public function testChangingTheSiteUrlInvalidatesOldTokens(): void
    {
        $claims = Token::claims(Token::issue(3, 'auth-code', 600)['token']);
        self::assertNotNull($claims);

        $options = \Helper::options();
        $options->siteUrl = 'https://moved.example.test/';
        \Helper::$optionsOverride = $options;

        self::assertFalse(Token::verify($claims, 'auth-code'));
    }

    public function testMalformedTokensNeverProduceClaims(): void
    {
        self::assertNull(Token::claims(''));
        self::assertNull(Token::claims('ttdfv1.abc.def'));
        self::assertNull(Token::claims('ttdfv2'));
        self::assertNull(Token::claims('ttdfv2.abc'));
        self::assertNull(Token::claims('ttdfv2.abc.def.ghi'));
        self::assertNull(Token::claims('ttdfv2.!!!!.!!!!'));
        self::assertNull(Token::claims('ttdfv2.' . base64_encode('nope') . '.sig'));
        self::assertNull(Token::claims(str_repeat('a', 513)));
    }

    public function testVerifyRefusesEmptyInput(): void
    {
        $claims = Token::claims(Token::issue(1, 'auth-code', 600)['token']);
        self::assertNotNull($claims);

        self::assertFalse(Token::verify($claims, ''));
    }

    public function testTtlIsClampedIntoASaneWindow(): void
    {
        self::assertSame(Token::DEFAULT_TTL, Token::clampTtl(0));
        self::assertSame(Token::DEFAULT_TTL, Token::clampTtl(-10));
        self::assertSame(Token::MIN_TTL, Token::clampTtl(5));
        self::assertSame(Token::MIN_TTL, Token::clampTtl(Token::MIN_TTL));
        self::assertSame(Token::MAX_TTL, Token::clampTtl(PHP_INT_MAX));
    }

    public function testExpiryAlsoCatchesClockSkewInTheFuture(): void
    {
        $now = time();

        self::assertTrue(Token::expired(['uid' => 1, 'expiresAt' => $now - 1, 'issuedAt' => $now - 100]));
        self::assertTrue(Token::expired(['uid' => 1, 'expiresAt' => $now + 600, 'issuedAt' => $now + 3600]));
        self::assertFalse(Token::expired(['uid' => 1, 'expiresAt' => $now + 600, 'issuedAt' => $now]));
    }
}
