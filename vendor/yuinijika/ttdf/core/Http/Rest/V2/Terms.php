<?php

declare(strict_types=1);

namespace TTDF\Http\Rest\V2;

use TTDF\Compat\Typecho;
use TTDF\Http\Response;
use Typecho\Db;
use Typecho\Db\Query;

/**
 * Category and tag administration for the v2 API.
 */
final class Terms
{
    public const TYPES = ['category', 'tag'];

    private readonly Db $db;

    public function __construct(?Db $db = null)
    {
        $this->db = $db ?? Db::get();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(string $type, int $parent = -1): array
    {
        $this->assertType($type);

        $select = $this->db->select('*')->from('table.metas')
            ->where('type = ?', $type)
            ->order('order', $this->sortAsc());

        if ($parent >= 0) {
            $select->where('parent = ?', $parent);
        }

        $terms = [];
        foreach ($this->rows($select) as $row) {
            $terms[] = $this->present($row);
        }

        return $terms;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $type, int $mid): ?array
    {
        $this->assertType($type);
        $row = $this->row($this->db->select('*')->from('table.metas')
            ->where('mid = ?', $mid)
            ->where('type = ?', $type)
            ->limit(1));

        return $row === null ? null : $this->present($row);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function create(string $type, array $input): array
    {
        $this->assertType($type);

        $name = $this->name($input['name'] ?? '');
        $slug = $this->slug($input['slug'] ?? '', $name);
        $this->assertUnique($type, $name, $slug, 0);

        $mid = (int) $this->db->query($this->db->insert('table.metas')->rows([
            'name' => $name,
            'slug' => $slug,
            'type' => $type,
            'description' => $this->description($input['description'] ?? ''),
            'count' => 0,
            'order' => (int) ($input['order'] ?? 0),
            'parent' => $this->parent($type, $input['parent'] ?? 0),
        ]));

        if ($mid <= 0) {
            Response::error('Unable to create the term', 500);
        }

        return (array) $this->find($type, $mid);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function update(string $type, int $mid, array $input): array
    {
        $term = $this->row($this->db->select('*')->from('table.metas')
            ->where('mid = ?', $mid)
            ->where('type = ?', $type)
            ->limit(1));
        if ($term === null) {
            Response::error('Term not found', 404);
        }

        $rows = [];

        if (array_key_exists('name', $input)) {
            $name = $this->name($input['name']);
            $this->assertUnique($type, $name, (string) $term['slug'], $mid);
            $rows['name'] = $name;
        }

        if (array_key_exists('slug', $input)) {
            $slug = $this->slug($input['slug'], (string) ($rows['name'] ?? $term['name']));
            $this->assertUnique($type, (string) ($rows['name'] ?? $term['name']), $slug, $mid);
            $rows['slug'] = $slug;
        }

        if (array_key_exists('description', $input)) {
            $rows['description'] = $this->description($input['description']);
        }

        if (array_key_exists('order', $input)) {
            $rows['order'] = (int) $input['order'];
        }

        if (array_key_exists('parent', $input)) {
            $parent = $this->parent($type, $input['parent']);
            if ($parent === $mid) {
                Response::error('Invalid parent', 422, ['parent' => '不能把分类的父级设为自身']);
            }
            $rows['parent'] = $parent;
        }

        if ($rows !== []) {
            $this->db->query($this->db->update('table.metas')->rows($rows)->where('mid = ?', $mid));
        }

        return (array) $this->find($type, $mid);
    }

    /**
     * @return array{deleted: int, contents: int}
     */
    public function delete(string $type, int $mid): array
    {
        $term = $this->row($this->db->select('*')->from('table.metas')
            ->where('mid = ?', $mid)
            ->where('type = ?', $type)
            ->limit(1));
        if ($term === null) {
            Response::error('Term not found', 404);
        }

        $cidRows = $this->rows($this->db->select('cid')->from('table.relationships')->where('mid = ?', $mid));
        $cids = [];
        foreach ($cidRows as $row) {
            $cid = (int) ($row['cid'] ?? 0);
            if ($cid > 0) {
                $cids[] = $cid;
            }
        }

        $this->db->query($this->db->delete('table.relationships')->where('mid = ?', $mid));
        $this->db->query($this->db->delete('table.metas')->where('mid = ?', $mid));

        if ($type === 'category') {
            // Children move to the root instead of disappearing with the parent.
            $this->db->query($this->db->update('table.metas')->rows(['parent' => 0])
                ->where('parent = ?', $mid));
        }

        foreach (array_unique($cids) as $cid) {
            $this->db->query($this->db->update('table.contents')->rows(['modified' => time()])
                ->where('cid = ?', $cid));
        }

        return ['deleted' => $mid, 'contents' => count(array_unique($cids))];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function present(array $row): array
    {
        $type = (string) ($row['type'] ?? 'category');
        $slug = (string) ($row['slug'] ?? '');

        return [
            'mid' => (int) ($row['mid'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'slug' => $slug,
            'type' => $type,
            'description' => (string) ($row['description'] ?? ''),
            'count' => (int) ($row['count'] ?? 0),
            'order' => (int) ($row['order'] ?? 0),
            'parent' => (int) ($row['parent'] ?? 0),
            'permalink' => $this->permalink($type, $slug),
        ];
    }

    private function assertType(string $type): void
    {
        if (!in_array($type, self::TYPES, true)) {
            Response::error('Unsupported term type', 422, ['type' => '只支持 category 与 tag']);
        }
    }

    private function name(mixed $value): string
    {
        $name = is_scalar($value) ? trim((string) $value) : '';
        if ($name === '') {
            Response::error('Invalid term name', 422, ['name' => '名称不能为空']);
        }

        if (mb_strlen($name) > 150) {
            Response::error('Invalid term name', 422, ['name' => '名称最多 150 个字符']);
        }

        return $name;
    }

    private function slug(mixed $value, string $name): string
    {
        $slug = is_scalar($value) ? trim((string) $value) : '';
        $slug = Typecho::slugName($slug !== '' ? $slug : $name, '');

        return $slug !== '' ? substr($slug, 0, 128) : substr(Typecho::random(8), 0, 8);
    }

    private function description(mixed $value): string
    {
        $description = is_scalar($value) ? trim((string) $value) : '';

        return mb_substr($description, 0, 150);
    }

    private function parent(string $type, mixed $value): int
    {
        if ($type !== 'category') {
            return 0;
        }

        $parent = is_scalar($value) ? (int) $value : 0;
        if ($parent <= 0) {
            return 0;
        }

        $row = $this->row($this->db->select('mid')->from('table.metas')
            ->where('mid = ?', $parent)
            ->where('type = ?', 'category')
            ->limit(1));

        return $row === null ? 0 : $parent;
    }

    private function assertUnique(string $type, string $name, string $slug, int $exceptMid): void
    {
        $select = $this->db->select('mid')->from('table.metas')
            ->where('type = ?', $type)
            ->where('(name = ? OR slug = ?)', $name, $slug)
            ->limit(1);

        if ($exceptMid > 0) {
            $select->where('mid <> ?', $exceptMid);
        }

        if ($this->row($select) !== null) {
            Response::error('Term already exists', 422, ['name' => '名称或别名已被占用']);
        }
    }

    private function permalink(string $type, string $slug): string
    {
        if ($slug === '' || !class_exists('Typecho\\Router')) {
            return '';
        }

        try {
            $url = \Typecho\Router::url($type === 'tag' ? 'tag' : 'category', ['slug' => $slug], Typecho::indexUrl());

            return $url === '#' ? '' : $url;
        } catch (\Throwable) {
            return '';
        }
    }

    private function sortAsc(): string
    {
        return defined('Typecho\\Db::SORT_ASC') ? (string) constant('Typecho\\Db::SORT_ASC') : 'ASC';
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
            error_log('TTDF REST v2 term query failed: ' . $exception->getMessage());

            return [];
        }
    }
}
