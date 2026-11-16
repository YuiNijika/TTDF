<?php

declare(strict_types=1);

namespace TTDF\Http\Rest\V2;

use TTDF\Compat\Typecho;
use TTDF\Http\Request;
use TTDF\Http\Response;
use TTDF\Http\Rest\PublicController;

/**
 * Front-end and session endpoints of the v2 API.
 *
 * Public reads are delegated to the v1 controller so both versions answer with
 * the same payload; the additions here are sessions, the personal area and
 * comment writing.
 */
final class FrontController
{
    private readonly PublicController $public;

    private readonly Users $users;

    private readonly Comments $comments;

    public function __construct(
        ?PublicController $public = null,
        ?Users $users = null,
        ?Comments $comments = null
    ) {
        $this->public = $public ?? new PublicController();
        $this->users = $users ?? new Users();
        $this->comments = $comments ?? new Comments();
    }

    public function site(): never
    {
        $this->public->site(Router::matchedPrefix());
    }

    public function posts(): never
    {
        $this->public->posts();
    }

    public function post(int $cid): never
    {
        $this->public->post($cid);
    }

    public function pages(): never
    {
        $this->public->pages();
    }

    public function page(int $cid): never
    {
        $this->public->page($cid);
    }

    public function categories(): never
    {
        $this->public->categories();
    }

    public function tags(): never
    {
        $this->public->tags();
    }

    public function commentList(): never
    {
        $this->public->comments();
    }

    /**
     * @param array<string, mixed> $identity
     */
    public function attachments(array $identity): never
    {
        $this->public->attachments((int) $identity['uid']);
    }

    public function search(): never
    {
        $this->public->search();
    }

    public function author(int $uid): never
    {
        $user = $this->users->find($uid);
        if ($user === null) {
            Response::error('User not found', 404);
        }

        Response::success($this->users->publicProfile($user));
    }

    public function login(): never
    {
        $body = Payload::body();
        Payload::require($body, 'name', 'password');

        $name = Payload::string($body, 'name', '', 150);
        $password = (string) ($body['password'] ?? '');
        $remember = Payload::bool($body, 'remember', false);
        $ttl = Payload::int(
            $body,
            'ttl',
            $remember ? Token::MAX_TTL : Router::defaultTokenTtl(),
            0,
            Token::MAX_TTL
        );

        if (strlen($password) > 200) {
            Response::error('Invalid request body', 422, ['password' => '密码过长']);
        }

        $auth = new Auth();
        $user = $auth->attempt($name, $password);
        if ($user === null) {
            self::throttleFailedLogin();
            Response::error('Invalid credentials', 401);
        }

        $token = Token::issue((int) $user['uid'], (string) $user['authCode'], $ttl ?: Token::DEFAULT_TTL);
        Auth::startCookieSession($user, $ttl > 0 ? $ttl : 0);

        Response::success([
            'token' => $token['token'],
            'tokenType' => 'Bearer',
            'expiresAt' => date('c', $token['expiresAt']),
            'expiresIn' => $token['expiresAt'] - time(),
            'user' => $this->users->profile($user),
            'source' => 'token',
        ]);
    }

    /**
     * @param array<string, mixed> $identity
     */
    public function logout(array $identity): never
    {
        $body = Payload::body();
        $all = Payload::bool($body, 'all', false);

        Auth::clearCookieSession();

        if ($all) {
            $this->users->rotateAuthCode((int) $identity['uid']);
        }

        Response::success([
            'loggedOut' => true,
            'revoked' => $all ? 'all-sessions' : 'current-client',
            'uid' => (int) $identity['uid'],
        ]);
    }

    /**
     * @param array<string, mixed> $identity
     */
    public function session(array $identity): never
    {
        Response::success([
            'authenticated' => true,
            'user' => Auth::sessionProfile($identity),
            'tokenTtl' => Router::defaultTokenTtl(),
        ]);
    }

    /**
     * @param array<string, mixed> $identity
     */
    public function me(array $identity): never
    {
        $user = $this->users->find((int) $identity['uid']);
        if ($user === null) {
            Response::error('User not found', 404);
        }

        Response::success($this->users->profile($user));
    }

