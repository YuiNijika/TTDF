<?php

declare(strict_types=1);

namespace TTDF\Http\Rest\V2;

use TTDF\Compat\Typecho;
use TTDF\Http\Request;
use TTDF\Http\Response;

/**
 * Identity resolution for the v2 API.
 *
 * Two credentials are accepted: a v2 bearer token issued by `POST /auth/login`,
 * and Typecho's own login cookie. Cookie authenticated writes additionally have
 * to come from the site origin, because a cookie is a browser credential that
 * any third-party page can make the browser send.
 */
final class Auth
{
    public const TIER_PUBLIC = 'public';

    public const TIER_USER = 'user';

    public const TIER_WRITER = 'writer';

    public const TIER_ADMIN = 'admin';

    private const COOKIE_UID = '__typecho_uid';

    private const COOKIE_AUTH = '__typecho_authCode';

    /** @var array<string, mixed>|null */
    private static ?array $identity = null;

    private static bool $resolved = false;

    private readonly Users $users;

    public function __construct(?Users $users = null)
    {
        $this->users = $users ?? new Users();
    }

    public static function reset(): void
    {
        self::$identity = null;
        self::$resolved = false;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function identity(): ?array
    {
        if (self::$resolved) {
            return self::$identity;
        }

        self::$resolved = true;

        return self::$identity = (new self())->resolve();
    }

    public static function authenticated(): bool
    {
        return self::identity() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function requireTier(string $tier): array
    {
        if ($tier === self::TIER_PUBLIC) {
            return [];
        }

        $identity = self::identity();
        if ($identity === null) {
            Response::error('Authentication required', 401);
        }

        if ($tier === self::TIER_USER) {
            return $identity;
        }

        $required = $tier === self::TIER_ADMIN ? 'administrator' : 'editor';
        if ((new Users())->rank((string) $identity['group']) > (new Users())->rank($required)) {
            Response::error('Insufficient permissions', 403);
        }

        return $identity;
    }

    /**
     * Guard unsafe methods that were authenticated by a cookie instead of a
     * bearer token.
     */
    public static function assertTrustedOrigin(string $method): void
    {
        if (!in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }

        $identity = self::identity();
        if ($identity === null || ($identity['source'] ?? '') !== 'cookie') {
            return;
        }

        if (!self::originMatches()) {
            Response::error(
                'Cookie sessions must send a same-origin request; use a bearer token for API clients',
                403
            );
        }
    }

    public static function originMatches(): bool
    {
        $origin = trim(Request::header('Origin'));
        if ($origin === '') {
            $referer = trim(Request::header('Referer'));
            if ($referer === '') {
                return false;
            }

            $parts = parse_url($referer);
            if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
                return false;
            }

            $origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        }

        return \TTDF\Http\Rest\Auth::isSameOrigin($origin);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolve(): ?array
    {
        $token = \TTDF\Http\Rest\Auth::bearerToken();
        if ($token !== '') {
            return $this->fromToken($token);
        }

        return $this->fromCookies();
    }

    /**
     * Verify credentials without touching the session.
     *
     * @return array<string, mixed>|null
     */
    public function attempt(string $name, string $password): ?array
    {
        $uid = $this->authenticate($name, $password);
        if ($uid <= 0) {
            return null;
        }

        $user = $this->users->find($uid);
        if ($user === null) {
            return null;
        }

        $this->users->touchLogin($uid);

        return $user;
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public static function issueToken(array $user, int $ttl): array
    {
        return Token::issue((int) $user['uid'], (string) $user['authCode'], $ttl);
    }

    /**
     * @param array<string, mixed> $user
     */
    public static function startCookieSession(array $user, int $expire): void
    {
        Typecho::setCookie(self::COOKIE_UID, (string) $user['uid'], $expire);
        Typecho::setCookie(self::COOKIE_AUTH, Typecho::hash((string) $user['authCode']), $expire);
    }

    public static function clearCookieSession(): void
    {
        Typecho::deleteCookie(self::COOKIE_UID);
        Typecho::deleteCookie(self::COOKIE_AUTH);
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public static function identityPayload(array $user, string $source): array
    {
        return [
            'uid' => (int) $user['uid'],
            'name' => (string) $user['name'],
            'screenName' => (string) $user['screenName'],
            'mail' => (string) $user['mail'],
            'url' => Url::website($user['url'] ?? ''),
            'group' => (string) $user['group'],
            'authCode' => (string) $user['authCode'],
            'source' => $source,
        ];
    }

    /**
     * @param array<string, mixed> $identity
     * @return array<string, mixed>
     */
    public static function sessionProfile(array $identity): array
    {
        return [
            'uid' => (int) $identity['uid'],
            'name' => (string) $identity['name'],
            'screenName' => (string) $identity['screenName'],
            'url' => Url::website($identity['url'] ?? ''),
            'group' => (string) $identity['group'],
            'source' => (string) ($identity['source'] ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fromToken(string $token): array
    {
        $claims = Token::claims($token);
        if ($claims === null) {
            Response::error('Invalid token', 401);
        }

        $user = $this->users->find($claims['uid']);
        if ($user === null) {
            Response::error('Invalid token', 401);
        }

        if (!Token::verify($claims, (string) $user['authCode'])) {
            Response::error('Invalid token', 401);
        }

        if (Token::expired($claims)) {
            Response::error('Token expired', 401);
        }

        return self::identityPayload($user, 'token');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fromCookies(): ?array
    {
        $uid = (int) Typecho::cookie(self::COOKIE_UID);
        $hash = Typecho::cookie(self::COOKIE_AUTH);
        if ($uid <= 0 || $hash === '') {
            return null;
        }

        $user = $this->users->find($uid);
        if ($user === null || !Typecho::hashValidate((string) $user['authCode'], $hash)) {
            return null;
        }

        return self::identityPayload($user, 'cookie');
    }

    private function authenticate(string $name, string $password): int
    {
        if ($name === '' || $password === '') {
            return 0;
        }

        if (class_exists('Widget\\User')) {
            try {
                $widget = \Widget\User::alloc();
                if ($widget->login($name, $password, true)) {
                    return (int) $widget->uid;
                }
            } catch (\Throwable $exception) {
                error_log('TTDF REST v2 login widget failed: ' . $exception->getMessage());
            }
        }

        $user = $this->users->findByIdentifier($name);
        if ($user === null) {
            return 0;
        }

        $hash = (string) $user['password'];

        // Typecho still ships phpass hashes for installed-before-1.2 accounts.
        if (str_starts_with($hash, '$P$')) {
            if (!class_exists('Utils\\PasswordHash')) {
                return 0;
            }

            $hasher = new \Utils\PasswordHash(8, true);

            return $hasher->checkPassword($password, $hash) ? (int) $user['uid'] : 0;
        }

        return Typecho::hashValidate($password, $hash) ? (int) $user['uid'] : 0;
    }
}
