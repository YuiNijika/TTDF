<?php

declare(strict_types=1);

namespace TTDF\Http\Rest\V2;

use TTDF\Compat\Typecho;
use TTDF\Framework;
use TTDF\Http\Response;
use TTDF\Http\Rest\Attachment;
use TTDF\Http\Rest\AttachmentLinker;
use TTDF\Theme\Fields;
use Typecho\Db;
use Typecho\Db\Query;

/**
 * Content repository for the v2 API.
 *
 * Rows follow Typecho's own conventions: drafts live in separate
 * `{type}_draft` rows, visibility is the `status` column, and category/tag
 * counts are refreshed the same way `Widget\Metas\EditTrait` does it so the
 * front end and the admin screen stay consistent.
 */
final class Contents
{
    public const MARKDOWN_MARKER = '<!--markdown-->';

    public const TYPES = ['post', 'page'];

    public const STATUSES = ['publish', 'private', 'hidden', 'waiting'];

    private const EDITABLE = [
        'title',
        'slug',
        'text',
        'status',
        'created',
        'modified',
        'password',
        'template',
        'order',
        'authorId',
        'allowComment',
        'allowPing',
        'allowFeed',
        'categories',
        'tags',
        'fields',
        'attachments',
    ];

    private readonly Db $db;

    public function __construct(?Db $db = null)
    {
        $this->db = $db ?? Db::get();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $cid): ?array
    {
        if ($cid <= 0) {
            return null;
        }

        $row = $this->row($this->db->select('*')->from('table.contents')->where('cid = ?', $cid)->limit(1));

        return $row === null ? null : $this->present($row, true);
    }

