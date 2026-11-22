<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TTDF\Http\Rest\V2\Url;

final class UrlTest extends TestCase
{
    public function testWebsiteOnlyAcceptsHttpAndHttps(): void
    {
        self::assertSame('', Url::website('javascript:alert(1)'));
        self::assertSame('', Url::website('data:text/html,<script>alert(1)</script>'));
        self::assertSame('', Url::website('//example.test/path'));
        self::assertSame('https://example.test/reader', Url::website('https://example.test/reader'));
    }

    public function testLinkAllowsSafeOutputSchemesAndRelativePaths(): void
    {
        self::assertSame('mailto:reader@example.test', Url::link('mailto:reader@example.test'));
        self::assertSame('/relative/path', Url::link('/relative/path'));
        self::assertSame('#section', Url::link('#section'));
        self::assertSame('', Url::link('javascript:alert(1)'));
        self::assertSame('', Url::link('vbscript:msgbox(1)'));
    }
}
