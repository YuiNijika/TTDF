<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TTDF\Hooks\Hooks;
use TTDF\Hooks\TypechoBridge;

final class TypechoBridgeTest extends TestCase
{
    protected function setUp(): void
    {
        TypechoBridge::reset();
        Hooks::reset();
        \Typecho\Plugin::reset();
    }

    protected function tearDown(): void
    {
        TypechoBridge::reset();
        Hooks::reset();
        \Typecho\Plugin::reset();
    }

    public function testOnlyRealTypechoArchiveHooksAreBridged(): void
    {
        TypechoBridge::register();

        self::assertSame(
            ['header', 'footer', 'beforeRender', 'afterRender'],
            array_keys(\Typecho\Plugin::$hooks['Widget\\Archive'])
        );
        self::assertArrayNotHasKey('Widget\\Contents\\Post', \Typecho\Plugin::$hooks);
        self::assertArrayNotHasKey('Widget\\Contents\\Page', \Typecho\Plugin::$hooks);
    }

    public function testBridgedHookForwardsArguments(): void
    {
        $received = [];
        Hooks::addAction('typecho.archive.before_render', static function (object $archive) use (&$received): void {
            $received[] = $archive;
        });

        TypechoBridge::register();
        $callback = \Typecho\Plugin::$hooks['Widget\\Archive']['beforeRender'];
        $archive = new \stdClass();
        $callback($archive);

        self::assertSame([$archive], $received);
    }
}
