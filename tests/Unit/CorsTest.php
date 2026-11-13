<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TTDF\Config\Config;
use TTDF\Http\Rest\Cors;

final class CorsTest extends TestCase
{
    protected function tearDown(): void
    {
        Config::reset();
    }

    public function testOnlyExactAllowlistedOriginsPass(): void
    {
        Config::load(['rest' => ['cors' => ['allowed_origins' => ['https://a.test']]]]);

        self::assertTrue(Cors::isAllowed('https://a.test'));
        self::assertFalse(Cors::isAllowed('https://a.test:8443'));
        self::assertFalse(Cors::isAllowed('https://a.test.evil'));
        self::assertFalse(Cors::isAllowed('https://b.test'));
    }

    public function testMalformedOriginsAreRejectedEvenWhenAllowlisted(): void
    {
        Config::load(['rest' => ['cors' => ['allowed_origins' => [
            "https://a.test\r\nX-Injected: 1",
            'null',
            'https://a.test/path',
            'https://a.test',
        ]]]]);

        self::assertFalse(Cors::isAllowed("https://a.test\r\nX-Injected: 1"));
        self::assertFalse(Cors::isAllowed('null'));
        self::assertFalse(Cors::isAllowed('https://a.test/path'));
        self::assertTrue(Cors::isAllowed('https://a.test'));
        self::assertFalse(Cors::isAllowed('https://a.test:8443'));
    }
}