    /**
     * @param array<string, mixed> $identity
     */
    public function updateMe(array $identity): never
    {
        $body = Payload::body();
        $input = [];

        foreach (['name', 'screenName', 'mail', 'url'] as $key) {
            if (Payload::has($body, $key)) {
                $input[$key] = Payload::string($body, $key, '', $key === 'url' ? 255 : 150);
            }
        }

        if (isset($input['url']) && $input['url'] !== '' && filter_var($input['url'], FILTER_VALIDATE_URL) === false) {
            Response::error('Invalid request body', 422, ['url' => '个人主页地址格式错误']);
        }

        if ($input === []) {
            Response::error('Invalid request body', 422, ['body' => '没有需要更新的字段']);
        }

        Response::success($this->users->update((int) $identity['uid'], $input));
    }

    /**
     * @param array<string, mixed> $identity
     */
    public function changePassword(array $identity): never
    {
        $body = Payload::body();
        Payload::require($body, 'currentPassword', 'password');

        $current = (string) $body['currentPassword'];
        $password = (string) $body['password'];
        $user = $this->users->find((int) $identity['uid']);

        if ($user === null || !Typecho::hashValidate($current, (string) $user['password'])) {
            self::throttleFailedLogin();
            Response::error('Invalid credentials', 401);
        }

        $updated = $this->users->update((int) $identity['uid'], ['password' => $password]);
        $token = Token::issue(
            (int) $updated['uid'],
            (string) ($this->users->find((int) $updated['uid'])['authCode'] ?? ''),
            Router::defaultTokenTtl()
        );

        Response::success([
            'updated' => true,
            'token' => $token['token'],
            'expiresAt' => date('c', $token['expiresAt']),
        ]);
    }

    /**
     * @param array<string, mixed> $identity
     */
    public function myComments(array $identity): never
    {
        $result = $this->comments->listing([
            'authorId' => (int) $identity['uid'],
            'page' => Request::queryInt('page', 1),
            'pageSize' => Request::queryInt('pageSize', 20),
            'status' => Request::queryString('status'),
        ], false);

        Response::success($result['items'], self::paginationMeta($result['total']));
    }

    public function createComment(): never
    {
        $body = Payload::body();
        Payload::require($body, 'cid', 'text');

        $comment = $this->comments->create([
            'cid' => Payload::int($body, 'cid', 0, 1),
            'text' => Payload::string($body, 'text', '', 20000),
            'parent' => Payload::int($body, 'parent', 0, 0),
            'author' => Payload::string($body, 'author', '', 150),
            'mail' => Payload::string($body, 'mail', '', 150),
            'url' => Payload::string($body, 'url', '', 255),
        ], Auth::identity());

        Response::success($comment, [], 201);
    }

    /**
     * @param array<string, mixed> $identity
     */
    public function deleteComment(array $identity, int $coid): never
    {
        $comment = $this->comments->find($coid);
        if ($comment === null) {
            Response::error('Comment not found', 404);
        }

        $isModerator = $this->users->rank((string) $identity['group']) <= $this->users->rank('editor');
        $isAuthor = (int) ($comment['authorId'] ?? 0) === (int) $identity['uid'];
        $isOwner = (int) ($comment['ownerId'] ?? 0) === (int) $identity['uid'];

        if (!$isModerator && !$isAuthor && !$isOwner) {
            Response::error('Insufficient permissions', 403);
        }

        Response::success(['deleted' => $this->comments->delete($coid)]);
    }

    private static function throttleFailedLogin(): void
    {
        if (!Response::isCapturing()) {
            // Slows credential stuffing without telling the caller which part
            // of the pair was wrong.
            sleep(1);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function paginationMeta(int $total): array
    {
        $page = max(1, Request::queryInt('page', 1));
        $pageSize = Payload::clamp(Request::queryInt('pageSize', 20), 1, 100, 20);

        return [
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
            'lastPage' => $total === 0 ? 0 : (int) ceil($total / $pageSize),
        ];
    }
}
