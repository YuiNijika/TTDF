<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use TTDF\Theme\TypechoNativeOptionsStore;
use Typecho\Db;

final class TypechoNativeOptionsStoreTest extends TestCase
{
    protected function setUp(): void
    {
        Db::reset();
    }

    protected function tearDown(): void
    {
        Db::reset();
    }

    public function testRowNamesArePrefixedAndScopedToTypechoOptions(): void
    {
        $store = new TypechoNativeOptionsStore();

        self::assertSame('ttdf:classic-22', $store->key('classic-22'));
        self::assertSame(32, $store->maxNameLength());
        self::assertSame('options', $store->table());
    }

    public function testReadsOnlySiteLevelRows(): void
    {
        Db::$fetcher = static fn (): array => [['value' => '{"SubTitle":"Hello"}']];
        $store = new TypechoNativeOptionsStore();

        self::assertSame('{"SubTitle":"Hello"}', $store->get('ttdf:classic-22'));
        self::assertTrue($store->exists('ttdf:classic-22'));

        $query = Db::$executed[0];
        self::assertSame('table.options', $query->table);
        self::assertSame('value', $query->fields[0]);
        self::assertSame(
            [
                ['condition' => 'name = ?', 'values' => ['ttdf:classic-22']],
                ['condition' => 'user = ?', 'values' => [0]],
            ],
            $query->wheres
        );
        self::assertSame(1, $query->limit);
    }

    public function testAllReturnsSiteLevelOptionsKeyedByName(): void
    {
        Db::$fetcher = static fn (): array => [
            ['name' => 'ttdf:classic-22', 'value' => '{"a":1}'],
            ['name' => 'ttdf:classic-22:b_1700000000', 'value' => '{"a":0}'],
        ];
        $store = new TypechoNativeOptionsStore();

        self::assertSame(
            [
                'ttdf:classic-22' => '{"a":1}',
                'ttdf:classic-22:b_1700000000' => '{"a":0}',
            ],
            $store->all()
        );
        self::assertSame(
            [['condition' => 'user = ?', 'values' => [0]]],
            Db::$executed[0]->wheres
        );
    }

    public function testMissingRowsReadAsNull(): void
    {
        Db::$fetcher = static fn (): array => [];
        $store = new TypechoNativeOptionsStore();

        self::assertNull($store->get('ttdf:classic-22'));
        self::assertFalse($store->exists('ttdf:classic-22'));
    }

    public function testOverlongNamesReadAsEmptyAndFailOnlyOnWrite(): void
    {
        $store = new TypechoNativeOptionsStore();
        $name = str_repeat('a', 33);

        self::assertNull($store->get($name));
        self::assertFalse($store->exists($name));

        $this->expectException(RuntimeException::class);
        $store->set($name, '{"a":1}');
    }

    public function testInstallIsANoOpBecauseTypechoOptionsAlwaysExists(): void
    {
        $store = new TypechoNativeOptionsStore();

        $store->install();

        self::assertSame([], Db::$executed);
    }
}
