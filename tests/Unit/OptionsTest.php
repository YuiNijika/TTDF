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

    public function testUnprefixedLegacyRowIsNotReadAsCurrentThemeValue(): void
    {
        $store = new InMemoryOptionsStore([
            'SubTitle' => '"global"',
            'Theme_SubTitle' => '"theme"',
        ]);
        $options = new Options($store, 'Theme');

        self::assertSame('theme', $options->get('SubTitle'));
        self::assertSame(
            'fallback',
            (new Options(new InMemoryOptionsStore(['SubTitle' => '"global"']), 'Theme'))
                ->get('SubTitle', 'fallback')
        );
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
        $options->import([
            '__schema_version' => '9',
            '__schema_version_Theme' => '8',
            'SubTitle' => 'Imported',
        ]);

        self::assertSame('Imported', $options->get('SubTitle'));
        self::assertNull($store->get('__schema_version'));
        self::assertNull($store->get('__schema_version_Theme'));
    }

    public function testSchemaVersionIsScopedPerTheme(): void
    {
        $store = new InMemoryOptionsStore();
        $first = new Options($store, 'Theme');
        $second = new Options($store, 'Other');

        $first->ensureInstalled();
        $first->setSchemaVersion(2);

        self::assertSame(2, $first->schemaVersion());
        self::assertNull($second->schemaVersion());

        $second->ensureInstalled();

        self::assertSame(1, $second->schemaVersion());
        self::assertSame(2, $first->schemaVersion());
    }

    public function testFreshThemeInstallStartsAtConfiguredSchemaVersion(): void
    {
        $options = new Options(new InMemoryOptionsStore(), 'Theme');

        $options->ensureInstalled();

        self::assertSame(2, $options->schemaVersion());
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

    public function testUnresolvedThemeNeverReadsOrListsGlobalRows(): void
    {
        $store = new InMemoryOptionsStore([
            'SubTitle' => '"global"',
            'Theme_SubTitle' => '"theme"',
        ]);
        $options = new Options($store, '');

        self::assertSame('fallback', $options->get('SubTitle', 'fallback'));
        self::assertSame([], $options->all());
    }

    public function testDeleteRequiresResolvedTheme(): void
    {
        $this->expectException(RuntimeException::class);

        (new Options(new InMemoryOptionsStore(), ''))->delete('SubTitle');
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