    /**
     * Listing for the admin screen: every type, every status.
     *
     * @param array<string, mixed> $filters
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function listing(array $filters): array
    {
        $type = (string) ($filters['type'] ?? '');
        $status = (string) ($filters['status'] ?? '');
        $search = (string) ($filters['search'] ?? '');
        $authorId = (int) ($filters['authorId'] ?? 0);
        $page = max(1, (int) ($filters['page'] ?? 1));
        $pageSize = Payload::clamp((int) ($filters['pageSize'] ?? 20), 1, 100, 20);

        $rows = $this->rows($this->listingQuery($type, $status, $search, $authorId, false)
            ->offset(($page - 1) * $pageSize)
            ->limit($pageSize));

        $object = $this->object($this->listingQuery($type, $status, $search, $authorId, true));

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->present($row);
        }

        return ['items' => $items, 'total' => (int) ($object->num ?? 0)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function create(string $type, array $input, int $authorId): array
    {
        $this->assertType($type);
        $input = Payload::only($input, self::EDITABLE);

        $draft = ($input['status'] ?? '') === 'draft';
        $status = $this->normalizeStatus($input['status'] ?? 'publish', $draft);
        $title = $this->title($input['title'] ?? '');
        $now = time();
        $body = $this->body((string) ($input['text'] ?? ''));

        $rows = [
            'title' => $title,
            'slug' => $this->uniqueSlug($this->slug($input['slug'] ?? '', $title), 0),
            'created' => $this->timestamp($input['created'] ?? null, $now),
            'modified' => $now,
            'text' => self::MARKDOWN_MARKER . $body,
            'order' => (int) ($input['order'] ?? 0),
            'authorId' => $this->resolveAuthor($input['authorId'] ?? null, $authorId),
            'template' => (string) ($input['template'] ?? ''),
            'type' => $draft ? $type . '_draft' : $type,
            'status' => $status,
            'password' => $this->password($input['password'] ?? null),
            'commentsNum' => 0,
            'allowComment' => $this->flag($input['allowComment'] ?? true),
            'allowPing' => $this->flag($input['allowPing'] ?? true),
            'allowFeed' => $this->flag($input['allowFeed'] ?? true),
            'parent' => 0,
        ];

        $cid = (int) $this->db->query($this->db->insert('table.contents')->rows($rows));
        if ($cid <= 0) {
            Response::error('Unable to create the content', 500);
        }

        $this->syncFields($cid, $input['fields'] ?? []);

        $attachments = $this->linkAttachments(
            $cid,
            (int) $rows['authorId'],
            $body,
            array_values(is_array($input['attachments'] ?? null) ? $input['attachments'] : []),
            $authorId
        );
        $this->storeBody($cid, $body, $attachments);

        if (!$draft) {
            $this->syncTerms($cid, $input['categories'] ?? [], $input['tags'] ?? []);
        }

        return (array) $this->find($cid);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function update(string $type, int $cid, array $input, ?int $actorId = null): array
    {
        $this->assertType($type);

        $current = $this->row($this->db->select('*')->from('table.contents')->where('cid = ?', $cid)->limit(1));
        if ($current === null || !in_array((string) $current['type'], [$type, $type . '_draft'], true)) {
            Response::error('Content not found', 404);
        }

        $input = Payload::only($input, self::EDITABLE);
        $rows = ['modified' => time()];
        $wasPublished = (string) $current['status'] === 'publish' && (string) $current['type'] === $type;

        if (array_key_exists('title', $input)) {
            $rows['title'] = $this->title($input['title']);
        }

        if (array_key_exists('slug', $input)) {
            $title = (string) ($rows['title'] ?? $current['title']);
            $rows['slug'] = $this->uniqueSlug($this->slug((string) $input['slug'], (string) $title), $cid);
        }

        $body = array_key_exists('text', $input)
            ? $this->body((string) $input['text'])
            : $this->body((string) $current['text']);

        if (array_key_exists('text', $input)) {
            $rows['text'] = self::MARKDOWN_MARKER . $body;
        }

        if (array_key_exists('created', $input)) {
            $rows['created'] = $this->timestamp($input['created'], (int) $current['created']);
        }

        if (array_key_exists('order', $input)) {
            $rows['order'] = (int) $input['order'];
        }

        if (array_key_exists('template', $input)) {
            $rows['template'] = (string) $input['template'];
        }

        if (array_key_exists('authorId', $input)) {
            $rows['authorId'] = $this->resolveAuthor($input['authorId'], (int) $current['authorId']);
        }

        if (array_key_exists('allowComment', $input)) {
            $rows['allowComment'] = $this->flag($input['allowComment']);
        }

        if (array_key_exists('allowPing', $input)) {
            $rows['allowPing'] = $this->flag($input['allowPing']);
        }

        if (array_key_exists('allowFeed', $input)) {
            $rows['allowFeed'] = $this->flag($input['allowFeed']);
        }

        if (array_key_exists('password', $input)) {
            $rows['password'] = $this->password($input['password']);
        }

        if (array_key_exists('status', $input)) {
            $requested = (string) $input['status'];
            if ($requested === 'draft') {
                $rows['type'] = $type . '_draft';
            } else {
                $rows['status'] = $this->normalizeStatus($requested, false);
                if ((string) $current['type'] === $type . '_draft') {
                    // Publishing a draft turns it into a real post again.
                    $rows['type'] = $type;
                    $rows['parent'] = 0;
                }
            }
        }

        $this->db->query($this->db->update('table.contents')->rows($rows)->where('cid = ?', $cid));

        if (array_key_exists('fields', $input)) {
            $this->syncFields($cid, $input['fields']);
        }

        if (array_key_exists('categories', $input) || array_key_exists('tags', $input)) {
            $this->syncTerms(
                $cid,
                array_key_exists('categories', $input) ? (array) $input['categories'] : null,
                array_key_exists('tags', $input) ? (array) $input['tags'] : null
            );
        }

        $isPublished = (string) ($rows['status'] ?? $current['status']) === 'publish'
            && (string) ($rows['type'] ?? $current['type']) === $type;

        if ($isPublished !== $wasPublished) {
            $this->refreshCounts($this->relatedMids($cid, 'category'), 'category');
            $this->refreshCounts($this->relatedMids($cid, 'tag'), 'tag');
        }

        $attachments = $this->linkAttachments(
            $cid,
            (int) ($rows['authorId'] ?? $current['authorId']),
            $body,
            array_values(is_array($input['attachments'] ?? null) ? $input['attachments'] : []),
            $actorId
        );
        $this->storeBody($cid, $body, $attachments);

        return (array) $this->find($cid);
    }

    public function delete(string $type, int $cid): void
    {
        $this->assertType($type);

        $current = $this->row($this->db->select('*')->from('table.contents')->where('cid = ?', $cid)->limit(1));
        if ($current === null || !in_array((string) $current['type'], [$type, $type . '_draft'], true)) {
            Response::error('Content not found', 404);
        }

        $mids = array_merge($this->relatedMids($cid, 'category'), $this->relatedMids($cid, 'tag'));

        $this->db->query(
            $this->db->update('table.contents')
                ->rows(['parent' => 0, 'status' => 'publish'])
                ->where('parent = ?', $cid)
                ->where('type = ?', 'attachment')
        );
        $this->db->query($this->db->delete('table.relationships')->where('cid = ?', $cid));
        $this->db->query($this->db->delete('table.fields')->where('cid = ?', $cid));
        $this->db->query($this->db->delete('table.comments')->where('cid = ?', $cid));
        $this->db->query($this->db->delete('table.contents')->where('parent = ? AND type = ?', $cid, 'revision'));
        $this->db->query($this->db->delete('table.contents')->where('cid = ?', $cid));

        $this->refreshCounts($mids, 'category');
        $this->refreshCounts($mids, 'tag');
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function present(array $row, bool $withAttachments = false): array
    {
        $cid = (int) ($row['cid'] ?? 0);
        $type = (string) ($row['type'] ?? 'post');
        $isDraft = str_ends_with($type, '_draft');
        $status = (string) ($row['status'] ?? 'publish');

        $attachments = $withAttachments ? $this->attachmentsOf($cid) : [];
        $text = $this->stripDefinitions(
            $this->body((string) ($row['text'] ?? '')),
            $withAttachments
                ? array_map(static fn (array $item): int => (int) ($item['id'] ?? 0), $attachments)
                : null
        );

        return [
            'cid' => $cid,
            'title' => (string) ($row['title'] ?? ''),
            'slug' => (string) ($row['slug'] ?? ''),
            'type' => $type,
            'kind' => $isDraft ? substr($type, 0, -6) : $type,
            'status' => $isDraft ? 'draft' : $status,
            'parent' => (int) ($row['parent'] ?? 0),
            'permalink' => $isDraft ? '' : $this->permalink($type, $cid, (string) ($row['slug'] ?? '')),
            'created' => $this->date($row['created'] ?? null),
            'modified' => $this->date($row['modified'] ?? null),
            'authorId' => (int) ($row['authorId'] ?? 0),
            'commentsNum' => (int) ($row['commentsNum'] ?? 0),
            'password' => (string) ($row['password'] ?? '') !== '',
            'template' => (string) ($row['template'] ?? ''),
            'order' => (int) ($row['order'] ?? 0),
            'allowComment' => (int) ($row['allowComment'] ?? 1) === 1,
            'allowPing' => (int) ($row['allowPing'] ?? 1) === 1,
            'allowFeed' => (int) ($row['allowFeed'] ?? 1) === 1,
            'text' => $text,
            'categories' => $this->termsOf($cid, 'category'),
            'tags' => $this->termsOf($cid, 'tag'),
            'fields' => $this->fieldsOf($cid),
            'attachments' => $attachments,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function termsOf(int $cid, string $type): array
    {
        $select = $this->db->select(
            'table.metas.mid',
            'table.metas.name',
            'table.metas.slug'
        )->from('table.metas')
            ->join('table.relationships', 'table.metas.mid = table.relationships.mid')
            ->where('table.relationships.cid = ?', $cid)
            ->where('table.metas.type = ?', $type);

        $terms = [];
        foreach ($this->rows($select) as $row) {
            $terms[] = [
                'mid' => (int) ($row['mid'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'slug' => (string) ($row['slug'] ?? ''),
            ];
        }

        return $terms;
    }

    /**
     * @return array<string, string>
     */
    public function fieldsOf(int $cid): array
    {
        $declared = Fields::names(Framework::root());
        $allowed = $declared === [] ? [] : array_flip($declared);

        $result = [];
        foreach ($this->rows($this->db->select('name', 'type', 'str_value', 'int_value', 'float_value')
            ->from('table.fields')
            ->where('cid = ?', $cid)) as $field) {
            $name = (string) ($field['name'] ?? '');
            if ($name === '' || ($allowed !== [] && !isset($allowed[$name]))) {
                continue;
            }

            $result[$name] = match ((string) ($field['type'] ?? 'str')) {
                'int' => (string) (int) ($field['int_value'] ?? 0),
                'float' => (string) (float) ($field['float_value'] ?? 0.0),
                default => (string) ($field['str_value'] ?? ''),
            };
        }

        return $result;
    }

