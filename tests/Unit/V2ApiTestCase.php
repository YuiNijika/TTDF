<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TTDF\Compat\Typecho;
use TTDF\Config\Config;
use TTDF\Hooks\Hooks;
use TTDF\Http\Request;
use TTDF\Http\Response;
use TTDF\Http\ResponseException;
use TTDF\Http\Rest\V2\Auth as V2Auth;
use TTDF\Http\Rest\V2\Router as V2Router;
use TTDF\Theme\Options;

require_once __DIR__ . '/V2FakeDb.php';

/**
 * Shared fixture for the v2 API tests.
 *
 * Each request is dispatched through the real router with a fresh identity
 * cache, mirroring one PHP request per call while still allowing a test to
 * walk a login, a read and a logout inside a single case.
 */
abstract class V2ApiTestCase extends TestCase
{
    protected string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);

        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/ttdf/v2/site',
            'REMOTE_ADDR' => '203.0.113.9',
            'HTTP_USER_AGENT' => 'TTDF v2 tests',
        ];
        $_GET = [];
        $_POST = [];
        $_FILES = [];

        Request::resetBody();
        Response::capture(true);

        Hooks::reset();
        Typecho::resetRoutes();
        \Typecho\Router::reset();
        \Typecho\Db::reset();
        \Typecho\Cookie::reset();
        \Widget\User::reset();
        \Helper::$optionsOverride = null;
        V2Auth::reset();

        Config::load([
            'rest' => [
                'enabled' => true,
                'v1_prefix' => 'ttdf/v1',
                'v2_enabled' => true,
                'v2_prefix' => 'ttdf/v2',
            ],
        ]);
        Options::reset();

        V2FakeDb::install($this->tables());
    }

    protected function tearDown(): void
    {
        Response::capture(false);
        Hooks::reset();
        Config::reset();
        Options::reset();
        Typecho::resetRoutes();
        \Typecho\Router::reset();
        \Typecho\Db::reset();
        \Typecho\Cookie::reset();
        \Widget\User::reset();
        \Helper::$optionsOverride = null;
        V2Auth::reset();

        $_SERVER = [];
        $_GET = [];
        $_POST = [];
        $_FILES = [];
        Request::resetBody();
    }

    protected function disableV2(): void
    {
        Config::load([
            'rest' => [
                'enabled' => false,
                'v1_prefix' => 'ttdf/v1',
                'v2_enabled' => false,
                'v2_prefix' => 'ttdf/v2',
            ],
        ]);
        Options::reset();
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    protected function tables(): array
    {
        return [
            'table.users' => [
                [
                    'uid' => 1,
                    'name' => 'admin',
                    'password' => Typecho::hash('secret'),
                    'mail' => 'admin@example.test',
                    'url' => 'https://example.test/',
                    'screenName' => '管理员',
                    'created' => 1_700_000_000,
                    'activated' => 1_700_000_000,
                    'logged' => 0,
                    'group' => 'administrator',
                    'authCode' => 'authcode-for-admin',
                ],
                [
                    'uid' => 2,
                    'name' => 'editor',
                    'password' => Typecho::hash('secret'),
                    'mail' => 'editor@example.test',
                    'url' => '',
                    'screenName' => '编辑',
                    'created' => 1_700_000_100,
                    'activated' => 1_700_000_100,
                    'logged' => 0,
                    'group' => 'editor',
                    'authCode' => 'authcode-for-editor',
                ],
                [
                    'uid' => 3,
                    'name' => 'reader',
                    'password' => Typecho::hash('secret'),
                    'mail' => 'reader@example.test',
                    'url' => '',
                    'screenName' => '读者',
                    'created' => 1_700_000_200,
                    'activated' => 1_700_000_200,
                    'logged' => 0,
                    'group' => 'subscriber',
                    'authCode' => 'authcode-for-reader',
                ],
            ],
            'table.contents' => [
                [
                    'cid' => 10,
                    'title' => 'Hello world',
                    'slug' => 'hello-world',
                    'type' => 'post',
                    'status' => 'publish',
                    'created' => 1_700_001_000,
                    'modified' => 1_700_001_000,
                    'text' => 'Body',
                    'authorId' => 1,
                    'commentsNum' => 0,
                    'password' => '',
                    'template' => '',
                    'order' => 0,
                    'allowComment' => 1,
                    'allowPing' => 1,
                    'allowFeed' => 1,
                    'parent' => 0,
                ],
                [
                    'cid' => 11,
                    'title' => 'About',
                    'slug' => 'about',
                    'type' => 'page',
                    'status' => 'publish',
                    'created' => 1_700_001_100,
                    'modified' => 1_700_001_100,
                    'text' => 'Page body',
                    'authorId' => 1,
                    'commentsNum' => 0,
                    'password' => '',
                    'template' => '',
                    'order' => 0,
                    'allowComment' => 1,
                    'allowPing' => 1,
                    'allowFeed' => 1,
                    'parent' => 0,
                ],
            ],
            'table.metas' => [
                [
                    'mid' => 20,
                    'name' => '默认分类',
                    'slug' => 'default',
                    'type' => 'category',
                    'description' => '',
                    'count' => 1,
                    'order' => 0,
                    'parent' => 0,
                ],
            ],
            'table.relationships' => [
                ['cid' => 10, 'mid' => 20],
            ],
            'table.comments' => [],
            'table.fields' => [],
            'table.options' => [
                ['name' => 'title', 'user' => 0, 'value' => 'Example'],
                ['name' => 'description', 'user' => 0, 'value' => 'Example site'],
                ['name' => 'commentsThreaded', 'user' => 0, 'value' => '1'],
                ['name' => 'commentsMaxNestingLevels', 'user' => 0, 'value' => '5'],
            ],
        ];
    }

    /**
     * Dispatch a request through the v2 router as one fresh HTTP request.
     *
     * @param array<string, mixed>|null $body
     * @param array<string, string> $headers
     */
    protected function dispatch(
        string $uri,
        string $method = 'GET',
        ?array $body = null,
        array $headers = []
    ): ResponseException {
        return $this->dispatchAt('ttdf/v2/' . ltrim($uri, '/'), $method, $body, $headers);
    }

    /**
     * Dispatch a request at an explicit path, used by custom prefix tests.
     *
     * @param array<string, mixed>|null $body
     * @param array<string, string> $headers
     */
    protected function dispatchAt(
        string $path,
        string $method = 'GET',
        ?array $body = null,
        array $headers = []
    ): ResponseException {
        $_SERVER['REQUEST_URI'] = '/' . ltrim($path, '/');
        $_SERVER['REQUEST_METHOD'] = strtoupper($method);

        $_GET = [];
        $query = parse_url($path, PHP_URL_QUERY);
        if (is_string($query)) {
            parse_str($query, $parsed);
            $_GET = $parsed;
        }

        foreach (['HTTP_AUTHORIZATION', 'HTTP_ORIGIN', 'HTTP_REFERER', 'HTTP_X_TYPECHO_TOKEN'] as $header) {
            unset($_SERVER[$header]);
        }
        foreach ($headers as $name => $value) {
            $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }

        Request::setBody($body === null ? null : (string) json_encode($body));
        V2Auth::reset();

        return $this->capture(fn () => V2Router::handle($this->root));
    }

    protected function capture(callable $callback): ResponseException
    {
        try {
            $callback();
        } catch (ResponseException $exception) {
            return $exception;
        }

        self::fail('Expected a captured response');
    }

    /**
     * Sign the browser in the same way `POST /auth/login` does.
     */
    protected function signInWithCookie(int $uid): void
    {
        $user = V2FakeDb::row('table.users', $uid);
        self::assertNotNull($user, 'The seeded user must exist');

        \Typecho\Cookie::$values['__typecho_uid'] = (string) $uid;
        \Typecho\Cookie::$values['__typecho_authCode'] = Typecho::hash((string) $user['authCode']);
    }

    /**
     * Add or replace site options in the in-memory database.
     *
     * @param array<string, string> $options
     */
    protected function seedOptions(array $options): void
    {
        $tables = V2FakeDb::tables();

        foreach ($tables['table.options'] as $index => $row) {
            $name = (string) $row['name'];
            if (!array_key_exists($name, $options)) {
                continue;
            }

            $tables['table.options'][$index]['value'] = $options[$name];
            unset($options[$name]);
        }

        foreach ($options as $name => $value) {
            $tables['table.options'][] = [
                'name' => (string) $name,
                'user' => 0,
                'value' => (string) $value,
            ];
        }

        V2FakeDb::install($tables);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    protected function seedRows(string $table, array $rows): void
    {
        $tables = V2FakeDb::tables();
        foreach ($rows as $row) {
            $tables[$table][] = $row;
        }

        V2FakeDb::install($tables);
    }

    /**
     * Comment moderation reads Typecho's option object, not the options table.
     *
     * @param array<string, mixed> $values
     */
    protected function overrideSiteOptions(array $values): void
    {
        $options = \Helper::options();
        foreach ($values as $name => $value) {
            $options->{$name} = $value;
        }

        \Helper::$optionsOverride = $options;
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function assertSuccess(int $expectedStatus, array $payload): void
    {
        self::assertSame($expectedStatus, $payload['code']);
        self::assertSame('success', $payload['message']);
    }
}
