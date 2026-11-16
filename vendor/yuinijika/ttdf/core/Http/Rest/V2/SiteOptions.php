<?php

declare(strict_types=1);

namespace TTDF\Http\Rest\V2;

use TTDF\Compat\Typecho;
use TTDF\Framework;
use TTDF\Http\Response;
use Typecho\Db;

/**
 * A curated view of Typecho's site options.
 *
 * The admin screen exposes far more than an API client needs, and several of
 * those keys can lock a site out of itself (`siteUrl`, `rewrite`, the active
 * theme directory). Only the keys listed here can be read or written.
 */
final class SiteOptions
{
    /** @var array<string, string> */
    public const FIELDS = [
        'title' => 'string',
        'description' => 'string',
        'keywords' => 'string',
        'lang' => 'string',
        'timezone' => 'int',
        'postsListSize' => 'int',
        'pageSize' => 'int',
        'markdown' => 'bool',
        'allowRegister' => 'bool',
        'defaultAllowComment' => 'bool',
        'defaultAllowPing' => 'bool',
        'defaultAllowFeed' => 'bool',
        'commentsRequireModeration' => 'bool',
        'commentsWhitelist' => 'bool',
        'commentsRequireMail' => 'bool',
        'commentsRequireUrl' => 'bool',
        'commentsThreaded' => 'bool',
        'commentsAutoClose' => 'bool',
        'commentsPostIntervalEnable' => 'bool',
        'commentsMarkdown' => 'bool',
        'commentsAntiSpam' => 'bool',
        'commentsCheckReferer' => 'bool',
        'commentsPostInterval' => 'int',
        'commentsPostTimeout' => 'int',
        'commentsMaxNestingLevels' => 'int',
        'commentsHTMLTagAllowed' => 'html',
    ];

    private const ALLOWED_COMMENT_TAGS = [
        'a',
        'b',
        'i',
        'em',
        'strong',
        'code',
        'pre',
        'blockquote',
        'ul',
        'ol',
        'li',
        'p',
        'br',
        'strike',
        'del',
        'ins',
        'sub',
        'sup',
    ];

    private readonly Db $db;

