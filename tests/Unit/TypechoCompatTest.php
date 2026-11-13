<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TTDF\Compat\Typecho;

final class TypechoCompatTest extends TestCase
{
    public function testSecurityTokenRoundTripsThroughTypechoWidget(): void
    {
        $token = Typecho::securityToken();

        self::assertNotSame('', $token);
        self::assertTrue(Typecho::checkToken($token));
        self::assertFalse(Typecho::checkToken('invalid'));
        self::assertFalse(Typecho::checkToken(''));
    }

    public function testAdminDetectionUsesTypechoUserWidget(): void
    {
        self::assertTrue(Typecho::isAdmin());
        self::assertSame('1.3.0', Typecho::version());
    }

    public function testVersionReadsMagicOptionsPropertyWithoutIsset(): void
    {
        $options = new class {
            public string $siteUrl = 'https://example.test/';
            public string $themeUrl = 'https://example.test/theme';
            public string $theme = 'Theme';

            public function __get(string $name): mixed
            {
                return $name === 'version' ? '1.2.1' : null;
            }
        };

        \Helper::$optionsOverride = $options;
        try {
            self::assertSame('1.2.1', Typecho::version());
            self::assertSame('https://example.test/', Typecho::siteUrl());
            self::assertSame('Theme', Typecho::themeName());
        } finally {
            \Helper::$optionsOverride = null;
        }
    }

    public function testRoutesAreAddedAndRemovedInMemory(): void
    {
        Typecho::resetRoutes();
        \Typecho\Router::reset();

        Typecho::addRoute('TTDF_TEST', '/ttdf-test/%path alphaslash 0%', 'Widget_Archive', 'render');
        self::assertArrayHasKey('TTDF_TEST', \Typecho\Router::routes());

        Typecho::removeRoute('TTDF_TEST');
        self::assertArrayNotHasKey('TTDF_TEST', \Typecho\Router::routes());
    }

    public function testCommentHtmlUsesTypechoWhitelist(): void
    {
        $html = Typecho::commentHtml('<p>ok</p><script>alert(1)</script><a href="#">link</a>');

        self::assertStringContainsString('<p>ok</p>', $html);
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('<a ', $html);
    }
}