    /**
     * @param mixed $categories list of mids, `null` keeps the current terms
     * @param mixed $tags list of names, `null` keeps the current terms
     */
    public function syncTerms(int $cid, mixed $categories, mixed $tags): void
    {
        if ($categories === null && $tags === null) {
            return;
        }

        $desired = array_merge(
            $categories === null
                ? $this->relatedMids($cid, 'category')
                : $this->resolveCategories(array_values(is_array($categories) ? $categories : [])),
            $tags === null
                ? $this->relatedMids($cid, 'tag')
                : $this->resolveTags(array_values(is_array($tags) ? $tags : []))
        );

        $existing = [];
        foreach ($this->rows($this->db->select('mid')->from('table.relationships')->where('cid = ?', $cid)) as $row) {
            $mid = (int) ($row['mid'] ?? 0);
            if ($mid > 0) {
                $existing[] = $mid;
            }
        }

        foreach (array_diff($existing, $desired) as $mid) {
            $this->db->query($this->db->delete('table.relationships')->where('cid = ? AND mid = ?', $cid, $mid));
        }

        foreach (array_diff($desired, $existing) as $mid) {
            $this->db->query($this->db->insert('table.relationships')->rows(['cid' => $cid, 'mid' => $mid]));
        }

        $affected = array_values(array_unique(array_merge($existing, $desired)));
        $this->refreshCounts($affected, 'category');
        $this->refreshCounts($affected, 'tag');
    }

