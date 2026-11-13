<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TTDF\Seo\Meta;

final class MetaTest extends TestCase
{
    public function testRenderEscapesValuesAndBuildsAbsoluteUrls(): void
    {
        ob_start();
        Meta::render([
            'title' => 'Hello <script>alert(1)</script>',
            'description' => '"quoted" & more',
            'canonical' => '/archives/1/',
            'image' => 'https://cdn.example.test/cover.jpg',
            'type' => 'article',
        ]);
        $html = (string) ob_get_clean();

        self::assertStringContainsString('<title>Hello &lt;script&gt;alert(1)&lt;/script&gt;</title>', $html);
        self::assertStringContainsString('content="&quot;quoted&quot; &amp; more"', $html);
        self::assertStringContainsString(
            '<link rel="canonical" href="https://example.test/archives/1/">',
            $html
        );
        self::assertStringContainsString(
            '<meta property="og:image" content="https://cdn.example.test/cover.jpg">',
            $html
        );
        self::assertStringContainsString('<meta property="og:type" content="article">', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function testRenderOmitsOptionalTagsWhenValuesAreEmpty(): void
    {
        ob_start();
        Meta::render([]);
        $html = (string) ob_get_clean();

        self::assertStringNotContainsString('<title>', $html);
        self::assertStringNotContainsString('name="description"', $html);
        self::assertStringNotContainsString('rel="canonical"', $html);
        self::assertStringContainsString('<meta property="og:type" content="website">', $html);
    }
}
