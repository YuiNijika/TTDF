<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TTDF\Support\Escaper;

final class EscaperTest extends TestCase
{
    public function testHtmlEscapesPayload(): void
    {
        self::assertSame('&lt;script&gt;alert(1)&lt;/script&gt;', Escaper::html('<script>alert(1)</script>'));
    }

    public function testUrlRejectsJavascriptScheme(): void
    {
        self::assertSame('', Escaper::url('javascript:alert(1)'));
        self::assertSame('https://example.test/a?b=1&amp;c=2', Escaper::url('https://example.test/a?b=1&c=2'));
    }

    public function testJsEscapesScriptTerminators(): void
    {
        $encoded = Escaper::js(['theme' => '</script><script>alert(1)</script>']);

        self::assertStringNotContainsString('</script>', $encoded);
        self::assertStringContainsString('\\u003C', $encoded);
    }
}
