<?php

declare(strict_types=1);

namespace TTDF\Http\Rest;

use Throwable;
use Typecho\Db;

/**
 * Resolve and link media records used by content saved through REST v2.
 *
 * Typecho's editor posts attachment IDs separately from the body. The v2 API
 * accepts the same list, and body images written as `![name][cid]` are
 * discovered too. URL matching remains as a compatibility path for older
 * clients that only know the uploaded file path.
 */
final class AttachmentLinker
{
    private readonly Db $db;

    public function __construct(?Db $db = null)
    {
        $this->db = $db ?? Db::get();
    }

    /**
     * @param list<mixed> $attachmentIds
     * @return list<array<string, mixed>>
     */
    public function link(
        int $cid,
        int $authorId,
        string $text,
        array $attachmentIds = [],
        ?int $actorId = null
    ): array {
        if ($cid <= 0 || $authorId <= 0) {
            return [];
        }

        $owners = [$authorId];
        if ($actorId !== null && $actorId > 0 && $actorId !== $authorId) {
            $owners[] = $actorId;
        }

        try {
            $rows = $this->rows($this->db->select(
                'cid',
                'title',
                'text',
                'created',
                'modified',
                'authorId',
                'parent',
                'order'
            )
                ->from('table.contents')
                ->where('type = ?', 'attachment')
                ->where('status = ?', 'publish')
                ->where('authorId IN ?', $owners));
        } catch (Throwable) {
            return [];
        }

        $available = [];
        foreach ($rows as $row) {
            $attachmentId = (int) ($row['cid'] ?? 0);
            $parent = (int) ($row['parent'] ?? 0);
            if ($attachmentId <= 0 || ($parent !== 0 && $parent !== $cid)) {
                continue;
            }

            $metadata = Attachment::metadata((string) ($row['text'] ?? ''));
            $url = Attachment::url((string) ($row['text'] ?? ''));
            if (!$metadata['isImage'] || $url === '') {
                continue;
            }

            $available[$attachmentId] = [
                'id' => $attachmentId,
                'cid' => $attachmentId,
                'title' => (string) ($row['title'] ?? ''),
                'name' => $metadata['name'],
                'url' => $url,
                'path' => $metadata['path'],
                'size' => $metadata['size'],
                'type' => $metadata['type'],
                'mime' => $metadata['mime'],
                'created' => (int) ($row['created'] ?? 0),
                'modified' => (int) ($row['modified'] ?? 0),
                'authorId' => (int) ($row['authorId'] ?? 0),
            ];
        }

        $desired = $this->orderedIds($attachmentIds, $text);
        if ($desired === [] && $available !== []) {
            $desired = $this->urlMatchedIds($available, $this->haystack($cid, $text));
        }

        try {
            $this->db->query(
                $this->db->update('table.contents')
                    ->rows(['parent' => 0, 'status' => 'publish'])
                    ->where('parent = ?', $cid)
                    ->where('type = ?', 'attachment')
            );
        } catch (Throwable) {
            // A failed detach must not stop the content write.
        }

        $linked = [];
        $order = 0;
        foreach ($desired as $attachmentId) {
            $attachment = $available[$attachmentId] ?? null;
            if ($attachment === null) {
                continue;
            }

            $order++;
            try {
                $updated = (int) $this->db->query(
                    $this->db->update('table.contents')
                        ->rows([
                            'parent' => $cid,
                            'status' => 'publish',
                            'order' => $order,
                        ])
                        ->where('cid = ?', $attachmentId)
                        ->where('type = ?', 'attachment')
                        ->where('authorId IN ?', $owners)
                        ->where('parent IN ?', [0, $cid])
                );
            } catch (Throwable) {
                continue;
            }

            if ($updated > 0) {
                $linked[] = $attachment;
            }
        }

        return $linked;
    }

    /**
     * @param list<mixed> $attachmentIds
     * @return list<int>
     */
    private function orderedIds(array $attachmentIds, string $text): array
    {
        $ids = [];
        foreach ($attachmentIds as $value) {
            $attachmentId = is_scalar($value) ? (int) $value : 0;
            if ($attachmentId > 0) {
                $ids[] = $attachmentId;
            }
        }

        if (
            preg_match_all('/!\[[^\]]*\]\[(\d{1,12})\]/u', $text, $matches) > 0
        ) {
            foreach ($matches[1] as $value) {
                $attachmentId = (int) $value;
                if ($attachmentId > 0) {
                    $ids[] = $attachmentId;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param array<int, array<string, mixed>> $available
     * @return list<int>
     */
    private function urlMatchedIds(array $available, string $text): array
    {
        $haystack = str_replace('\\/', '/', $text);
        $ids = [];

        foreach ($available as $attachment) {
            $path = ltrim(str_replace('\\', '/', (string) ($attachment['path'] ?? '')), '/');
            if ($path === '') {
                continue;
            }

            $url = (string) ($attachment['url'] ?? '');
            $urlPath = $url !== '' ? (string) parse_url($url, PHP_URL_PATH) : '';
            $candidates = array_values(array_unique(array_filter(
                [$path, '/' . $path, $url, ltrim($urlPath, '/')],
                static fn (string $candidate): bool => $candidate !== ''
            )));

            foreach ($candidates as $candidate) {
                if (str_contains($haystack, $candidate)) {
                    $ids[] = (int) $attachment['cid'];
                    break;
                }
            }
        }

        return $ids;
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

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(\Typecho\Db\Query $select): array
    {
        return array_values(array_filter(
            $this->db->fetchAll($select),
            static fn (mixed $row): bool => is_array($row)
        ));
    }
}
