<?php

declare(strict_types=1);

namespace TTDF\Http\Rest;

use TTDF\Compat\Typecho;
use TTDF\Framework;
use TTDF\Hooks\Hooks;
use TTDF\Http\Request;
use TTDF\Http\Response;
use TTDF\Theme\Fields;
use Typecho\Db;
use Typecho\Db\Query;

final class PublicController
{
    private readonly Db $db;

    public function __construct(?Db $db = null)
    {
        $this->db = $db ?? Db::get();
    }

    public function site(?string $prefix = null): never
    {
        $options = Typecho::options() ?? new \stdClass();
        Response::success([
            'title' => (string) ($options->title ?? ''),
            'description' => (string) ($options->description ?? ''),
            'url' => Typecho::siteUrl(),
            'theme' => Typecho::themeName(),
            'frameworkVersion' => Framework::VERSION,
            'typechoVersion' => Typecho::version(),
            'restPrefix' => $prefix ?? (Prefixes::match(Prefixes::V1) ?? Prefixes::v1()),
        ]);
    }

    public function posts(): never
    {
        [$page, $pageSize] = $this->pagination();
        $search = Request::queryString('search');
        $category = Request::queryString('category');
        $tag = Request::queryString('tag');
        $format = $this->format();

        $total = $this->countContents('post', $search, $category, $tag);
        $rows = $this->rows($this->contentsQuery('post', $search, $category, $tag)
            ->offset(($page - 1) * $pageSize)
            ->limit($pageSize));

        $items = array_map(fn (array $row): array => $this->formatPost($row, $format, false), $rows);
        Response::success($items, $this->paginationMeta($page, $pageSize, $total));
    }

    public function post(int $cid): never
    {
        $row = $this->findContent($cid, 'post');
        if ($row === null) {
            Response::error('Post not found', 404);
        }

        Response::success($this->formatPost($row, $this->format(), true));
    }

    public function pages(): never
    {
        [$page, $pageSize] = $this->pagination();
        $search = Request::queryString('search');
        $format = $this->format();

        $total = $this->countContents('page', $search);
        $rows = $this->rows($this->contentsQuery('page', $search)
            ->offset(($page - 1) * $pageSize)
            ->limit($pageSize));

        $items = array_map(fn (array $row): array => $this->formatPost($row, $format, false), $rows);
        Response::success($items, $this->paginationMeta($page, $pageSize, $total));
    }

    public function page(int $cid): never
    {
        $row = $this->findContent($cid, 'page');
        if ($row === null) {
            Response::error('Page not found', 404);
        }

        Response::success($this->formatPost($row, $this->format(), true));
    }

    public function categories(): never
    {
        Response::success($this->terms('category'));
    }

    public function tags(): never
    {
        Response::success($this->terms('tag'));
    }

    public function comments(): never
    {
        $cid = Request::queryInt('cid', 0);
        if ($cid <= 0) {
            Response::error('Missing cid', 400);
        }

        $content = $this->findContent($cid, null);
        if ($content === null) {
            Response::error('Content not found', 404);
        }

        [$page, $pageSize] = $this->pagination();
        $order = Request::queryString('order', 'asc') === 'desc' ? $this->sortDesc() : $this->sortAsc();
        $total = $this->countComments($cid);

        $select = $this->db->select(
            'coid',
            'cid',
            'author',
            'mail',
            'url',
            'text',
            'status',
            'parent',
            'created',
            'authorId'
        )->from('table.comments')
            ->where('cid = ?', $cid)
            ->where('status = ?', 'approved')
            ->order('created', $order)
            ->offset(($page - 1) * $pageSize)
            ->limit($pageSize);

        $comments = [];
        foreach ($this->rows($select) as $row) {
            $mail = (string) ($row['mail'] ?? '');
            $comments[] = [
                'coid' => (int) ($row['coid'] ?? 0),
                'cid' => (int) ($row['cid'] ?? 0),
                'author' => (string) ($row['author'] ?? ''),
                'avatar' => $mail !== '' ? Typecho::gravatarUrl($mail, 90) : '',
                'url' => $this->safeUrl((string) ($row['url'] ?? '')),
                'text' => Typecho::commentHtml(Typecho::markdown((string) ($row['text'] ?? ''))),
                'parent' => (int) ($row['parent'] ?? 0),
                'created' => $this->date($row['created'] ?? null),
                'authorId' => (int) ($row['authorId'] ?? 0),
            ];
        }

        Response::success($comments, $this->paginationMeta($page, $pageSize, $total));
    }

