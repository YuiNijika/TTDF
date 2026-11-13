<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TTDF\Http\Rest\Auth;

final class AuthTest extends TestCase
{
    public function testSameOriginComparisonUsesTheConfiguredSiteUrl(): void
    {
        self::assertTrue(Auth::isSameOrigin(''));
        self::assertTrue(Auth::isSameOrigin('https://example.test'));
        self::assertFalse(Auth::isSameOrigin('https://example.test.evil'));
        self::assertFalse(Auth::isSameOrigin('http://example.test'));
        self::assertFalse(Auth::isSameOrigin('https://evil.test'));
    }
}
