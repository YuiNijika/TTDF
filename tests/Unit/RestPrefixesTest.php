<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use TTDF\Config\Config;
use TTDF\Http\Rest\Prefixes;
use TTDF\Http\Rest\Router as V1Router;
use TTDF\Http\Rest\V2\Router as V2Router;

require_once __DIR__ . '/V2ApiTestCase.php';

final class RestPrefixesTest extends V2ApiTestCase
{
    public function testLegacyStringPrefixStillConfiguresV1(): void
    {
        Config::load(['rest' => ['enabled' => true, 'prefix' => 'legacy/api']]);

        self::assertSame('legacy/api', Prefixes::v1());
        self::assertSame('ttdf/v2', Prefixes::v2());
    }

    public function testExtraPrefixCanMountTheV2Api(): void
    {
        $this->loadPrefixes([
            'mobile' => 'v2',
        ]);

        $payload = $this->dispatchAt('mobile/site')->payload();

        $this->assertSuccess(200, $payload);
        self::assertSame('mobile', $payload['data']['restPrefix']);
    }

    public function testExtraPrefixCanMountTheV1Api(): void
    {
        $this->loadPrefixes([
            'legacy' => 'v1',
        ], true);

        $_SERVER['REQUEST_URI'] = '/legacy/site';
        self::assertTrue(V1Router::isRequest());

        $payload = $this->captureV1()->payload();

        $this->assertSuccess(200, $payload);
        self::assertSame('legacy', $payload['data']['restPrefix']);
    }

    public function testLongestExtraPrefixWins(): void
    {
        $this->loadPrefixes([
            'api' => 'v2',
            'api/mobile' => 'v2',
        ]);

        $payload = $this->dispatchAt('api/mobile/site')->payload();

        $this->assertSuccess(200, $payload);
        self::assertSame('api/mobile', $payload['data']['restPrefix']);
        self::assertSame('api/mobile', Prefixes::match(Prefixes::V2));
    }

    public function testInvalidAndDisabledMountsAreIgnored(): void
    {
        $this->loadPrefixes([
            'api' => 'v3',
            'bad prefix' => 'v2',
            '../escape' => 'v2',
            'off' => ['api' => 'v2', 'enabled' => false],
            'ok' => ['api' => 'v2'],
        ]);

        $prefixes = Prefixes::strings();

        self::assertContains('ttdf/v1', $prefixes);
        self::assertContains('ttdf/v2', $prefixes);
        self::assertContains('ok', $prefixes);
        self::assertNotContains('api', $prefixes);
        self::assertNotContains('bad prefix', $prefixes);
        self::assertNotContains('../escape', $prefixes);
        self::assertNotContains('off', $prefixes);
    }

    public function testExtraMountCannotShadowAPrimaryPrefix(): void
    {
        $this->loadPrefixes([
            'ttdf/v2' => 'v1',
        ]);

        $mounts = Prefixes::for(Prefixes::V1);
        self::assertSame(['ttdf/v1'], array_column($mounts, 'prefix'));
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function loadPrefixes(array $extra, bool $v1Enabled = true): void
    {
        Config::load([
            'rest' => [
                'enabled' => $v1Enabled,
                'v1_prefix' => 'ttdf/v1',
                'v2_enabled' => true,
                'v2_prefix' => 'ttdf/v2',
                'prefix' => $extra,
            ],
        ]);
        \TTDF\Theme\Options::reset();
    }

    private function captureV1(): \TTDF\Http\ResponseException
    {
        $_SERVER['REQUEST_URI'] = '/legacy/site';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        return $this->capture(fn () => V1Router::handle($this->root));
    }
}
