<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TTDF\Config\Config;
use TTDF\Config\ConfigLoader;

final class ConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        Config::reset();
        putenv('TTDF_DEBUG');
        putenv('TTDF_REST_ENABLED');
        putenv('TTDF_REST_CORS_ORIGINS');
    }

    public function testDotAccessAndDefaults(): void
    {
        Config::load(['rest' => ['enabled' => true]]);

        self::assertTrue(Config::get('rest.enabled'));
        self::assertSame('fallback', Config::get('rest.missing', 'fallback'));
        self::assertTrue(Config::has('rest.enabled'));
        self::assertFalse(Config::has('rest.missing'));
    }

    public function testEnvironmentOverrides(): void
    {
        putenv('TTDF_DEBUG=1');
        putenv('TTDF_REST_ENABLED=true');
        putenv('TTDF_REST_CORS_ORIGINS=https://a.test,https://b.test');

        $config = ConfigLoader::load(dirname(__DIR__, 2));

        self::assertTrue($config['debug']);
        self::assertTrue($config['rest']['enabled']);
        self::assertSame(['https://a.test', 'https://b.test'], $config['rest']['cors']['allowed_origins']);
    }
}
