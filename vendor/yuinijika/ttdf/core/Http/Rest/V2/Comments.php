<?php

declare(strict_types=1);

namespace TTDF\Http\Rest\V2;

use TTDF\Compat\Typecho;
use TTDF\Http\Request;
use TTDF\Http\Response;
use Typecho\Db;
use Typecho\Db\Query;

/**
 * Comment reading and writing for the v2 API.
 *
 * Writing keeps Typecho's moderation model intact: the same option switches
 * decide between `approved` and `waiting`, guests cannot post under a
 * registered user name, and the per-address posting interval still applies.
 */
final class Comments
{
    public const STATUSES = ['approved', 'waiting', 'spam'];

    private const MAX_LENGTH = 20000;

    private readonly Db $db;

    public function __construct(?Db $db = null)
    {
        $this->db = $db ?? Db::get();
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $identity
     * @return array<string, mixed>
     */
    public function create(array $input, ?array $identity): array
    {
        $cid = (int) ($input['cid'] ?? 0);
        if ($cid <= 0) {
            Response::error('Invalid request body', 422, ['cid' => 'is required']);
        }

        $content = $this->content($cid);
        if ($content === null) {
            Response::error('Content not found', 404);
        }

        $this->assertCommentable($content);

        $text = is_scalar($input['text'] ?? null) ? trim((string) $input['text']) : '';
        if ($text === '') {
            Response::error('Invalid request body', 422, ['text' => 'is required']);
        }

        if (mb_strlen($text) > self::MAX_LENGTH) {
            Response::error('Invalid request body', 422, ['text' => '评论内容过长']);
        }

        $comment = [
            'cid' => $cid,
            'created' => $this->now(),
            'agent' => $this->agent(),
            'ip' => $this->ip(),
            'ownerId' => (int) ($content['authorId'] ?? 0),
            'type' => 'comment',
            'text' => $text,
            'parent' => 0,
        ];

        $parent = (int) ($input['parent'] ?? 0);
        if ($parent > 0) {
            $comment['parent'] = $this->resolveParent($parent, $cid);
        }

        if ($identity !== null) {
            $comment['author'] = (string) $identity['screenName'];
            $comment['mail'] = (string) $identity['mail'];
            $comment['url'] = (string) $identity['url'];
            $comment['authorId'] = (int) $identity['uid'];
        } else {
            $author = is_scalar($input['author'] ?? null) ? trim((string) $input['author']) : '';
            $mail = is_scalar($input['mail'] ?? null) ? trim((string) $input['mail']) : '';
            $url = is_scalar($input['url'] ?? null) ? trim((string) $input['url']) : '';

            $comment['author'] = $this->validateAuthor($author);
            $comment['mail'] = $this->validateMail($mail);
            $comment['url'] = $this->validateUrl($url);
        }

        $this->assertInterval();
        $comment['status'] = $this->moderatedStatus($content, $comment, $identity);

        $coid = $this->insert($comment);
        if ($coid <= 0) {
            Response::error('Unable to save the comment', 500);
        }

        return (array) $this->find($coid, false);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $coid, bool $includeSensitive = true): ?array
    {
        if ($coid <= 0) {
            return null;
        }

        $row = $this->row($this->db->select('*')->from('table.comments')->where('coid = ?', $coid)->limit(1));

        return $row === null ? null : $this->present($row, $includeSensitive);
    }

    /**
     * @return list<int>
     */
    public function descendants(int $coid): array
    {
        $ids = [];
        $queue = [$coid];

        while ($queue !== []) {
            $parent = array_shift($queue);
            foreach ($this->rows($this->db->select('coid')->from('table.comments')->where('parent = ?', $parent)) as $row) {
                $child = (int) ($row['coid'] ?? 0);
                if ($child > 0 && !in_array($child, $ids, true)) {
                    $ids[] = $child;
                    $queue[] = $child;
                }
            }
        }

        return $ids;
    }

    public function delete(int $coid): int
    {
        $row = $this->row($this->db->select('coid', 'cid')->from('table.comments')->where('coid = ?', $coid)->limit(1));
        if ($row === null) {
            Response::error('Comment not found', 404);
        }

        $ids = array_merge([$coid], $this->descendants($coid));
        foreach ($ids as $id) {
            $this->db->query($this->db->delete('table.comments')->where('coid = ?', $id));
        }

        $this->recount((int) ($row['cid'] ?? 0));

        return count($ids);
    }

    /**
     * @return array<string, mixed>
     */
    public function setStatus(int $coid, string $status): array
    {
        if (!in_array($status, self::STATUSES, true)) {
            Response::error('Invalid comment status', 422, ['status' => '只允许 approved、waiting 与 spam']);
        }

        $row = $this->row($this->db->select('coid', 'cid')->from('table.comments')->where('coid = ?', $coid)->limit(1));
        if ($row === null) {
            Response::error('Comment not found', 404);
        }

        $this->db->query($this->db->update('table.comments')->rows(['status' => $status])->where('coid = ?', $coid));
        $this->recount((int) ($row['cid'] ?? 0));

        return (array) $this->find($coid);
    }

    /**
     * @param list<int> $ids
     * @return array<string, mixed>
     */
    public function bulk(array $ids, string $action): array
    {
        $action = strtolower(trim($action));
        if ($action === 'delete') {
            $deleted = 0;
            foreach ($ids as $coid) {
                $row = $this->row($this->db->select('coid', 'cid')->from('table.comments')
                    ->where('coid = ?', $coid)->limit(1));
                if ($row === null) {
                    continue;
                }

                foreach (array_merge([$coid], $this->descendants($coid)) as $id) {
                    $this->db->query($this->db->delete('table.comments')->where('coid = ?', $id));
                    $deleted++;
                }

                $this->recount((int) ($row['cid'] ?? 0));
            }

            return ['action' => $action, 'affected' => $deleted];
        }

        $status = match ($action) {
            'approve', 'approved' => 'approved',
            'wait', 'waiting', 'pending' => 'waiting',
            'spam' => 'spam',
            default => '',
        };

        if ($status === '') {
            Response::error('Invalid comment action', 422, ['action' => '只允许 approve、waiting、spam 与 delete']);
        }

        $cids = [];
        $affected = 0;
        foreach ($ids as $coid) {
            $row = $this->row($this->db->select('coid', 'cid')->from('table.comments')
                ->where('coid = ?', $coid)->limit(1));
            if ($row === null) {
                continue;
            }

            $this->db->query($this->db->update('table.comments')->rows(['status' => $status])
                ->where('coid = ?', $coid));
            $cids[] = (int) ($row['cid'] ?? 0);
            $affected++;
        }

        foreach (array_unique($cids) as $cid) {
            $this->recount($cid);
        }

        return ['action' => $action, 'status' => $status, 'affected' => $affected];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function listing(array $filters, bool $includeSensitive = true): array
    {
        $status = (string) ($filters['status'] ?? '');
        $cid = (int) ($filters['cid'] ?? 0);
        $authorId = (int) ($filters['authorId'] ?? 0);
        $search = (string) ($filters['search'] ?? '');
        $page = max(1, (int) ($filters['page'] ?? 1));
        $pageSize = Payload::clamp((int) ($filters['pageSize'] ?? 20), 1, 100, 20);

        $select = $this->filter($status, $cid, $authorId, $search);
        $rows = $this->rows(
            $select->order('created', $this->sortDesc())->offset(($page - 1) * $pageSize)->limit($pageSize)
        );

        $object = $this->object($this->filter($status, $cid, $authorId, $search, true));

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->present($row, $includeSensitive);
        }

        return ['items' => $items, 'total' => (int) ($object->num ?? 0)];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function present(array $row, bool $includeSensitive = true): array
    {
        $text = (string) ($row['text'] ?? '');
        $status = (string) ($row['status'] ?? 'approved');

        $result = [
            'coid' => (int) ($row['coid'] ?? 0),
            'cid' => (int) ($row['cid'] ?? 0),
            'author' => (string) ($row['author'] ?? ''),
            'authorId' => (int) ($row['authorId'] ?? 0),
            'ownerId' => (int) ($row['ownerId'] ?? 0),
            'mail' => (string) ($row['mail'] ?? ''),
            'url' => $this->safeUrl((string) ($row['url'] ?? '')),
            'ip' => (string) ($row['ip'] ?? ''),
            'agent' => (string) ($row['agent'] ?? ''),
            'text' => $text,
            'html' => Typecho::commentHtml(Typecho::markdown($text)),
            'type' => (string) ($row['type'] ?? 'comment'),
            'status' => $status,
            'approved' => $status === 'approved',
            'parent' => (int) ($row['parent'] ?? 0),
            'created' => $this->date($row['created'] ?? null),
        ];

        if (!$includeSensitive) {
            unset($result['mail'], $result['ip'], $result['agent']);
        }

        return $result;
    }

    private function filter(string $status, int $cid, int $authorId, string $search, bool $count = false): Query
    {
        $select = $count ? $this->db->select('COUNT(*) AS num') : $this->db->select('*');
        $select->from('table.comments');

        if ($status !== '') {
            if (!in_array($status, self::STATUSES, true)) {
                Response::error('Invalid comment status', 422, ['status' => '只允许 approved、waiting 与 spam']);
            }
            $select->where('status = ?', $status);
        }

        if ($cid > 0) {
            $select->where('cid = ?', $cid);
        }

        if ($authorId > 0) {
            $select->where('authorId = ?', $authorId);
        }

        if ($search !== '') {
            $like = '%' . $search . '%';
            $select->where('(author LIKE ? OR text LIKE ? OR mail LIKE ?)', $like, $like, $like);
        }

        return $select;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function content(int $cid): ?array
    {
        return $this->row($this->db->select('*')->from('table.contents')
            ->where('cid = ?', $cid)
            ->where('type IN ?', ['post', 'page'])
            ->limit(1));
    }

    /**
     * @param array<string, mixed> $content
     */
    private function assertCommentable(array $content): void
    {
        if ((string) ($content['status'] ?? '') !== 'publish') {
            Response::error('Comments are closed for this content', 403);
        }

        if ((int) ($content['created'] ?? 0) > $this->now()) {
            Response::error('Content not found', 404);
        }

        if ((string) ($content['password'] ?? '') !== '') {
            Response::error('Comments are closed for this content', 403);
        }

        if ((int) ($content['allowComment'] ?? 1) !== 1) {
            Response::error('Comments are closed for this content', 403);
        }

        if ($this->options('commentsAutoClose') && (int) $this->options('commentsPostTimeout', 0) > 0) {
            $timeout = (int) $this->options('commentsPostTimeout', 0);
            if ($this->now() - (int) $content['created'] > $timeout) {
                Response::error('Comments are closed for this content', 403);
            }
        }
    }

    private function resolveParent(int $parent, int $cid): int
    {
        if (!$this->options('commentsThreaded')) {
            return 0;
        }

        $row = $this->row($this->db->select('coid', 'cid', 'parent')->from('table.comments')
            ->where('coid = ?', $parent)->limit(1));
        if ($row === null || (int) ($row['cid'] ?? 0) !== $cid) {
            Response::error('Parent comment not found', 422, ['parent' => '父级评论不存在']);
        }

        $levels = (int) $this->options('commentsMaxNestingLevels', 5);
        if ($levels > 0) {
            $depth = 0;
            $cursor = $row;
            while ((int) ($cursor['parent'] ?? 0) > 0 && $depth <= $levels) {
                $depth++;
                $next = $this->row($this->db->select('coid', 'parent')->from('table.comments')
                    ->where('coid = ?', (int) $cursor['parent'])->limit(1));
                if ($next === null) {
                    break;
                }
                $cursor = $next;
            }

            if ($depth >= $levels) {
                Response::error('Comment nesting limit reached', 422, ['parent' => '超过最大嵌套层级']);
            }
        }

        return $parent;
    }

    private function validateAuthor(string $author): string
    {
        if ($author === '') {
            Response::error('Invalid request body', 422, ['author' => '必须填写用户名']);
        }

        if (mb_strlen($author) > 150) {
            Response::error('Invalid request body', 422, ['author' => '用户名最多 150 个字符']);
        }

        if (strip_tags($author) !== $author || preg_match('/[<>"\']/', $author) === 1) {
            Response::error('Invalid request body', 422, ['author' => '请不要在用户名中使用特殊字符']);
        }

        $registered = $this->row($this->db->select('uid')->from('table.users')
            ->where('(screenName = ? OR name = ?)', $author, $author)
            ->limit(1));
        if ($registered !== null) {
            Response::error('This user name is registered, please log in', 403, ['author' => '请登录后再次提交']);
        }

        return $author;
    }

    private function validateMail(string $mail): string
    {
        if ($mail === '' && $this->options('commentsRequireMail')) {
            Response::error('Invalid request body', 422, ['mail' => '必须填写电子邮箱地址']);
        }

        if ($mail !== '' && (mb_strlen($mail) > 150 || filter_var($mail, FILTER_VALIDATE_EMAIL) === false)) {
            Response::error('Invalid request body', 422, ['mail' => '邮箱地址不合法']);
        }

        return $mail;
    }

    private function validateUrl(string $url): string
    {
        if ($url === '') {
            if ($this->options('commentsRequireUrl')) {
                Response::error('Invalid request body', 422, ['url' => '必须填写个人主页']);
            }

            return '';
        }

        if (mb_strlen($url) > 255 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            Response::error('Invalid request body', 422, ['url' => '个人主页地址格式错误']);
        }

        return $url;
    }

    private function assertInterval(): void
    {
        if (!$this->options('commentsPostIntervalEnable')) {
            return;
        }

        $interval = (int) $this->options('commentsPostInterval', 0);
        if ($interval <= 0) {
            return;
        }

        $row = $this->row($this->db->select('created')->from('table.comments')
            ->where('ip = ?', $this->ip())
            ->order('created', $this->sortDesc())
            ->limit(1));

        if ($row === null) {
            return;
        }

        $elapsed = $this->now() - (int) ($row['created'] ?? 0);
        if ($elapsed >= 0 && $elapsed < $interval) {
            Response::error('You are posting too fast, please retry later', 429);
        }
    }

    /**
     * @param array<string, mixed> $content
     * @param array<string, mixed> $comment
     * @param array<string, mixed>|null $identity
     */
    private function moderatedStatus(array $content, array $comment, ?array $identity): string
    {
        $isOwner = $identity !== null
            && ((int) $identity['uid'] === (int) ($content['authorId'] ?? 0)
                || (new Users())->rank((string) $identity['group']) <= (new Users())->rank('editor'));

        if ($isOwner) {
            return 'approved';
        }

        if ($this->options('commentsRequireModeration')) {
            return 'waiting';
        }

        if ($this->options('commentsWhitelist')) {
            $known = $this->row($this->db->select('coid')->from('table.comments')
                ->where('author = ? AND mail = ? AND status = ?', (string) $comment['author'], (string) $comment['mail'], 'approved')
                ->limit(1));

            return $known === null ? 'waiting' : 'approved';
        }

        return 'approved';
    }

    /**
     * @param array<string, mixed> $comment
     */
    private function insert(array $comment): int
    {
        $coid = (int) $this->db->query($this->db->insert('table.comments')->rows([
            'cid' => (int) $comment['cid'],
            'created' => (int) $comment['created'],
            'author' => (string) ($comment['author'] ?? ''),
            'authorId' => (int) ($comment['authorId'] ?? 0),
            'ownerId' => (int) ($comment['ownerId'] ?? 0),
            'mail' => (string) ($comment['mail'] ?? ''),
            'url' => (string) ($comment['url'] ?? ''),
            'ip' => (string) ($comment['ip'] ?? ''),
            'agent' => mb_substr((string) ($comment['agent'] ?? ''), 0, 511),
            'text' => (string) ($comment['text'] ?? ''),
            'type' => (string) ($comment['type'] ?? 'comment'),
            'status' => (string) ($comment['status'] ?? 'approved'),
            'parent' => (int) ($comment['parent'] ?? 0),
        ]));

        if ($coid > 0) {
            $this->recount((int) $comment['cid']);
        }

        return $coid;
    }

    private function recount(int $cid): void
    {
        if ($cid <= 0) {
            return;
        }

        $object = $this->object($this->db->select('COUNT(coid) AS num')->from('table.comments')
            ->where('status = ?', 'approved')
            ->where('cid = ?', $cid));

        $this->db->query($this->db->update('table.contents')->rows(['commentsNum' => (int) ($object->num ?? 0)])
            ->where('cid = ?', $cid));
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

    private function options(string $name, mixed $default = false): mixed
    {
        $options = Typecho::options();
        if ($options === null) {
            return $default;
        }

        $value = null;
        if (property_exists($options, $name) || method_exists($options, '__get')) {
            $value = $options->{$name};
        }

        return $value === null || $value === '' ? $default : $value;
    }

    private function now(): int
    {
        return time();
    }

    private function ip(): string
    {
        return mb_substr(Request::ip(), 0, 64);
    }

    private function agent(): string
    {
        return mb_substr(Request::header('User-Agent'), 0, 511);
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
            error_log('TTDF REST v2 comment query failed: ' . $exception->getMessage());

            return [];
        }
    }

    private function object(Query $select): ?object
    {
        try {
            $row = $this->db->fetchObject($select);

            return is_object($row) ? $row : null;
        } catch (\Throwable $exception) {
            error_log('TTDF REST v2 comment query failed: ' . $exception->getMessage());

            return null;
        }
    }
}
