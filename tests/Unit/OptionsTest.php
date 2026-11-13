<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
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
}
