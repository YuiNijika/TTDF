<?php

declare(strict_types=1);

namespace TTDF\Http\Rest;

use TTDF\Compat\Upload;
use TTDF\Support\Json;

final class Attachment
{
    /**
     * Typecho stores attachment metadata as JSON in `contents.text`.
     *
     * @return array{path: string, name: string, size: int, type: string, mime: string, isImage: bool}
     */
    public static function metadata(string $text): array
    {
        $text = trim($text);
        $decoded = $text === '' ? null : Json::decode($text);
        $data = is_array($decoded) ? $decoded : [];
        $looksLikeJson = str_starts_with($text, '{') || str_starts_with($text, '[');

        $path = isset($data['path']) && is_string($data['path'])
            ? trim($data['path'])
            : (is_array($decoded) || $looksLikeJson ? '' : $text);

        $name = isset($data['name']) && is_string($data['name']) ? trim($data['name']) : '';
        if ($name === '' && $path !== '') {
            $name = basename($path);
        }

        $size = isset($data['size']) && is_numeric($data['size']) ? (int) $data['size'] : 0;
        $type = isset($data['type']) && is_string($data['type']) ? strtolower(trim($data['type'])) : '';
        if ($type === '' && $name !== '') {
            $type = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        }

        $mime = isset($data['mime']) && is_string($data['mime']) ? trim($data['mime']) : '';
        $isImage = in_array($type, ['jpg', 'jpeg', 'gif', 'png', 'tiff', 'bmp', 'webp', 'avif'], true)
            || str_starts_with(strtolower($mime), 'image/');

        return [
            'path' => $path,
            'name' => $name,
            'size' => max(0, $size),
            'type' => $type,
            'mime' => $mime,
            'isImage' => $isImage,
        ];
    }

    /**
     * @param class-string $handler
     */
    public static function url(string $text, string $handler = 'Widget\\Upload'): string
    {
        $metadata = self::metadata($text);
        if ($metadata['path'] === '') {
            return '';
        }

        return self::safeUrl(Upload::url($metadata['path'], $handler));
    }

    public static function safeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return $scheme === '' || in_array($scheme, ['http', 'https'], true) ? $url : '';
    }
}