    /**
     * @param mixed $fields
     */
    public function syncFields(int $cid, mixed $fields): void
    {
        if (!is_array($fields)) {
            return;
        }

        $declared = Fields::names(Framework::root());
        $allowed = $declared === [] ? [] : array_flip($declared);
        $existing = [];

        foreach ($this->rows($this->db->select('name')->from('table.fields')->where('cid = ?', $cid)) as $row) {
            $name = (string) ($row['name'] ?? '');
            if ($name !== '') {
                $existing[$name] = true;
            }
        }

        foreach ($fields as $name => $value) {
            if (!is_string($name) || preg_match('/^[_a-z][_a-z0-9]*$/i', $name) !== 1) {
                continue;
            }

            if ($allowed !== [] && !isset($allowed[$name])) {
                continue;
            }

            if (!is_scalar($value)) {
                continue;
            }

            $rows = [
                'type' => 'str',
                'str_value' => (string) $value,
                'int_value' => 0,
                'float_value' => 0,
            ];

            if (isset($existing[$name])) {
                $this->db->query($this->db->update('table.fields')->rows($rows)
                    ->where('cid = ? AND name = ?', $cid, $name));
                unset($existing[$name]);
                continue;
            }

            $this->db->query($this->db->insert('table.fields')->rows($rows + ['cid' => $cid, 'name' => $name]));
        }

        foreach (array_keys($existing) as $name) {
            $this->db->query($this->db->delete('table.fields')->where('cid = ? AND name = ?', $cid, $name));
        }
    }

    /**
     * @param list<mixed> $attachmentIds
     * @return list<array<string, mixed>>
     */
    private function linkAttachments(
        int $cid,
        int $authorId,
        string $text,
        array $attachmentIds,
        ?int $actorId = null
    ): array {
        try {
            return (new AttachmentLinker($this->db))->link(
                $cid,
                $authorId,
                $text,
                $attachmentIds,
                $actorId
            );
        } catch (\Throwable $exception) {
            error_log('TTDF attachment linking failed: ' . $exception->getMessage());

            return [];
        }
    }

