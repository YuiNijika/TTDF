<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TTDF\Hooks\Hooks;
use TTDF\Theme\ArchiveLayout;

final class ArchiveLayoutTest extends TestCase
{
    protected function setUp(): void
    {
        Hooks::reset();
        ArchiveLayout::reset();
    }

    protected function tearDown(): void
    {
        Hooks::reset();
        ArchiveLayout::reset();
    }

    public function testThemeHooksRenderTypechoHeaderAndFooterOnce(): void
    {
        $archive = new class {
            public int $headers = 0;

            public int $footers = 0;

            public function header(): void
            {
                $this->headers++;
            }

            public function footer(): void
            {
                $this->footers++;
            }
        };

        ArchiveLayout::register();
        Hooks::doAction('theme.head', $archive);
        Hooks::doAction('theme.head', $archive);
        Hooks::doAction('theme.footer', $archive);
        Hooks::doAction('theme.footer', $archive);

        self::assertSame(1, $archive->headers);
        self::assertSame(1, $archive->footers);
    }

    public function testLegacyLoadFootRunsTypechoFooter(): void
    {
        $archive = new class {
            public int $footers = 0;

            public function footer(): void
            {
                $this->footers++;
            }
        };

        ArchiveLayout::register();
        Hooks::doAction('load_foot', $archive);

        self::assertSame(1, $archive->footers);
    }
}
