<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TTDF\Support\Color;

final class ColorTest extends TestCase
{
    public function testNormalizeAcceptsCommonColorNotations(): void
    {
        self::assertSame('#aabbcc', Color::normalize('#ABC'));
        self::assertSame('#aabbcc', Color::normalize('  #AABBCC '));
        self::assertSame('#aabbcc', Color::normalize('#aabbccdd'));
        self::assertSame('#aabbcc', Color::normalize('rgb(170, 187, 204)'));
        self::assertSame('#aabbcc', Color::normalize('rgba(170,187,204,0.4)'));
    }

    public function testNormalizeRejectsValuesThatCouldEscapeTheStyleBlock(): void
    {
        self::assertSame('', Color::normalize('red'));
        self::assertSame('', Color::normalize(''));
        self::assertSame('', Color::normalize('  '));
        self::assertSame('', Color::normalize('#12345'));
        self::assertSame('', Color::normalize('#gggggg'));
        self::assertSame('', Color::normalize('rgb(300, 0, 0)'));
        self::assertSame('', Color::normalize('rgb(1, 2)'));
        self::assertSame('', Color::normalize('rgb(1, 2, 3, 4, 5)'));
        self::assertSame('', Color::normalize('rgb(1px, 2, 3)'));
        self::assertSame('', Color::normalize('#000; } body { background: url(evil) }'));
        self::assertSame('', Color::normalize('#000}'));
        self::assertSame('', Color::normalize(['#000000']));
        self::assertSame('', Color::normalize(null));
    }

    public function testIsDarkFollowsRelativeLuminance(): void
    {
        self::assertTrue(Color::isDark('#000000'));
        self::assertTrue(Color::isDark('#0f1115'));
        self::assertFalse(Color::isDark('#ffffff'));
        self::assertFalse(Color::isDark('#5eead4'));
        self::assertFalse(Color::isDark('not-a-color'));
    }

    public function testContrastPicksReadableTextColor(): void
    {
        self::assertSame(Color::LIGHT_TEXT, Color::contrast('#0f766e'));
        self::assertSame(Color::LIGHT_TEXT, Color::contrast('#000000'));
        self::assertSame(Color::DARK_TEXT, Color::contrast('#fafaf8'));
        self::assertSame(Color::DARK_TEXT, Color::contrast('#5eead4'));
    }

    public function testMixBlendsTowardTargetAndClampsRatio(): void
    {
        self::assertSame('#000000', Color::mix('#000000', '#ffffff', 0.0));
        self::assertSame('#ffffff', Color::mix('#000000', '#ffffff', 1.0));
        self::assertSame('#808080', Color::mix('#000000', '#ffffff', 0.5));
        self::assertSame('#000000', Color::mix('#000000', '#ffffff', -3.0));
        self::assertSame('#ffffff', Color::mix('#000000', '#ffffff', 9.0));
        self::assertSame('', Color::mix('nope', '#ffffff', 0.5));
        self::assertSame('', Color::mix('#000000', 'nope', 0.5));
    }

    public function testAlphaEmitsRgbaWithTrimmedAlpha(): void
    {
        self::assertSame('rgba(15, 118, 110, 0.12)', Color::alpha('#0f766e', 0.12));
        self::assertSame('rgba(15, 118, 110, 1)', Color::alpha('#0f766e', 2.0));
        self::assertSame('rgba(15, 118, 110, 0)', Color::alpha('#0f766e', -1.0));
        self::assertSame('', Color::alpha('nope', 0.5));
    }
}