    /**
     * Persist the canonical Markdown body and the attachment definitions
     * Typecho's renderer needs for `![name][cid]` images.
     *
     * @param list<array<string, mixed>> $attachments
     */
    private function storeBody(int $cid, string $body, array $attachments): void
    {
        $text = $this->withDefinitions($body, $attachments);

        try {
            $this->db->query(
                $this->db->update('table.contents')
                    ->rows(['text' => $text])
                    ->where('cid = ?', $cid)
            );
        } catch (\Throwable $exception) {
            error_log('TTDF markdown storage failed: ' . $exception->getMessage());
        }
    }

    /**
     * @param list<array<string, mixed>> $attachments
     */
    private function withDefinitions(string $body, array $attachments): string
    {
        $body = $this->body($body);
        $definitions = [];
        $ids = [];

        foreach ($attachments as $attachment) {
            $attachmentId = (int) ($attachment['id'] ?? 0);
            $url = trim((string) ($attachment['url'] ?? ''));
            if ($attachmentId <= 0 || $url === '') {
                continue;
            }

            $ids[] = $attachmentId;
            $definitions[] = '[' . $attachmentId . ']: ' . $url;
        }

        $body = $this->stripDefinitions($body, $ids);
        if ($definitions === []) {
            return self::MARKDOWN_MARKER . $body;
        }

        return self::MARKDOWN_MARKER
            . rtrim($body)
            . "\n\n"
            . implode("\n", $definitions);
    }

    /**
     * @param list<int>|null $ids null removes every numeric definition
     */
    private function stripDefinitions(string $text, ?array $ids = null): string
    {
        $allowed = $ids === null ? null : array_fill_keys(array_map('intval', $ids), true);

        $text = rtrim($text);

        while (
            preg_match('/(?:\r?\n){1,2}[ \t]{0,3}\[(\d{1,12})\]:[^\n]*$/u', $text, $match) === 1
        ) {
            $id = (int) $match[1];
            if ($allowed !== null && !isset($allowed[$id])) {
                break;
            }

            $text = rtrim(substr($text, 0, -strlen($match[0])));
        }

        return $text;
    }

