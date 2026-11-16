<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TTDF\Config\Config;
use TTDF\Theme\Options;
use TTDF\Theme\Settings;

final class SettingsTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load([]);
    }

    protected function tearDown(): void
    {
        Options::reset();
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

    public function testTemplateMapValidationDropsComponentsOutsideFieldAllowlist(): void
    {
        $result = Settings::validate([
            'Templates' => [
                'post' => 'Post',
                '404' => 'PostMeta',
            ],
        ]);

        self::assertSame([
            'post' => 'Post',
        ], $result['Templates']);

        $result = Settings::validate([
            'Templates' => [
                'post' => 'Post',
                '404' => 'Post',
            ],
        ]);

        self::assertSame([
            'post' => 'Post',
        ], $result['Templates']);
    }

    public function testMediaValidationKeepsUrlsForTextFields(): void
    {
        $result = Settings::validate([
            'Logo' => ' https://cdn.test/logo.png ',
            'SubTitle' => '../not-a-media-value',
        ]);

        self::assertSame('https://cdn.test/logo.png', $result['Logo']);
        self::assertSame('../not-a-media-value', $result['SubTitle']);

        self::assertSame('42', Settings::validate(['Logo' => ' 42 '])['Logo']);
        self::assertSame(
            '/usr/uploads/2026/11/logo.png',
            Settings::validate(['Logo' => '/usr/uploads/2026/11/logo.png'])['Logo']
        );
        self::assertSame('', Settings::validate(['Logo' => 'javascript:alert(1)'])['Logo']);
        self::assertSame('', Settings::validate(['Logo' => '//evil.test/logo.png'])['Logo']);
        self::assertSame('', Settings::validate(['Logo' => ['https://cdn.test/logo.png']])['Logo']);
    }

    public function testMediaFieldModesDistinguishTextFieldsFromPickerFields(): void
    {
        self::assertTrue(Settings::isMediaField(['type' => 'Text', 'name' => 'Logo', 'media' => true]));
        self::assertTrue(Settings::isMediaField([
            'type' => 'Text',
            'name' => 'Logo',
            'media' => ['enabled' => true],
        ]));
        self::assertTrue(Settings::isMediaField(['type' => 'Textarea', 'name' => 'Banners', 'media' => true]));
        self::assertTrue(Settings::isMediaField(['type' => 'Media', 'name' => 'Logo']));
        self::assertSame('url', Settings::mediaMode(['type' => 'Text', 'name' => 'Logo', 'media' => true]));
        self::assertSame('url', Settings::mediaMode(['type' => 'Textarea', 'name' => 'Banners', 'media' => true]));
        self::assertSame('reference', Settings::mediaMode(['type' => 'Media', 'name' => 'Logo']));
        self::assertFalse(Settings::isMediaField(['type' => 'Text', 'name' => 'Logo']));
        self::assertFalse(Settings::isMediaField([
            'type' => 'Text',
            'name' => 'Logo',
            'media' => ['enabled' => false],
        ]));
        self::assertFalse(Settings::isMediaField(['type' => 'Textarea', 'name' => 'FooterText']));
        self::assertNull(Settings::mediaMode(['type' => 'Textarea', 'name' => 'FooterText']));
        self::assertNull(Settings::mediaMode(['type' => 'Select', 'name' => 'Mode', 'media' => true]));
    }

    public function testThemeInfoReportsThemeRootIndexVersion(): void
    {
        $info = Settings::themeInfo();

        self::assertSame('5.0.1-next', $info['themeVersion']);
        self::assertSame('5.0.1-next', $info['ttdfVersion']);
        self::assertSame('1.3.0', $info['typechoVersion']);
    }

    public function testSaveWritesOneJsonDocumentAndCreatesBackups(): void
    {
        $store = new InMemoryOptionsStore();
        $this->useOptions(new Options($store, 'Theme'));

        self::assertSame(1, Settings::save(['SubTitle' => 'First']));
        self::assertSame(1, Settings::save(['SubTitle' => 'Second']));

        self::assertSame(
            ['SubTitle' => 'Second'],
            json_decode((string) $store->get('Theme'), true, 512, JSON_THROW_ON_ERROR)
        );

        $backups = Settings::backups();
        self::assertCount(1, $backups);
        self::assertSame(1, $backups[0]['count']);
        self::assertMatchesRegularExpression('/^Theme:backup_\d{10}$/', $backups[0]['key']);
        self::assertTrue(Settings::restore($backups[0]['key']));
        self::assertSame('First', Options::instance()->get('SubTitle'));
    }

    private function useOptions(Options $options): void
    {
        $property = new \ReflectionProperty(Options::class, 'instance');
        $property->setValue(null, $options);
    }
}
