<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TTDF\Config\Config;
use TTDF\Theme\Settings;

final class SettingsTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load([]);
    }

    public function testValidationWhitelistsSchemaFields(): void
    {
        $result = Settings::validate([
            'SubTitle' => 'Hello',
            'RESTAPI_Switch' => 'true',
            'Unknown' => 'ignored',
        ]);

        self::assertSame('Hello', $result['SubTitle']);
        self::assertTrue($result['RESTAPI_Switch']);
        self::assertArrayNotHasKey('Unknown', $result);
    }

    public function testTextareaValuesRemainStrings(): void
    {
        $result = Settings::validate(['CORS_Origins' => "https://a.test\nhttps://b.test"]);
        self::assertSame("https://a.test\nhttps://b.test", $result['CORS_Origins']);
    }

    public function testNonScalarSwitchValuesBecomeFalse(): void
    {
        $result = Settings::validate(['RESTAPI_Switch' => ['unexpected']]);

        self::assertFalse($result['RESTAPI_Switch']);
    }

    public function testTemplateMapValidationKeepsKnownSlotsAndSafeNames(): void
    {
        $result = Settings::validate([
            'Templates' => [
                'post' => 'Post',
                'page' => ' Page ',
                'tag' => '../Secret',
                'unknown' => 'Post',
                '404' => '',
            ],
        ]);

        self::assertSame([
            'post' => 'Post',
            'page' => 'Page',
            '404' => '',
        ], $result['Templates']);
    }

    public function testThemeInfoReportsThemeRootIndexVersion(): void
    {
        $info = Settings::themeInfo();

        self::assertSame('5.0.0-next', $info['themeVersion']);
        self::assertSame('5.0.0-next', $info['ttdfVersion']);
        self::assertSame('1.3.0', $info['typechoVersion']);
    }
}
