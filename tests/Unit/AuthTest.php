<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TTDF\Config\Config;
use TTDF\Http\Response;
use TTDF\Http\ResponseException;
use TTDF\Http\Rest\Auth;
use TTDF\Theme\Options;
use TTDF\Theme\ThemeConfig;

final class AuthTest extends TestCase
{
    private const ENV_NAME = 'TTDF_TEST_REST_TOKEN';

    protected function setUp(): void
    {
        Config::load([
            'rest' => [
                'enabled' => true,
                'token_enabled' => false,
            ],
        ]);
        Options::reset();
        ThemeConfig::reset();
        Response::capture(true);
        putenv(self::ENV_NAME);
        $_SERVER = [];
    }

    protected function tearDown(): void
    {
        Response::capture(false);
        Config::reset();
        Options::reset();
        ThemeConfig::reset();
        putenv(self::ENV_NAME);
        $_SERVER = [];
    }

    public function testSameOriginComparisonUsesTheConfiguredSiteUrl(): void
    {
        self::assertTrue(Auth::isSameOrigin(''));
        self::assertTrue(Auth::isSameOrigin('https://example.test'));
        self::assertFalse(Auth::isSameOrigin('https://example.test.evil'));
        self::assertFalse(Auth::isSameOrigin('http://example.test'));
        self::assertFalse(Auth::isSameOrigin('https://evil.test'));
    }

    public function testTokenIsIgnoredWhenSwitchIsDisabled(): void
    {
        $this->configure([
            'REST_Token_Switch' => false,
            'REST_Token_Env' => self::ENV_NAME,
        ]);
        putenv(self::ENV_NAME . '=' . str_repeat('a', 64));

        Auth::requirePublicAccess();

        self::assertSame('', Auth::bearerToken());
    }

    public function testTokenIsRequiredWhenSwitchIsEnabled(): void
    {
        $token = str_repeat('b', 64);
        $this->configure([
            'REST_Token_Switch' => true,
            'REST_Token_Env' => self::ENV_NAME,
        ]);
        putenv(self::ENV_NAME . '=' . $token);

        $exception = $this->capture(static fn () => Auth::requirePublicAccess());

        self::assertSame(401, $exception->status());
        self::assertSame('Invalid token', $exception->payload()['message']);
    }

    public function testValidBearerTokenPasses(): void
    {
        $token = str_repeat('c', 64);
        $this->configure([
            'REST_Token_Switch' => true,
            'REST_Token_Env' => self::ENV_NAME,
        ]);
        putenv(self::ENV_NAME . '=' . $token);
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

        Auth::requirePublicAccess();

        self::assertSame($token, Auth::bearerToken());
    }

    public function testEnabledSwitchWithoutEnvironmentValueIsMisconfigured(): void
    {
        $this->configure([
            'REST_Token_Switch' => true,
            'REST_Token_Env' => self::ENV_NAME,
        ]);

        $exception = $this->capture(static fn () => Auth::requirePublicAccess());

        self::assertSame(500, $exception->status());
        self::assertSame('REST token is misconfigured', $exception->payload()['message']);
    }

    public function testDisabledPublicApiReturns404BeforeTokenChecks(): void
    {
        Config::load([
            'rest' => [
                'enabled' => false,
                'token_enabled' => true,
            ],
        ]);
        ThemeConfig::reset();

        $exception = $this->capture(static fn () => Auth::requirePublicAccess());

        self::assertSame(404, $exception->status());
        self::assertSame('REST API is disabled', $exception->payload()['message']);
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function configure(array $settings): void
    {
        $data = [];
        foreach ($settings as $name => $value) {
            $data['Theme_' . $name] = (string) json_encode($value, JSON_THROW_ON_ERROR);
        }

        $property = new \ReflectionProperty(Options::class, 'instance');
        $property->setValue(null, new Options(new InMemoryOptionsStore($data), 'Theme'));
        ThemeConfig::reset();
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
