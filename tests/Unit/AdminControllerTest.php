<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TTDF\Config\Config;
use TTDF\Http\Request;
use TTDF\Http\Response;
use TTDF\Http\ResponseException;
use TTDF\Http\Rest\AdminController;
use TTDF\Theme\Options;
use TTDF\Theme\Schema;
use Typecho\Db;
use Typecho\Db\Query;

final class AdminControllerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/ttdf-admin-' . uniqid('', true);
        mkdir($this->root . '/logs', 0777, true);

        $_SERVER['CONTENT_LENGTH'] = '0';
        $_GET = [];
        Config::load([]);
        Options::reset();
        Schema::reset();
        Db::reset();
        \Widget\User::reset();
        Response::capture(true);
        Request::resetBody();

        Db::$fetcher = static function (Query $query): array {
            if ($query->table !== 'table.ttdf') {
                return [];
            }

            $name = (string) ($query->wheres[0]['values'][0] ?? '');
            if ($name === '' && in_array('name', $query->fields, true)) {
                return [[
                    'name' => 'Theme',
                    'value' => '{"SubTitle":"Hello"}',
                ]];
            }

            return $name === 'Theme'
                ? [['name' => 'Theme', 'value' => '{"SubTitle":"Hello"}']]
                : [];
        };
    }

    protected function tearDown(): void
    {
        Response::capture(false);
        Request::resetBody();
        Config::reset();
        Options::reset();
        Schema::reset();
        Db::reset();
        \Widget\User::reset();
        @rmdir($this->root . '/logs');
        @rmdir($this->root);
        $_SERVER = [];
        $_GET = [];
    }

    public function testOptionsGetReturnsSchemaDefaults(): void
    {
        $payload = $this->capture(static fn () => (new AdminController(sys_get_temp_dir()))->optionsGet())->payload();

        self::assertSame(200, $payload['code']);
        self::assertSame('Hello', $payload['data']['SubTitle']);
        self::assertSame('', $payload['data']['FooterText']);
    }

    public function testOptionsPostSavesOnlyWhitelistedFields(): void
    {
        $this->body('{"SubTitle":"New","Unknown":"ignored"}');

        $payload = $this->capture(fn () => (new AdminController($this->root))->optionsPost())->payload();

        self::assertSame(1, $payload['data']['saved']);
        self::assertSame('设置已更新', $payload['data']['message']);
        self::assertSame('New', Options::instance()->get('SubTitle'));
        self::assertNull(Options::instance()->get('Unknown'));
    }

    public function testOptionsPostRejectsPayloadWithoutKnownFields(): void
    {
        $this->body('{"Unknown":"ignored"}');

        $exception = $this->capture(fn () => (new AdminController($this->root))->optionsPost());

        self::assertSame(422, $exception->status());
        self::assertSame('No valid settings were provided', $exception->payload()['message']);
    }

    public function testImportSavesWhitelistedSettings(): void
    {
        $this->body('{"settings":{"SubTitle":"Imported","Unknown":"ignored"}}');

        $payload = $this->capture(fn () => (new AdminController($this->root))->import())->payload();

        self::assertSame(1, $payload['data']['imported']);
        self::assertSame('Imported', Options::instance()->get('SubTitle'));
    }

    public function testImportRequiresSettings(): void
    {
        $this->body('{}');

        $exception = $this->capture(fn () => (new AdminController($this->root))->import());

        self::assertSame(422, $exception->status());
        self::assertSame('Missing settings', $exception->payload()['message']);
    }

    public function testImportRejectsPayloadWithoutKnownFields(): void
    {
        $this->body('{"settings":{"Unknown":"ignored"}}');

        $exception = $this->capture(fn () => (new AdminController($this->root))->import());

        self::assertSame(422, $exception->status());
        self::assertSame('No valid settings were provided', $exception->payload()['message']);
    }

    public function testMigrateIsIdempotent(): void
    {
        $payload = $this->capture(fn () => (new AdminController($this->root))->migrate())->payload();

        self::assertSame('already-current', $payload['data']['status']);
        self::assertSame(0, $payload['data']['migrated']);
        self::assertNull($payload['data']['backup']);
    }

    public function testSchemaConfigAndThemeInfoExposeFrameworkData(): void
    {
        $schema = $this->capture(fn () => (new AdminController($this->root))->schema())->payload();
        $config = $this->capture(fn () => (new AdminController($this->root))->config())->payload();
        $info = $this->capture(fn () => (new AdminController($this->root))->themeInfo())->payload();

        self::assertArrayHasKey('General', $schema['data']['tabs']);
        self::assertArrayHasKey('SubTitle', $schema['data']['fields']);
        self::assertArrayHasKey('General', $config['data']['tabs']);
        self::assertSame('5.0.3-next', $config['data']['frameworkVersion']);
        self::assertSame('Theme', $info['data']['themeName']);
        self::assertSame('5.0.3-next', $info['data']['ttdfVersion']);
    }

    public function testExportIncludesThemeMetadataAndValues(): void
    {
        $payload = $this->capture(fn () => (new AdminController($this->root))->export())->payload();

        self::assertSame('Theme', $payload['data']['meta']['theme']);
        self::assertSame('5.0.3-next', $payload['data']['meta']['frameworkVersion']);
        self::assertSame('Hello', $payload['data']['settings']['SubTitle']);
    }

    public function testDiagnosticsDoesNotExposeTokensOrSecrets(): void
    {
        $token = str_repeat('s', 32);
        putenv('TTDF_REST_TOKEN=' . $token);

        $payload = $this->capture(fn () => (new AdminController($this->root))->diagnostics())->payload();
        $encoded = json_encode($payload, JSON_THROW_ON_ERROR);

        self::assertSame(PHP_VERSION, $payload['data']['phpVersion']);
        self::assertSame('json-document', $payload['data']['settingsStorage']);
        self::assertSame('ttdf-table', $payload['data']['settingsDriver']);
        self::assertSame('ttdf', $payload['data']['settingsTable']);
        self::assertStringNotContainsString($token, $encoded);
        self::assertStringNotContainsString('TTDF_REST_TOKEN', $encoded);

        putenv('TTDF_REST_TOKEN');
    }

    private function body(string $body): void
    {
        Request::setBody($body);
        $_SERVER['CONTENT_LENGTH'] = (string) strlen($body);
    }

    /**
     * @param callable(): void $callback
     */
    private function capture(callable $callback): ResponseException
    {
        try {
            $callback();
        } catch (ResponseException $exception) {
            return $exception;
        }

        self::fail('Expected a captured response');
    }
}