    public function attachments(?int $authorId = null): never
    {
        [$page, $pageSize] = $this->pagination();
        $cid = Request::queryInt('cid', 0);

        $total = $this->countAttachments($cid, $authorId);
        $rows = $this->rows(
            $this->attachmentsQuery($cid, false, $authorId)
                ->offset(($page - 1) * $pageSize)
                ->limit($pageSize)
        );

        $items = [];
        foreach ($rows as $row) {
            $text = (string) ($row['text'] ?? '');
            $attachment = Attachment::metadata($text);
            $item = [
                'cid' => (int) ($row['cid'] ?? 0),
                'parent' => (int) ($row['parent'] ?? 0),
                'url' => Attachment::url($text),
                'type' => $attachment['type'],
                'mime' => $attachment['mime'],
                'isImage' => $attachment['isImage'],
            ];

            if ($authorId !== null) {
                $item['title'] = (string) ($row['title'] ?? '');
                $item['name'] = $attachment['name'];
                $item['size'] = $attachment['size'];
                $item['created'] = $this->date($row['created'] ?? null);
                $item['modified'] = $this->date($row['modified'] ?? null);
            }

            $items[] = $item;
        }

        Response::success($items, $this->paginationMeta($page, $pageSize, $total));
    }

    public function search(): never
    {
        $query = Request::queryString('q');
        if ($query === '') {
            Response::error('Missing q', 400);
        }

        [$page, $pageSize] = $this->pagination();
        $format = $this->format();
        $category = Request::queryString('category');
        $tag = Request::queryString('tag');
        $total = $this->countContents('post', $query, $category, $tag);

        $rows = $this->rows($this->contentsQuery('post', $query, $category, $tag)
            ->offset(($page - 1) * $pageSize)
            ->limit($pageSize));

        $items = array_map(fn (array $row): array => $this->formatPost($row, $format, false), $rows);
        Response::success($items, $this->paginationMeta($page, $pageSize, $total));
    }

    private function contentsQuery(string $type, string $search = '', string $category = '', string $tag = ''): Query
    {
        $select = $this->db->select(
            'cid',
            'title',
            'slug',
            'created',
            'modified',
            'text',
            'commentsNum',
            'authorId',
            'type'
        )->from('table.contents');

        $this->applyContentFilters($select, $type, $search, $category, $tag);

        return $select->order('created', $this->contentOrder());
    }

    private function countContents(string $type, string $search = '', string $category = '', string $tag = ''): int
    {
        $select = $this->db->select('COUNT(*) AS num')->from('table.contents');
        $this->applyContentFilters($select, $type, $search, $category, $tag);

        $row = $this->object($select);
        return (int) ($row->num ?? 0);
    }

    private function applyContentFilters(
        Query $select,
        string $type,
        string $search,
        string $category,
        string $tag
    ): void {
        $select->where('type = ?', $type)
            ->where('status = ?', 'publish')
            ->where('created <= ?', time())
            ->where('(password IS NULL OR password = ?)', '');

        if ($search !== '') {
            $like = '%' . $search . '%';
            $select->where('(title LIKE ? OR text LIKE ?)', $like, $like);
        }

        if ($category !== '') {
            $this->applyTermFilter($select, 'category', $category);
        }

        if ($tag !== '') {
            $this->applyTermFilter($select, 'tag', $tag);
        }
    }

    private function countComments(int $cid): int
    {
        $select = $this->db->select('COUNT(*) AS num')->from('table.comments')
            ->where('cid = ?', $cid)
            ->where('status = ?', 'approved');

        $row = $this->object($select);
        return (int) ($row->num ?? 0);
    }

    private function countAttachments(int $cid, ?int $authorId = null): int
    {
        $row = $this->object($this->attachmentsQuery($cid, true, $authorId));
        return (int) ($row->num ?? 0);
    }

    private function attachmentsQuery(int $cid, bool $count = false, ?int $authorId = null): Query
    {
        $select = $this->db->select(...($count
            ? ['COUNT(*) AS num']
            : ['cid', 'title', 'text', 'parent', 'created', 'modified']
        ))->from('table.contents')
            ->where('type = ?', 'attachment')
            ->where('status = ?', 'publish')
            ->where('created <= ?', time());

        if ($authorId !== null) {
            // A user may list their own uploads even when the parent content is
            // still a draft; the file already belongs to that account.
            $select->where('authorId = ?', $authorId);
        } else {
            $this->attachmentVisibility($select);
        }

        if ($cid > 0) {
            $select->where('parent = ?', $cid);
        }

        return $select;
    }

