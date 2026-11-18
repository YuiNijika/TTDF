<?php

declare(strict_types=1);

namespace TTDF\Http\Rest\V2;

use TTDF\Compat\Typecho;
use TTDF\Hooks\Hooks;
use TTDF\Http\Request;
use TTDF\Http\Response;
use TTDF\Http\ResponseException;
use TTDF\Http\Rest\MediaController;
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

    /**
     * The editor media library is scoped to the signed-in writer so an editor
     * cannot browse, rename or delete another user's uploads.
     *
     * @param array<string, mixed> $identity
     */
    public function mediaList(array $identity): never
    {
        $this->media($identity)->index();
    }

    /**
     * @param array<string, mixed> $identity
     */
    public function mediaShow(array $identity, int $id): never
    {
        $this->media($identity)->show($id);
    }

    /**
     * @param array<string, mixed> $identity
     */
    public function mediaUpload(array $identity): never
    {
        $this->media($identity)->upload();
    }

    /**
     * @param array<string, mixed> $identity
     */
    public function mediaRename(array $identity, int $id): never
    {
        $this->media($identity)->rename($id);
    }

    /**
     * @param array<string, mixed> $identity
     */
    public function mediaDelete(array $identity, int $id): never
    {
        $this->media($identity)->delete($id);
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

    /**
     * @param array<string, mixed> $identity
     */
    private function media(array $identity): MediaController
    {
        return new MediaController(null, (int) $identity['uid']);
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
     * Self-service registration for the theme's login dialog.
     *
     * Typecho's own registration screen creates a random password, which does
     * not fit an API caller that supplies its own password. The account is
     * still created with Typecho's schema and hashing rules; the site owner
     * controls availability with the native `allowRegister` option.
     */
    public function register(): never
    {
        if (!self::allowRegister()) {
            Response::error('Registration is disabled', 403);
        }

        $body = Payload::body();
        Payload::require($body, 'name', 'mail', 'password');

        $name = Payload::string($body, 'name', '', 32);
        $mail = Payload::string($body, 'mail', '', 64);
        $password = $body['password'] ?? '';
        if (!is_string($password)) {
            Response::error('Invalid request body', 422, ['password' => '密码必须是字符串']);
        }

        $input = [
            'name' => $name,
            'mail' => $mail,
            'password' => $password,
            'code' => Payload::string($body, 'code', '', 12),
        ];

        $allowed = Hooks::applyFilters('rest.v2.register.can_register', true, $input);
        if ($allowed !== true) {
            Response::error('Registration is disabled', 403);
        }

        $verified = Hooks::applyFilters('rest.v2.register.validate', true, $input);
        if ($verified !== true) {
            Response::error('Registration verification failed', 422);
        }

        Hooks::doAction('rest.v2.register.before_create', $input);
        $user = $this->users->register($input);
        Hooks::doAction('rest.v2.register.created', $user, $input);

        $ttl = Router::defaultTokenTtl();
        $token = Token::issue((int) $user['uid'], (string) $user['authCode'], $ttl);
        Auth::startCookieSession($user, $ttl);

        Response::success([
            'token' => $token['token'],
            'tokenType' => 'Bearer',
            'expiresAt' => date('c', $token['expiresAt']),
            'expiresIn' => $token['expiresAt'] - time(),
            'user' => $this->users->profile($user),
            'source' => 'token',
        ], [], 201);
    }

    /**
     * Issue a short-lived verification code for registration, password reset
     * or e-mail changes. Delivery is delegated to a server-trusted theme hook;
     * the API never returns the code itself.
     */
    public function emailCode(): never
    {
        $body = Payload::body();
        Payload::require($body, 'mail', 'purpose');

        $mail = Payload::string($body, 'mail', '', 64);
        $purpose = strtolower(Payload::string($body, 'purpose', '', 16));

        if (!self::validMail($mail)) {
            Response::error('Invalid email address', 422, ['mail' => '电子邮箱格式错误']);
        }

        if (!in_array($purpose, ['register', 'reset', 'email'], true)) {
            Response::error('Invalid request body', 422, ['purpose' => '未知的验证码用途']);
        }

        $identity = Auth::identity() ?? [];
        $ttl = max(60, min(3600, (int) Hooks::applyFilters('rest.v2.email_code.ttl', 600, $mail, $purpose)));
        $interval = max(
            10,
            min(600, (int) Hooks::applyFilters('rest.v2.email_code.interval', 60, $mail, $purpose))
        );

        if ($purpose === 'register') {
            if (!self::allowRegister()) {
                Response::error('Registration is disabled', 403);
            }

            if ($this->users->mailExists($mail)) {
                Response::error('Invalid email address', 422, ['mail' => '该邮箱已被注册']);
            }
        }

        if ($purpose === 'email') {
            $identity = Auth::requireTier(Auth::TIER_USER);
            if (strcasecmp((string) $identity['mail'], $mail) === 0) {
                Response::error('Invalid email address', 422, ['mail' => '新邮箱不能与当前邮箱相同']);
            }

            if ($this->users->mailExists($mail, (int) $identity['uid'])) {
                Response::error('Invalid email address', 422, ['mail' => '该邮箱已被占用']);
            }
        }

        $allowed = Hooks::applyFilters(
            'rest.v2.email_code.can_send',
            true,
            $mail,
            $purpose,
            $identity
        );

        if ($allowed !== true || !Hooks::hasAction('rest.v2.email_code.send')) {
            Response::error('Email delivery is unavailable', 503);
        }

        if ($purpose === 'reset' && $this->users->findByMail($mail) === null) {
            // Do not reveal whether an address belongs to an account.
            Response::success([
                'sent' => true,
                'purpose' => $purpose,
                'expiresIn' => $ttl,
                'cooldown' => $interval,
            ]);
        }

        try {
            $issued = VerificationCodes::issue($mail, $purpose, $ttl, $interval);
            Hooks::doAction(
                'rest.v2.email_code.send',
                $mail,
                $purpose,
                $issued['code'],
                $issued['expiresAt'],
                $identity
            );
            Hooks::doAction(
                'rest.v2.email_code.sent',
                $mail,
                $purpose,
                $issued['expiresAt'],
                $identity
            );
        } catch (ResponseException $exception) {
            throw $exception;
        } catch (RateLimitException $exception) {
            Response::error(
                'Please wait before requesting another code',
                429,
                ['mail' => $exception->retryAfter() . ' 秒后可重新发送']
            );
        } catch (\Throwable $exception) {
            VerificationCodes::clear($mail, $purpose);
            error_log('TTDF REST v2 verification mail failed');
            Response::error('Unable to send verification email', 503);
        }

        Response::success([
            'sent' => true,
            'purpose' => $purpose,
            'expiresIn' => max(0, $issued['expiresAt'] - time()),
            'cooldown' => $interval,
        ]);
    }

    /**
     * Reset a forgotten password after proving control of the account mailbox.
     */
    public function resetPassword(): never
    {
        $body = Payload::body();
        Payload::require($body, 'mail', 'code', 'password');

        $mail = Payload::string($body, 'mail', '', 64);
        $code = Payload::string($body, 'code', '', 12);
        $password = self::passwordValue($body, 'password');

        if (!self::validMail($mail)) {
            Response::error('Invalid verification code', 422, ['code' => '验证码无效或已过期']);
        }

        $allowed = Hooks::applyFilters('rest.v2.password.reset.can_reset', true, $mail);
        if ($allowed !== true || !Hooks::hasAction('rest.v2.email_code.send')) {
            Response::error('Password reset is disabled', 403);
        }

        if (!VerificationCodes::verify($mail, 'reset', $code)) {
            Response::error('Invalid verification code', 422, ['code' => '验证码无效或已过期']);
        }

        $verified = Hooks::applyFilters('rest.v2.password.reset.verify', true, $mail, $code);
        if ($verified !== true) {
            Response::error('Invalid verification code', 422, ['code' => '验证码无效或已过期']);
        }

        $user = $this->users->findByMail($mail);
        if ($user === null) {
            Response::error('Invalid verification code', 422, ['code' => '验证码无效或已过期']);
        }

        Hooks::doAction('rest.v2.password.reset.before', $user, $mail);
        $updated = $this->users->update((int) $user['uid'], ['password' => $password]);
        Hooks::doAction('rest.v2.password.reset.after', $updated, $mail);

        Response::success(['reset' => true]);
    }

    private static function allowRegister(): bool
    {
        $options = Typecho::options();
        if ($options === null) {
            return false;
        }

        if (!property_exists($options, 'allowRegister') && !method_exists($options, '__get')) {
            return false;
        }

        return (bool) ($options->{'allowRegister'} ?? false);
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
        $current = $this->users->find((int) $identity['uid']);
        if ($current === null) {
            Response::error('User not found', 404);
        }

        foreach (['name', 'screenName', 'mail', 'url'] as $key) {
            if (Payload::has($body, $key)) {
                $limit = match ($key) {
                    'url' => 255,
                    'mail' => 64,
                    'name' => 32,
                    default => 150,
                };
                $input[$key] = Payload::string($body, $key, '', $limit);
            }
        }

        if (isset($input['url']) && $input['url'] !== '' && filter_var($input['url'], FILTER_VALIDATE_URL) === false) {
            Response::error('Invalid request body', 422, ['url' => '个人主页地址格式错误']);
        }

        $mailChanged = false;
        if (isset($input['mail']) && strcasecmp($input['mail'], (string) $current['mail']) !== 0) {
            if (!self::validMail($input['mail'])) {
                Response::error('Invalid email address', 422, ['mail' => '电子邮箱格式错误']);
            }

            $allowed = Hooks::applyFilters(
                'rest.v2.account.email.verify',
                true,
                (string) $current['mail'],
                $input['mail'],
                Payload::string($body, 'emailCode', '', 12)
            );

            if ($allowed !== true) {
                Response::error('Invalid verification code', 422, ['emailCode' => '验证码无效或已过期']);
            }

            Hooks::doAction('rest.v2.account.email.before_update', $current, $input['mail']);
            $mailChanged = true;
        }

        if ($input === []) {
            Response::error('Invalid request body', 422, ['body' => '没有需要更新的字段']);
        }

        $updated = $this->users->update((int) $identity['uid'], $input);
        if ($mailChanged) {
            Hooks::doAction('rest.v2.account.email.updated', $updated, (string) $current['mail']);
        }

        Response::success($updated);
    }

    /**
     * @param array<string, mixed> $identity
     */
    public function changePassword(array $identity): never
    {
        $body = Payload::body();
        Payload::require($body, 'currentPassword', 'password');

        $currentPassword = Payload::string($body, 'currentPassword', '', 200);
        $password = self::passwordValue($body, 'password');
        $user = $this->users->find((int) $identity['uid']);

        if ($user === null || !Typecho::hashValidate($currentPassword, (string) $user['password'])) {
            self::throttleFailedLogin();
            Response::error('Invalid credentials', 401);
        }

        Hooks::doAction('rest.v2.password.change.before', $user);
        $this->users->update((int) $identity['uid'], ['password' => $password]);
        $updated = $this->users->find((int) $identity['uid']);
        if ($updated === null) {
            Response::error('User not found', 404);
        }

        $ttl = Router::defaultTokenTtl();
        $token = Token::issue((int) $updated['uid'], (string) $updated['authCode'], $ttl);
        Auth::startCookieSession($updated, $ttl);
        Hooks::doAction('rest.v2.password.change.after', $this->users->profile($updated));

        Response::success([
            'updated' => true,
            'token' => $token['token'],
            'expiresAt' => date('c', $token['expiresAt']),
            'user' => $this->users->profile($updated),
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
     * @param array<string, mixed> $body
     */
    private static function passwordValue(array $body, string $key): string
    {
        $value = $body[$key] ?? '';
        if (!is_string($value)) {
            Response::error('Invalid request body', 422, [$key => '密码必须是字符串']);
        }

        $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
        if ($length < 6 || $length > 18) {
            Response::error('Invalid password', 422, [$key => '密码长度需为 6–18 个字符']);
        }

        return $value;
    }

    private static function validMail(string $mail): bool
    {
        $sanitized = filter_var($mail, FILTER_SANITIZE_EMAIL);
        $length = function_exists('mb_strlen') ? mb_strlen($mail, 'UTF-8') : strlen($mail);

        return $mail !== ''
            && $length <= 64
            && is_string($sanitized)
            && $sanitized === $mail
            && filter_var($mail, FILTER_VALIDATE_EMAIL) !== false;
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
