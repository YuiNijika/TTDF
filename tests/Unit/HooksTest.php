<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TTDF\Hooks\Hooks;

final class HooksTest extends TestCase
{
    protected function setUp(): void
    {
        Hooks::reset();
    }

    public function testActionsRunInPriorityOrder(): void
    {
        $calls = [];
        Hooks::addAction('demo', static function () use (&$calls): void {
            $calls[] = 'late';
        }, 20);
        Hooks::addAction('demo', static function () use (&$calls): void {
            $calls[] = 'early';
        }, 5);

        Hooks::doAction('demo');

        self::assertSame(['early', 'late'], $calls);
    }

    public function testFiltersChainValues(): void
    {
        Hooks::addFilter('title', static fn (string $value): string => $value . '!');
        Hooks::addFilter('title', static fn (string $value): string => strtoupper($value));

        self::assertSame('HELLO!', Hooks::applyFilters('title', 'hello'));
    }

    public function testRemoveAction(): void
    {
        $callback = static function (): void {
        };
        Hooks::addAction('demo', $callback);
        Hooks::removeAction('demo', $callback);

        self::assertFalse(Hooks::hasAction('demo'));
    }

    public function testLegacyLoadFootAliasRunsModernCallbacks(): void
    {
        $calls = [];
        Hooks::addAction('theme.after_main', static function () use (&$calls): void {
            $calls[] = 'modern';
        });

        Hooks::doAction('load_foot');

        self::assertSame(['modern'], $calls);
    }

    public function testModernHookRunsLegacyCallbacks(): void
    {
        $calls = [];
        Hooks::addAction('load_foot', static function () use (&$calls): void {
            $calls[] = 'legacy';
        });

        Hooks::doAction('theme.after_main');

        self::assertSame(['legacy'], $calls);
    }

    public function testLegacyArchiveAliasSharesCallbacks(): void
    {
        $calls = [];
        Hooks::addAction('header', static function () use (&$calls): void {
            $calls[] = 'legacy';
        });

        Hooks::doAction('typecho.archive.header');

        self::assertSame(['legacy'], $calls);
    }
}
