<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use JsonException;
use PHPUnit\Framework\TestCase;
use TTDF\Support\Json;

final class JsonTest extends TestCase
{
    public function testEncodeAndDecodeRoundTrip(): void
    {
        $value = ['name' => '中文', 'nested' => ['flag' => true]];

        self::assertSame($value, Json::decode(Json::encode($value)));
    }

    public function testDecodeReturnsNullForInvalidJson(): void
    {
        self::assertNull(Json::decode('{invalid'));
    }

    public function testEncodeScriptEscapesHtmlSensitiveCharacters(): void
    {
        $encoded = Json::encodeScript(['html' => '<script>"x" & \'y\'</script>']);

        self::assertStringNotContainsString('<', $encoded);
        self::assertStringNotContainsString('>', $encoded);
        self::assertStringNotContainsString('&', $encoded);
        self::assertStringContainsString('\u003C', $encoded);
        self::assertStringContainsString('\u0026', $encoded);
    }

    public function testEncodeRejectsInvalidUtf8(): void
    {
        $this->expectException(JsonException::class);

        Json::encode(["bad" => "\xB1\x31"]);
    }
}