    public function __construct(?Db $db = null)
    {
        $this->db = $db ?? Db::get();
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $stored = $this->stored();
        $result = [];

        foreach (array_keys(self::FIELDS) as $name) {
            $value = $stored[$name] ?? $this->option($name);

            $result[$name] = $this->cast($name, $value);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        return [
            'site' => [
                'title' => (string) ($this->option('title') ?? ''),
                'description' => (string) ($this->option('description') ?? ''),
                'url' => Typecho::siteUrl(),
                'theme' => Typecho::themeName(),
                'timezone' => (int) ($this->option('timezone') ?? 0),
            ],
            'versions' => [
                'framework' => Framework::VERSION,
                'typecho' => Typecho::version(),
                'php' => PHP_VERSION,
            ],
            'counts' => [
                'post' => $this->countContents('post'),
                'page' => $this->countContents('page'),
                'draft' => $this->countContents('draft'),
                'comment' => $this->countComments('approved'),
                'commentWaiting' => $this->countComments('waiting'),
                'commentSpam' => $this->countComments('spam'),
                'user' => $this->countUsers(),
                'category' => $this->countTerms('category'),
                'tag' => $this->countTerms('tag'),
            ],
            'rest' => [
                'v1Enabled' => \TTDF\Http\Rest\State::enabled(),
                'tokenEnabled' => \TTDF\Http\Rest\State::tokenEnabled(),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{saved: list<string>, options: array<string, mixed>}
     */
    public function update(array $input): array
    {
        $saved = [];

        foreach ($input as $name => $value) {
            if (!array_key_exists($name, self::FIELDS)) {
                continue;
            }

            $this->assertValue($name, $value);
            $this->write($name, $this->cast($name, $value));
            $saved[] = $name;
        }

        if ($saved === []) {
            Response::error('No valid options were provided', 422);
        }

        return ['saved' => $saved, 'options' => $this->all()];
    }

    /**
     * Read the stored rows directly so a response right after a write does not
     * return the widget's request-level cache.
     *
     * @return array<string, mixed>
     */
    private function stored(): array
    {
        try {
            $rows = $this->db->fetchAll(
                $this->db->select('name', 'value')->from('table.options')->where('user = ?', 0)
            );
        } catch (\Throwable $exception) {
            error_log('TTDF REST v2 option query failed: ' . $exception->getMessage());

            return [];
        }

        $stored = [];
        foreach ($rows as $row) {
            $name = (string) ($row['name'] ?? '');
            if ($name !== '' && array_key_exists($name, self::FIELDS)) {
                $stored[$name] = $row['value'] ?? '';
            }
        }

        return $stored;
    }

    private function write(string $name, mixed $value): void
    {
        $stored = is_bool($value) ? ($value ? '1' : '0') : (string) $value;

        $affected = (int) $this->db->query(
            $this->db->update('table.options')->rows(['value' => $stored])
                ->where('name = ?', $name)
                ->where('user = ?', 0)
        );

        if ($affected > 0) {
            return;
        }

        $exists = $this->db->fetchObject(
            $this->db->select('name')->from('table.options')->where('name = ?', $name)->where('user = ?', 0)->limit(1)
        );

        if ($exists === null) {
            $this->db->query($this->db->insert('table.options')->rows([
                'name' => $name,
                'user' => 0,
                'value' => $stored,
            ]));
        }
    }

    private function cast(string $name, mixed $value): mixed
    {
        $type = self::FIELDS[$name];

        if ($value === null) {
            $value = '';
        }

        if ($type === 'bool') {
            if (is_bool($value)) {
                return $value;
            }

            return is_scalar($value)
                && in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
        }

        if ($type === 'int') {
            $int = is_numeric($value) ? (int) $value : 0;

            return match ($name) {
                'postsListSize', 'pageSize' => max(1, min(100, $int)),
                'commentsPostInterval', 'commentsPostTimeout' => max(0, min(2592000, $int)),
                'commentsMaxNestingLevels' => max(0, min(10, $int)),
                'timezone' => max(-43200, min(50400, $int)),
                default => $int,
            };
        }

        if ($type === 'html') {
            return $this->sanitizeTags((string) $value);
        }

        return mb_substr(
            trim((string) $value),
            0,
            $name === 'description' || $name === 'keywords' ? 500 : 150
        );
    }

    /**
     * Reject unusable values on write. Reads stay tolerant, because an option
     * the site never stored must not turn a plain read into an error response.
     */
    private function assertValue(string $name, mixed $value): void
    {
        if (self::FIELDS[$name] !== 'string' || $name !== 'lang') {
            return;
        }

        $text = is_scalar($value) ? trim((string) $value) : '';
        if (preg_match('/^[a-zA-Z-]{2,12}$/', $text) !== 1) {
            Response::error('Invalid option value', 422, ['lang' => '语言代码不合法']);
        }
    }

    private function sanitizeTags(string $value): string
    {
        preg_match_all('/<\s*([a-zA-Z][a-zA-Z0-9]*)/', $value, $matches);

        $tags = [];
        foreach ($matches[1] as $tag) {
            $tag = strtolower($tag);
            if (in_array($tag, self::ALLOWED_COMMENT_TAGS, true) && !in_array($tag, $tags, true)) {
                $tags[] = $tag;
            }
        }

        return implode('', array_map(static fn (string $tag): string => '<' . $tag . '>', $tags));
    }

    private function option(string $name): mixed
    {
        $options = Typecho::options();
        if ($options === null) {
            return null;
        }

        if (property_exists($options, $name) || method_exists($options, '__get')) {
            return $options->{$name};
        }

        return null;
    }

    private function countContents(string $type): int
    {
        $select = $this->db->select('COUNT(*) AS num')->from('table.contents');

        $select->where(match ($type) {
            'draft' => 'type IN ?',
            default => 'type = ?',
        }, $type === 'draft' ? ['post_draft', 'page_draft'] : $type);

        if ($type !== 'draft') {
            $select->where('status IN ?', ['publish', 'hidden', 'private', 'waiting']);
        }

        $row = $this->object($select);

        return (int) ($row->num ?? 0);
    }

    private function countComments(string $status): int
    {
        $row = $this->object($this->db->select('COUNT(*) AS num')->from('table.comments')
            ->where('status = ?', $status));

        return (int) ($row->num ?? 0);
    }

    private function countUsers(): int
    {
        $row = $this->object($this->db->select('COUNT(*) AS num')->from('table.users'));

        return (int) ($row->num ?? 0);
    }

    private function countTerms(string $type): int
    {
        $row = $this->object($this->db->select('COUNT(*) AS num')->from('table.metas')
            ->where('type = ?', $type));

        return (int) ($row->num ?? 0);
    }

    private function object(\Typecho\Db\Query $select): ?object
    {
        try {
            $row = $this->db->fetchObject($select);

            return is_object($row) ? $row : null;
        } catch (\Throwable $exception) {
            error_log('TTDF REST v2 option query failed: ' . $exception->getMessage());

            return null;
        }
    }
}
