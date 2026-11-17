<?php

declare(strict_types=1);

namespace TTDF\Http\Rest;

use Throwable;
use Typecho\Db;

/**
 * Link unattached media records to content saved through the v2 API.
 *
 * Typecho's editor sends attachment IDs separately, while REST clients only
 * send HTML and custom fields. Matching the stored upload path keeps the
 * native media library accurate without trusting an arbitrary cid.
 */
final class AttachmentLinker
{
    private readonly Db $db;

    public function __construct(?Db $db = null)
    {
        $this->db = $db ?? Db::get();
    }

    public function link(int $cid, int $authorId, string $text): int
    {
        if ($cid <= 0 || $authorId <= 0) {
            return 0;
        }

        $haystack = $this->haystack($cid, $text);
        if ($haystack === '') {
            return 0;
        }

        try {
            $attachments = $this->db->fetchAll(
                $this->db->select('cid', 'text')
                    ->from('table.contents')
                    ->where('type = ?', 'attachment')
                    ->where('status = ?', 'publish')
                    ->where('parent = ?', 0)
                    ->where('authorId = ?', $authorId)
            );
        } catch (Throwable) {
            return 0;
        }

        $linked = 0;
        foreach ($attachments as $attachment) {
            if (!is_array($attachment)) {
                continue;
            }

            $attachmentId = (int) ($attachment['cid'] ?? 0);
            if ($attachmentId <= 0) {
                continue;
            }

            $metadata = Attachment::metadata((string) ($attachment['text'] ?? ''));
            if (!$metadata['isImage'] || $metadata['path'] === '') {
                continue;
            }

            if (!self::references($haystack, $metadata['path'])) {
                continue;
            }

            try {
                $linked += (int) $this->db->query(
                    $this->db->update('table.contents')
                        ->rows(['parent' => $cid])
                        ->where('cid = ?', $attachmentId)
                        ->where('type = ?', 'attachment')
                        ->where('parent = ?', 0)
                );
            } catch (Throwable) {
                // A failed association must not roll back the content write.
            }
        }

        return $linked;
    }

    private function haystack(int $cid, string $text): string
    {
        $parts = [$text];

        try {
            $fields = $this->db->fetchAll(
                $this->db->select('type', 'str_value', 'int_value', 'float_value')
                    ->from('table.fields')
                    ->where('cid = ?', $cid)
            );

            foreach ($fields as $field) {
                if (!is_array($field)) {
                    continue;
                }

                $parts[] = match ((string) ($field['type'] ?? 'str')) {
                    'int' => (string) (int) ($field['int_value'] ?? 0),
                    'float' => (string) (float) ($field['float_value'] ?? 0.0),
                    default => (string) ($field['str_value'] ?? ''),
                };
            }
        } catch (Throwable) {
            // The body text is still enough for editor-inserted images.
        }

        return trim(implode("\n", array_filter(
            $parts,
            static fn (string $part): bool => trim($part) !== ''
        )));
    }

    private static function references(string $haystack, string $path): bool
    {
        $haystack = str_replace('\\/', '/', $haystack);
        $path = ltrim(str_replace('\\', '/', trim($path)), '/');
        if ($path === '') {
            return false;
        }

        $url = Attachment::url('/' . $path);
        $urlPath = $url !== '' ? (string) parse_url($url, PHP_URL_PATH) : '';
        $candidates = array_values(array_unique(array_filter(
            [$path, '/' . $path, $url, ltrim($urlPath, '/')],
            static fn (string $candidate): bool => $candidate !== ''
        )));

        foreach ($candidates as $candidate) {
            if (str_contains($haystack, $candidate)) {
                return true;
            }
        }

        return false;
    }
}