    private function attachmentVisibility(Query $select): void
    {
        $parents = [];
        try {
            $rows = $this->db->fetchAll(
                $this->db->select('cid')->from('table.contents')
                    ->where('status = ?', 'publish')
                    ->where('(password IS NULL OR password = ?)', '')
                    ->where('type IN ?', ['post', 'page'])
                    ->where('created <= ?', time())
            );
            foreach ($rows as $row) {
                $parent = (int) ($row['cid'] ?? 0);
                if ($parent > 0) {
                    $parents[] = $parent;
                }
            }
        } catch (\Throwable $exception) {
            error_log('TTDF attachment visibility query failed: ' . $exception->getMessage());
        }

        if ($parents === []) {
            $select->where('cid = ?', 0);
            return;
        }

        $select->where('parent IN ?', $parents);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findContent(int $cid, ?string $type): ?array
    {
        $select = $this->db->select(
            'cid',
            'title',
            'slug',
            'created',
            'modified',
            'text',
            'commentsNum',
            'authorId',
            'type'
        )->from('table.contents')
            ->where('cid = ?', $cid)
            ->where('status = ?', 'publish')
            ->where('created <= ?', time())
            ->where('(password IS NULL OR password = ?)', '')
            ->limit(1);

        if ($type !== null) {
            $select->where('type = ?', $type);
        } else {
            $select->where('type IN ?', ['post', 'page']);
        }

        $rows = $this->rows($select);
        return $rows[0] ?? null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function formatPost(array $row, string $format, bool $withContent): array
    {
        $cid = (int) ($row['cid'] ?? 0);
        $text = (string) ($row['text'] ?? '');

        $result = [
            'cid' => $cid,
            'title' => (string) ($row['title'] ?? ''),
            'slug' => (string) ($row['slug'] ?? ''),
            'permalink' => $this->permalink($row),
            'type' => (string) ($row['type'] ?? 'post'),
            'created' => $this->date($row['created'] ?? null),
            'modified' => $this->date($row['modified'] ?? null),
            'commentsNum' => (int) ($row['commentsNum'] ?? 0),
            'author' => $this->author((int) ($row['authorId'] ?? 0)),
            'categories' => $this->postTerms($cid, 'category'),
            'tags' => $this->postTerms($cid, 'tag'),
            'fields' => $this->postFields($cid, $row),
            'excerpt' => $this->excerpt($text),
        ];

        if ($withContent) {
            $result['content'] = $format === 'markdown' ? $text : Typecho::markdown($text);
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function postTerms(int $cid, string $type): array
    {
        $select = $this->db->select(
            'table.metas.mid',
            'table.metas.name',
            'table.metas.slug',
            'table.metas.description'
        )->from('table.metas')
            ->join('table.relationships', 'table.metas.mid = table.relationships.mid')
            ->where('table.relationships.cid = ?', $cid)
            ->where('table.metas.type = ?', $type)
            ->order('table.metas.order', $this->sortAsc());

        $result = [];
        foreach ($this->rows($select) as $row) {
            $result[] = [
                'mid' => (int) ($row['mid'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'slug' => (string) ($row['slug'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
                'permalink' => $this->termPermalink($type, (string) ($row['slug'] ?? '')),
            ];
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function terms(string $type): array
    {
        $select = $this->db->select(
            'mid',
            'name',
            'slug',
            'description',
            'count',
            'order'
        )->from('table.metas')
            ->where('type = ?', $type)
            ->order('order', $this->sortAsc());

        $result = [];
        foreach ($this->rows($select) as $row) {
            $result[] = [
                'mid' => (int) ($row['mid'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'slug' => (string) ($row['slug'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
                'count' => (int) ($row['count'] ?? 0),
                'order' => (int) ($row['order'] ?? 0),
                'permalink' => $this->termPermalink($type, (string) ($row['slug'] ?? '')),
            ];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, string>
     */
    private function postFields(int $cid, array $row): array
    {
        $declared = Fields::names(Framework::root());
        if ($declared === []) {
            return [];
        }

        $allowed = array_flip($declared);
        $select = $this->db->select('name', 'value')->from('table.fields')->where('cid = ?', $cid);
        $result = [];
        foreach ($this->rows($select) as $row) {
            $name = (string) ($row['name'] ?? '');
            if ($name !== '' && isset($allowed[$name])) {
                $result[$name] = (string) ($row['value'] ?? '');
            }
        }

        $filtered = Hooks::applyFilters('rest.post.fields', $result, $cid, $row);
        if (!is_array($filtered)) {
            return $result;
        }

        $safe = [];
        foreach ($filtered as $name => $value) {
            if (
                is_string($name)
                && preg_match('/^[A-Za-z0-9_]{1,100}$/', $name) === 1
                && is_scalar($value)
            ) {
                $safe[$name] = (string) $value;
            }
        }

        return $safe;
    }

    /**
     * @return array{name: string, screenName: string}|null
     */
    private function author(int $uid): ?array
    {
        if ($uid <= 0) {
            return null;
        }

        $select = $this->db->select('uid', 'name', 'screenName')->from('table.users')
            ->where('uid = ?', $uid)
            ->limit(1);
        $row = $this->rows($select)[0] ?? null;

        return $row === null ? null : [
            'name' => (string) ($row['name'] ?? ''),
            'screenName' => (string) ($row['screenName'] ?? ''),
        ];
    }

    private function excerpt(string $text): string
    {
        $plain = trim(strip_tags(Typecho::markdown($text)));
        $plain = preg_replace('/\s+/u', ' ', $plain) ?? $plain;
        $length = max(0, min(500, Request::queryInt('excerptLength', 200)));
        if ($length === 0) {
            return '';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($plain) > $length
                ? mb_substr($plain, 0, $length) . '...'
                : $plain;
        }

        return strlen($plain) > $length
            ? substr($plain, 0, $length) . '...'
            : $plain;
    }

    private function safeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return $scheme === '' || in_array($scheme, ['http', 'https', 'mailto'], true) ? $url : '';
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function pagination(): array
    {
        $page = max(1, Request::queryInt('page', 1));
        $pageSize = max(1, min(50, Request::queryInt('pageSize', 10)));
        return [$page, $pageSize];
    }

    /**
     * @return array{page: int, pageSize: int, total: int, lastPage: int}
     */
    private function paginationMeta(int $page, int $pageSize, int $total): array
    {
        return [
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
            'lastPage' => $total === 0 ? 0 : (int) ceil($total / $pageSize),
        ];
    }

    private function format(): string
    {
        return Request::queryString('format', 'html') === 'markdown' ? 'markdown' : 'html';
    }

    private function date(mixed $value): string
    {
        return is_numeric($value) ? date('c', (int) $value) : '';
    }

    /**
     * @param array<string, mixed> $row
     */
    private function permalink(array $row): string
    {
        $name = (string) ($row['type'] ?? 'post') === 'page' ? 'page' : 'post';
        return $this->routerUrl($name, [
            'cid' => (int) ($row['cid'] ?? 0),
            'slug' => (string) ($row['slug'] ?? ''),
        ]);
    }

    private function termPermalink(string $type, string $slug): string
    {
        return $this->routerUrl($type === 'tag' ? 'tag' : 'category', ['slug' => $slug]);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function routerUrl(string $name, array $params): string
    {
        try {
            if (!class_exists('Typecho\\Router')) {
                return '';
            }

            $prefix = Typecho::indexUrl();
            if ($prefix === '') {
                $prefix = Typecho::siteUrl();
            }

            $url = \Typecho\Router::url($name, $params, $prefix);
            return $url === '#' ? '' : $url;
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(Query $select): array
    {
        try {
            $rows = $this->db->fetchAll($select);
            return array_values($rows);
        } catch (\Throwable $exception) {
            error_log('TTDF REST query failed: ' . $exception->getMessage());
            return [];
        }
    }

    private function object(Query $select): ?object
    {
        try {
            $row = $this->db->fetchObject($select);
            return is_object($row) ? $row : null;
        } catch (\Throwable $exception) {
            error_log('TTDF REST query failed: ' . $exception->getMessage());
            return null;
        }
    }

    private function applyTermFilter(Query $select, string $type, string $slug): void
    {
        $type = $type === 'tag' ? 'tag' : 'category';
        $ids = [];

        $matches = $this->db->select('table.relationships.cid')
            ->from('table.relationships')
            ->join('table.metas', 'table.metas.mid = table.relationships.mid')
            ->where('table.metas.type = ?', $type)
            ->where('table.metas.slug = ?', $slug);

        foreach ($this->rows($matches) as $row) {
            $cid = (int) ($row['cid'] ?? 0);
            if ($cid > 0) {
                $ids[] = $cid;
            }
        }

        if ($ids === []) {
            $select->where('cid = ?', 0);
            return;
        }

        $select->where('cid IN ?', $ids);
    }

    private function sortDesc(): string
    {
        return defined('Typecho\\Db::SORT_DESC') ? (string) constant('Typecho\\Db::SORT_DESC') : 'DESC';
    }

    private function sortAsc(): string
    {
        return defined('Typecho\\Db::SORT_ASC') ? (string) constant('Typecho\\Db::SORT_ASC') : 'ASC';
    }

    private function contentOrder(): string
    {
        return Request::queryString('order', 'desc') === 'asc' ? $this->sortAsc() : $this->sortDesc();
    }
}