    private function body(string $text): string
    {
        return str_starts_with($text, self::MARKDOWN_MARKER)
            ? substr($text, strlen(self::MARKDOWN_MARKER))
            : $text;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function attachmentsOf(int $cid): array
    {
        if ($cid <= 0) {
            return [];
        }

        $rows = $this->rows(
            $this->db->select('cid', 'title', 'text', 'created', 'modified', 'authorId', 'order')
                ->from('table.contents')
                ->where('parent = ?', $cid)
                ->where('type = ?', 'attachment')
                ->where('status = ?', 'publish')
                ->order('order', $this->sortAsc())
                ->order('cid', $this->sortAsc())
        );

        $items = [];
        foreach ($rows as $row) {
            $attachmentId = (int) ($row['cid'] ?? 0);
            $metadata = Attachment::metadata((string) ($row['text'] ?? ''));
            $url = Attachment::url((string) ($row['text'] ?? ''));
            if ($attachmentId <= 0 || !$metadata['isImage'] || $url === '') {
                continue;
            }

            $items[] = [
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

        return $items;
    }

    private function sortAsc(): string
    {
        return defined('Typecho\\Db::SORT_ASC') ? (string) constant('Typecho\\Db::SORT_ASC') : 'ASC';
    }

    /**
     * @param list<int> $mids
     */
    private function refreshCounts(array $mids, string $type): void
    {
        foreach (array_unique($mids) as $mid) {
            $mid = (int) $mid;
            if ($mid <= 0) {
                continue;
            }

            $object = $this->object(
                $this->db->select('COUNT(table.contents.cid) AS num')
                    ->from('table.contents')
                    ->join('table.relationships', 'table.contents.cid = table.relationships.cid')
                    ->where('table.relationships.mid = ?', $mid)
                    ->where('table.contents.type = ?', 'post')
                    ->where('table.contents.status = ?', 'publish')
            );

            $this->db->query($this->db->update('table.metas')->rows(['count' => (int) ($object->num ?? 0)])
                ->where('mid = ? AND type = ?', $mid, $type));
        }
    }

    /**
     * @return list<int>
     */
    private function relatedMids(int $cid, string $type): array
    {
        $select = $this->db->select('table.metas.mid')->from('table.metas')
            ->join('table.relationships', 'table.metas.mid = table.relationships.mid')
            ->where('table.relationships.cid = ?', $cid)
            ->where('table.metas.type = ?', $type);

        $mids = [];
        foreach ($this->rows($select) as $row) {
            $mid = (int) ($row['mid'] ?? 0);
            if ($mid > 0) {
                $mids[] = $mid;
            }
        }

        return $mids;
    }

    /**
     * @param list<mixed> $categories
     * @return list<int>
     */
    private function resolveCategories(array $categories): array
    {
        $mids = [];
        foreach ($categories as $value) {
            $mid = is_scalar($value) ? (int) $value : 0;
            if ($mid <= 0) {
                continue;
            }

            $row = $this->row($this->db->select('mid')->from('table.metas')
                ->where('mid = ?', $mid)
                ->where('type = ?', 'category')
                ->limit(1));
            if ($row !== null) {
                $mids[] = $mid;
            }
        }

        return array_values(array_unique($mids));
    }

    /**
     * @param list<mixed> $tags
     * @return list<int>
     */
    private function resolveTags(array $tags): array
    {
        $mids = [];
        foreach ($tags as $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $name = trim((string) $value);
            if ($name === '') {
                continue;
            }

            if (mb_strlen($name) > 150) {
                Response::error('Invalid tag name', 422, ['tags' => '标签名最多 150 个字符']);
            }

            $slug = Typecho::slugName($name, Typecho::random(6));
            $row = $this->row($this->db->select('mid')->from('table.metas')
                ->where('type = ?', 'tag')
                ->where('(name = ? OR slug = ?)', $name, $slug)
                ->limit(1));

            if ($row !== null) {
                $mids[] = (int) $row['mid'];
                continue;
            }

            $mid = (int) $this->db->query($this->db->insert('table.metas')->rows([
                'name' => $name,
                'slug' => $slug,
                'type' => 'tag',
                'description' => '',
                'count' => 0,
                'order' => 0,
                'parent' => 0,
            ]));

            if ($mid > 0) {
                $mids[] = $mid;
            }
        }

        return array_values(array_unique($mids));
    }

    private function listingQuery(
        string $type,
        string $status,
        string $search,
        int $authorId,
        bool $count
    ): Query {
        $select = $count
            ? $this->db->select('COUNT(*) AS num')
            : $this->db->select('*');

        $select->from('table.contents');

        if ($type !== '' && in_array($type, self::TYPES, true)) {
            $select->where('type IN ?', [$type, $type . '_draft']);
        } else {
            $select->where('type IN ?', ['post', 'page', 'post_draft', 'page_draft']);
        }

        if ($status === 'draft') {
            $select->where('type IN ?', ['post_draft', 'page_draft']);
        } elseif ($status !== '' && in_array($status, self::STATUSES, true)) {
            $select->where('status = ?', $status)
                ->where('type IN ?', ['post', 'page']);
        }

        if ($search !== '') {
            $like = '%' . $search . '%';
            $select->where('(title LIKE ? OR text LIKE ? OR slug LIKE ?)', $like, $like, $like);
        }

        if ($authorId > 0) {
            $select->where('authorId = ?', $authorId);
        }

        return $count ? $select : $select->order('created', $this->sortDesc());
    }

    private function assertType(string $type): void
    {
        if (!in_array($type, self::TYPES, true)) {
            Response::error('Unsupported content type', 422, ['type' => '只支持 post 与 page']);
        }
    }

    private function normalizeStatus(mixed $status, bool $draft): string
    {
        $status = is_scalar($status) ? strtolower(trim((string) $status)) : 'publish';
        if ($status === '' || $draft) {
            return 'publish';
        }

        if (!in_array($status, self::STATUSES, true)) {
            Response::error('Invalid content status', 422, ['status' => '只允许 publish、private、hidden、waiting 与 draft']);
        }

        return $status;
    }

    private function title(mixed $value): string
    {
        $title = is_scalar($value) ? trim((string) $value) : '';
        if ($title === '') {
            Response::error('Invalid content title', 422, ['title' => '标题不能为空']);
        }

        if (mb_strlen($title) > 150) {
            Response::error('Invalid content title', 422, ['title' => '标题最多 150 个字符']);
        }

        return $title;
    }

    private function slug(mixed $value, string $title): string
    {
        $slug = is_scalar($value) ? trim((string) $value) : '';
        $slug = Typecho::slugName($slug !== '' ? $slug : $title, Typecho::random(6));

        return $slug !== '' ? $slug : Typecho::random(6);
    }

    private function uniqueSlug(string $slug, int $cid): string
    {
        $candidate = $slug;
        $suffix = 1;

        while ($this->slugTaken($candidate, $cid)) {
            $suffix++;
            $candidate = substr($slug, 0, 130) . '-' . $suffix;
            if ($suffix > 50) {
                $candidate = substr($slug, 0, 130) . '-' . Typecho::random(6);
                break;
            }
        }

        return $candidate;
    }

    private function slugTaken(string $slug, int $cid): bool
    {
        $select = $this->db->select('cid')->from('table.contents')->where('slug = ?', $slug)->limit(1);
        if ($cid > 0) {
            $select->where('cid <> ?', $cid);
        }

        return $this->row($select) !== null;
    }

    private function timestamp(mixed $value, int $default): int
    {
        if (is_int($value) || is_float($value)) {
            return (int) $value;
        }

        if (is_string($value) && trim($value) !== '') {
            $value = trim($value);
            if (preg_match('/^\d{9,11}$/', $value) === 1) {
                return (int) $value;
            }

            $parsed = strtotime($value);

            return $parsed === false ? $default : $parsed;
        }

        return $default;
    }

    private function resolveAuthor(mixed $value, int $default): int
    {
        $uid = is_scalar($value) ? (int) $value : 0;
        if ($uid <= 0 || $uid === $default) {
            return $default;
        }

        $row = $this->row($this->db->select('uid')->from('table.users')->where('uid = ?', $uid)->limit(1));

        return $row === null ? $default : $uid;
    }

    private function password(mixed $value): string
    {
        $password = is_scalar($value) ? trim((string) $value) : '';

        return mb_substr($password, 0, 32);
    }

    private function flag(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_scalar($value)) {
            return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true) ? '1' : '0';
        }

        return '1';
    }

    private function permalink(string $type, int $cid, string $slug): string
    {
        try {
            if (!class_exists('Typecho\\Router')) {
                return '';
            }

            $prefix = Typecho::indexUrl();
            $url = \Typecho\Router::url(
                $type === 'page' ? 'page' : 'post',
                ['cid' => $cid, 'slug' => $slug],
                $prefix
            );

            return $url === '#' ? '' : $url;
        } catch (\Throwable) {
            return '';
        }
    }

    private function date(mixed $value): string
    {
        return is_numeric($value) && (int) $value > 0 ? date('c', (int) $value) : '';
    }

    private function sortDesc(): string
    {
        return defined('Typecho\\Db::SORT_DESC') ? (string) constant('Typecho\\Db::SORT_DESC') : 'DESC';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function row(Query $select): ?array
    {
        $rows = $this->rows($select);

        return $rows[0] ?? null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(Query $select): array
    {
        try {
            return array_values($this->db->fetchAll($select));
        } catch (\Throwable $exception) {
            error_log('TTDF REST v2 content query failed: ' . $exception->getMessage());

            return [];
        }
    }

    private function object(Query $select): ?object
    {
        try {
            $row = $this->db->fetchObject($select);

            return is_object($row) ? $row : null;
        } catch (\Throwable $exception) {
            error_log('TTDF REST v2 content query failed: ' . $exception->getMessage());

            return null;
        }
    }
}
