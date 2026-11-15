<?php

declare(strict_types=1);

namespace TTDF\Theme;

use Throwable;
use Typecho\Db;
use TTDF\Http\Rest\Attachment;

/**
 * Resolve a theme media setting to a safe attachment URL.
 *
 * A dedicated `Media` field stores the Typecho attachment cid as a string,
 * while `Text` / `Textarea` media fields store the URL. Absolute http(s) URLs,
 * site-relative paths and legacy cids are all resolved here so themes never
 * have to guess which flavour a saved value uses.
 */
final class Media
{
    /**
     * @return array{
     *     id: int,
     *     url: string,
     *     name: string,
     *     title: string,
     *     path: string,
     *     size: int,
     *     mime: string,
     *     type: string,
     *     isImage: bool
     * }
     */
    public static function metadata(mixed $value, ?Db $db = null): array
    {
        $empty = [
            'id' => 0,
            'url' => '',
            'name' => '',
            'title' => '',
            'path' => '',
            'size' => 0,
            'mime' => '',
            'type' => '',
            'isImage' => false,
        ];

        if (!is_scalar($value)) {
            return $empty;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return $empty;
        }

        if (
            preg_match('#^https?://#i', $value) === 1
            || (str_starts_with($value, '/') && !str_starts_with($value, '//'))
        ) {
            $url = Attachment::safeUrl($value);
            if ($url === '') {
                return $empty;
            }

            return [
                'id' => 0,
                'url' => $url,
                'name' => basename((string) parse_url($url, PHP_URL_PATH)),
                'title' => '',
                'path' => '',
                'size' => 0,
                'mime' => '',
                'type' => strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION)),
                'isImage' => true,
            ];
        }

        if (ctype_digit($value) === false || (int) $value <= 0) {
            return $empty;
        }

        try {
            $db = $db ?? Db::get();
            $row = $db->fetchRow(
                $db->select('cid', 'title', 'text')
                    ->from('table.contents')
                    ->where('table.contents.cid = ?', (int) $value)
                    ->where('table.contents.type = ?', 'attachment')
                    ->limit(1)
            );
        } catch (Throwable) {
            return $empty;
        }

        if (!is_array($row)) {
            return $empty;
        }

        $text = (string) ($row['text'] ?? '');
        $metadata = Attachment::metadata($text);
        $url = Attachment::url($text);
        if ($url === '') {
            return $empty;
        }

        $title = htmlspecialchars_decode((string) ($row['title'] ?? ''), ENT_QUOTES);
        if ($title === '') {
            $title = $metadata['name'];
        }

        return [
            'id' => (int) ($row['cid'] ?? $value),
            'url' => $url,
            'name' => $metadata['name'],
            'title' => $title,
            'path' => $metadata['path'],
            'size' => $metadata['size'],
            'mime' => $metadata['mime'],
            'type' => $metadata['type'],
            'isImage' => $metadata['isImage'],
        ];
    }

    public static function url(mixed $value, ?Db $db = null): string
    {
        $metadata = self::metadata($value, $db);

        return $metadata['url'];
    }

    /**
     * Normalize what a media-enabled Text field stores: an absolute http(s)
     * URL, a site-relative path or a cid saved by an older version. Anything
     * else, including non-http schemes and protocol-relative values, is
     * dropped so a settings save can never persist an unsafe URL.
     */
    public static function normalizeUrl(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (ctype_digit($value)) {
            return $value;
        }

        if (str_starts_with($value, '/') && !str_starts_with($value, '//')) {
            return $value;
        }

        $url = Attachment::safeUrl($value);

        return preg_match('#^https?://#i', $url) === 1 ? $url : '';
    }

    /**
     * A media-enabled Textarea keeps one normalized URL per line.
     */
    public static function normalizeUrlList(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $lines = preg_split('/\r\n|\r|\n/', (string) $value);
        $urls = [];

        foreach (is_array($lines) ? $lines : [] as $line) {
            $url = self::normalizeUrl($line);
            if ($url !== '') {
                $urls[] = $url;
            }
        }

        return implode("\n", $urls);
    }
}
