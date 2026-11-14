<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use TTDF\Theme\Options;

final class OptionsTest extends TestCase
{
    public function testJsonRoundTripAndDefaults(): void
    {
        $store = new InMemoryOptionsStore();
        $options = new Options($store, 'Theme');

        $options->set('SubTitle', 'Hello');
        $options->set('RESTAPI_Switch', true);

        self::assertSame('Hello', $options->get('SubTitle'));
        self::assertTrue($options->get('RESTAPI_Switch'));
        self::assertSame('fallback', $options->get('Missing', 'fallback'));
        self::assertSame('"Hello"', $store->get('Theme_SubTitle'));
    }

    public function testLegacyRawStringFallback(): void
    {
        $store = new InMemoryOptionsStore(['Theme_SubTitle' => 'Legacy']);
        $options = new Options($store, 'Theme');

        self::assertSame('Legacy', $options->get('SubTitle'));
    }

    public function testExplicitEmptyStringIsDistinctFromMissingValue(): void
    {
        $store = new InMemoryOptionsStore(['Theme_REST_Token_Env' => '""']);
        $options = new Options($store, 'Theme');

        self::assertSame('', $options->get('REST_Token_Env', null));
        self::assertNull($options->get('Missing', null));
    }

    public function testImportRejectsSpecialKeys(): void
    {
        $store = new InMemoryOptionsStore();
        $options = new Options($store, 'Theme');
        $options->import(['__schema_version' => '9', 'SubTitle' => 'Imported']);

        self::assertSame('Imported', $options->get('SubTitle'));
        self::assertNull($store->get('__schema_version'));
    }

    public function testAllOnlyReturnsCurrentThemeValues(): void
    {
        $store = new InMemoryOptionsStore([
            'Theme_SubTitle' => '"Hello"',
            'Theme_RESTAPI_Switch' => 'true',
            'Other_SubTitle' => '"Other theme"',
            'Other_Secret' => '"do-not-leak"',
            '__schema_version' => '2',
        ]);
        $options = new Options($store, 'Theme');

        self::assertSame([
            'SubTitle' => 'Hello',
            'RESTAPI_Switch' => true,
        ], $options->all());
    }

    public function testSetRequiresResolvedTheme(): void
    {
        $this->expectException(RuntimeException::class);

        (new Options(new InMemoryOptionsStore(), ''))->set('SubTitle', 'Hello');
    }

    public function testThemeNameWithHyphenKeepsPrefix(): void
    {
        $store = new InMemoryOptionsStore();
        $options = new Options($store, 'classic-22');

        $options->set('SubTitle', 'Hello');

        self::assertSame('"Hello"', $store->get('classic-22_SubTitle'));
        self::assertSame(['SubTitle' => 'Hello'], $options->all());
    }

    public function testInstanceRefreshesThemeResolvedAfterBoot(): void
    {
        $helper = new \stdClass();
        $helper->theme = '';
        \Helper::$optionsOverride = $helper;
        Options::reset();

        try {
            $early = Options::instance();
            self::assertSame('', $early->themeName());

            $helper->theme = 'Theme';
            $late = Options::instance();
            self::assertSame('Theme', $late->themeName());
            self::assertNotSame($early, $late);
        } finally {
            \Helper::$optionsOverride = null;
            Options::reset();
        }
    }
}
