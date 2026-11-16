<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TTDF\Theme\StorageMode;

final class StorageModeTest extends TestCase
{
    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function flags(): array
    {
        return [
            'undefined' => [null, true],
            'true' => [true, true],
            'false' => [false, false],
            'int 1' => [1, true],
            'int 0' => [0, false],
            'string 1' => ['1', true],
            'string true' => ['true', true],
            'string on' => ['ON', true],
            'string false' => ['false', false],
            'string off' => [' off ', false],
            'string no' => ['no', false],
            'string 0' => ['0', false],
            'empty string' => ['', false],
            'table name' => ['ttdf', true],
        ];
    }

    #[DataProvider('flags')]
    public function testDedicatedTableFlag(mixed $flag, bool $expected): void
    {
        self::assertSame($expected, StorageMode::dedicatedTableEnabled($flag));
        self::assertSame(
            $expected ? StorageMode::DEDICATED_TABLE : StorageMode::TYPECHO_OPTIONS,
            StorageMode::label($flag)
        );
    }
}
