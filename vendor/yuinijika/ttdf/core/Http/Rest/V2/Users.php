<?php

declare(strict_types=1);

namespace TTDF\Http\Rest\V2;

use TTDF\Compat\Typecho;
use TTDF\Http\Response;
use Typecho\Db;
use Typecho\Db\Query;

/**
 * User rows for the v2 API.
 *
 * Rows are read and written with Typecho's own schema so a site keeps working
 * from the admin screen; passwords go through `Typecho\Common::hash()`, which is
 * what `Widget\User::login()` validates against.
 */
final class Users
{
    /** @var array<string, int> */
    public const GROUPS = [
        'administrator' => 0,
        'editor' => 1,
        'contributor' => 2,
        'subscriber' => 3,
        'visitor' => 4,
    ];

    public const PUBLIC_COLUMNS = ['uid', 'name', 'screenName', 'mail', 'url', 'group', 'created', 'activated', 'logged'];

    private readonly Db $db;

    public function __construct(?Db $db = null)
    {
        $this->db = $db ?? Db::get();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $uid): ?array
    {
        if ($uid <= 0) {
            return null;
        }

        return $this->row($this->db->select('*')->from('table.users')->where('uid = ?', $uid)->limit(1));
    }

    /**
     * Resolve a login identifier; Typecho accepts the user name and, when the
     * input looks like an address, the mail column.
     *
     * @return array<string, mixed>|null
     */
    public function findByIdentifier(string $identifier): ?array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        $select = $this->db->select('*')->from('table.users')->where('name = ?', $identifier)->limit(1);
        $row = $this->row($select);
        if ($row !== null) {
            return $row;
        }

