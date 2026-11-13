<?php

declare(strict_types=1);

namespace TTDF\Seo;

use TTDF\Compat\Typecho;
use TTDF\Support\Escaper;

final class Meta
{
    /**
     * @param array{title?: string, description?: string, canonical?: string, image?: string, type?: string} $meta
     */
    public static function render(array $meta): void
    {
        $title = trim((string) ($meta['title'] ?? ''));
        $description = trim((string) ($meta['description'] ?? ''));
        $canonical = self::absoluteUrl((string) ($meta['canonical'] ?? ''));
        $image = self::absoluteUrl((string) ($meta['image'] ?? ''));
        $type = (string) ($meta['type'] ?? 'website');

        if ($title !== '') {
            echo '<title>' . Escaper::html($title) . '</title>' . PHP_EOL;
        }

        if ($description !== '') {
            echo '<meta name="description" content="' . Escaper::attr($description) . '">' . PHP_EOL;
        }

        if ($canonical !== '') {
            echo '<link rel="canonical" href="' . Escaper::attr($canonical) . '">' . PHP_EOL;
        }

        echo '<meta property="og:title" content="' . Escaper::attr($title) . '">' . PHP_EOL;
        if ($description !== '') {
            echo '<meta property="og:description" content="' . Escaper::attr($description) . '">' . PHP_EOL;
        }
        if ($canonical !== '') {
            echo '<meta property="og:url" content="' . Escaper::attr($canonical) . '">' . PHP_EOL;
        }
        if ($image !== '') {
            echo '<meta property="og:image" content="' . Escaper::attr($image) . '">' . PHP_EOL;
        }
        echo '<meta property="og:type" content="' . Escaper::attr($type) . '">' . PHP_EOL;
    }

    private static function absoluteUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $url) === 1) {
            return $url;
        }

        return rtrim(Typecho::siteUrl(), '/') . '/' . ltrim($url, '/');
    }
}