        if (str_contains($identifier, '@')) {
            return $this->row(
                $this->db->select('*')->from('table.users')->where('mail = ?', $identifier)->limit(1)
            );
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(int $page, int $pageSize, string $search = ''): array
    {
        $select = $this->db->select(...self::PUBLIC_COLUMNS)->from('table.users')
            ->order('uid', $this->sortAsc());

        if ($search !== '') {
            $like = '%' . $search . '%';
            $select->where('(name LIKE ? OR screenName LIKE ? OR mail LIKE ?)', $like, $like, $like);
        }

        $select->offset(($page - 1) * $pageSize)->limit($pageSize);

        $rows = [];
        foreach ($this->rows($select) as $row) {
            $rows[] = $this->profile($row);
        }

        return $rows;
    }

    public function count(string $search = ''): int
    {
        $select = $this->db->select('COUNT(*) AS num')->from('table.users');
        if ($search !== '') {
            $like = '%' . $search . '%';
            $select->where('(name LIKE ? OR screenName LIKE ? OR mail LIKE ?)', $like, $like, $like);
        }

        $row = $this->object($select);

        return (int) ($row->num ?? 0);
    }

    public function activate(int $uid): void
    {
        $this->db->query($this->db->update('table.users')->rows(['activated' => time()])->where('uid = ?', $uid));
    }

    public function touchLogin(int $uid): void
    {
        $this->db->query($this->db->update('table.users')->rows(['logged' => time()])->where('uid = ?', $uid));
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function create(array $input): array
    {
        $name = (string) $input['name'];
        $mail = (string) $input['mail'];
        $password = (string) $input['password'];
        $group = (string) ($input['group'] ?? 'contributor');
        $screenName = (string) ($input['screenName'] ?? $name);
        $url = (string) ($input['url'] ?? '');

        $this->assertGroup($group);

        if (preg_match('/^[A-Za-z0-9_-]{1,32}$/', $name) !== 1) {
            Response::error('Invalid user name', 422, ['name' => '只允许字母、数字、下划线与短横线，最多 32 个字符']);
        }

        if (strlen($password) < 6) {
            Response::error('Invalid password', 422, ['password' => '密码至少 6 个字符']);
        }

        $this->assertUnique('name', $name, 0);
        $this->assertUnique('mail', $mail, 0);

        $now = time();
        $authCode = Typecho::random(32);
        $uid = (int) $this->db->query($this->db->insert('table.users')->rows([
            'name' => $name,
            'password' => Typecho::hash($password),
            'mail' => $mail,
            'url' => $url,
            'screenName' => $screenName,
            'created' => $now,
            'activated' => $now,
            'logged' => 0,
            'group' => $group,
            'authCode' => $authCode,
        ]));

        if ($uid <= 0) {
            Response::error('Unable to create the user', 500);
        }

        return $this->profile((array) $this->find($uid));
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function update(int $uid, array $input): array
    {
        $user = $this->find($uid);
        if ($user === null) {
            Response::error('User not found', 404);
        }

        $rows = [];

        if (array_key_exists('name', $input)) {
            $name = (string) $input['name'];
            if (preg_match('/^[A-Za-z0-9_-]{1,32}$/', $name) !== 1) {
                Response::error('Invalid user name', 422, ['name' => '只允许字母、数字、下划线与短横线，最多 32 个字符']);
            }
            $this->assertUnique('name', $name, $uid);
            $rows['name'] = $name;
        }

        if (array_key_exists('mail', $input)) {
            $mail = (string) $input['mail'];
            $this->assertUnique('mail', $mail, $uid);
            $rows['mail'] = $mail;
        }

        if (array_key_exists('screenName', $input)) {
            $rows['screenName'] = (string) $input['screenName'];
        }

        if (array_key_exists('url', $input)) {
            $rows['url'] = (string) $input['url'];
        }

        if (array_key_exists('group', $input)) {
            $group = (string) $input['group'];
            $this->assertGroup($group);
            if ($user['group'] === 'administrator' && $group !== 'administrator') {
                $this->assertNotLastAdministrator($uid);
            }
            $rows['group'] = $group;
        }

        if (array_key_exists('password', $input)) {
            $password = (string) $input['password'];
            if (strlen($password) < 6) {
                Response::error('Invalid password', 422, ['password' => '密码至少 6 个字符']);
            }

            // Rotating the auth code invalidates every issued token and cookie
            // session for this account.
            $rows['password'] = Typecho::hash($password);
            $rows['authCode'] = Typecho::random(32);
        }

        if ($rows !== []) {
            $this->db->query($this->db->update('table.users')->rows($rows)->where('uid = ?', $uid));
        }

        return $this->profile((array) $this->find($uid));
    }

    public function delete(int $uid): void
    {
        $user = $this->find($uid);
        if ($user === null) {
            Response::error('User not found', 404);
        }

        if ($user['group'] === 'administrator') {
            $this->assertNotLastAdministrator($uid);
        }

        $this->db->query($this->db->delete('table.users')->where('uid = ?', $uid));
    }

    /**
     * Regenerate the auth code so every existing credential stops working.
     */
    public function rotateAuthCode(int $uid): string
    {
        $authCode = Typecho::random(32);
        $this->db->query($this->db->update('table.users')->rows(['authCode' => $authCode])->where('uid = ?', $uid));

        return $authCode;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function profile(array $row): array
    {
        return [
            'uid' => (int) ($row['uid'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'screenName' => (string) ($row['screenName'] ?? ''),
            'mail' => (string) ($row['mail'] ?? ''),
            'url' => (string) ($row['url'] ?? ''),
            'group' => (string) ($row['group'] ?? ''),
            'created' => $this->date($row['created'] ?? null),
            'activated' => $this->date($row['activated'] ?? null),
            'logged' => $this->date($row['logged'] ?? null),
        ];
    }

    /**
     * Author data shown on public endpoints. Contact details and login
     * timestamps stay out of the payload; only identity and profile links are
     * useful to a reader.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function publicProfile(array $row): array
    {
        return [
            'uid' => (int) ($row['uid'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'screenName' => (string) ($row['screenName'] ?? ''),
            'url' => (string) ($row['url'] ?? ''),
            'group' => (string) ($row['group'] ?? ''),
            'created' => $this->date($row['created'] ?? null),
        ];
    }

    public function rank(string $group): int
    {
        return self::GROUPS[$group] ?? PHP_INT_MAX;
    }

    private function assertGroup(string $group): void
    {
        if (!array_key_exists($group, self::GROUPS)) {
            Response::error('Invalid user group', 422, ['group' => '未知的用户组']);
        }
    }

    private function assertUnique(string $column, string $value, int $exceptUid): void
    {
        if ($value === '') {
            Response::error('Invalid user data', 422, [$column => '不能为空']);
        }

        $select = $this->db->select('uid')->from('table.users')->where($column . ' = ?', $value)->limit(1);
        if ($exceptUid > 0) {
            $select->where('uid <> ?', $exceptUid);
        }

        if ($this->row($select) !== null) {
            Response::error('User already exists', 422, [$column => '已被占用']);
        }
    }

    private function assertNotLastAdministrator(int $uid): void
    {
        $select = $this->db->select('COUNT(*) AS num')->from('table.users')
            ->where('group = ?', 'administrator')
            ->where('uid <> ?', $uid);
        $row = $this->object($select);

        if ((int) ($row->num ?? 0) === 0) {
            Response::error('The last administrator cannot be removed or demoted', 409);
        }
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
            error_log('TTDF REST v2 user query failed: ' . $exception->getMessage());

            return [];
        }
    }

    private function object(Query $select): ?object
    {
        try {
            $row = $this->db->fetchObject($select);

            return is_object($row) ? $row : null;
        } catch (\Throwable $exception) {
            error_log('TTDF REST v2 user query failed: ' . $exception->getMessage());

            return null;
        }
    }

    private function date(mixed $value): string
    {
        return is_numeric($value) && (int) $value > 0 ? date('c', (int) $value) : '';
    }

    private function sortAsc(): string
    {
        return defined('Typecho\\Db::SORT_ASC') ? (string) constant('Typecho\\Db::SORT_ASC') : 'ASC';
    }
}
