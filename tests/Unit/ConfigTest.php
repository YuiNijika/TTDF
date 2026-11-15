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
        putenv('TTDF_REST_TOKEN_ENABLED');
        putenv('TTDF_REST_CORS_ORIGINS');
        putenv('TTDF_SETTINGS_TABLE');
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
        putenv('TTDF_REST_TOKEN_ENABLED=true');
        putenv('TTDF_REST_CORS_ORIGINS=https://a.test,https://b.test');

        $config = ConfigLoader::load(dirname(__DIR__, 2));

        self::assertTrue($config['debug']);
        self::assertTrue($config['rest']['enabled']);
        self::assertTrue($config['rest']['token_enabled']);
        self::assertSame(['https://a.test', 'https://b.test'], $config['rest']['cors']['allowed_origins']);
        self::assertSame('router', $config['router']['pages_dir']);
    }

    public function testTokenEnforcementIsDisabledByDefault(): void
    {
        $config = ConfigLoader::load(dirname(__DIR__, 2));

        self::assertFalse($config['rest']['token_enabled']);
    }

    public function testLegacyAppConfigPathIsNoLongerAccepted(): void
    {
        $root = sys_get_temp_dir() . '/ttdf-config-' . uniqid('', true);
        mkdir($root . '/app', 0777, true);
        file_put_contents($root . '/app/app.config.php', '<?php return [];');

        try {
            $this->expectException(\RuntimeException::class);
            ConfigLoader::load($root);
        } finally {
            @unlink($root . '/app/app.config.php');
            @rmdir($root . '/app');
            @rmdir($root);
        }
    }

    public function testRouterPagesDirectoryMustStayRelativeToApp(): void
    {
        $root = sys_get_temp_dir() . '/ttdf-config-' . uniqid('', true);
        mkdir($root . '/app/config', 0777, true);
        file_put_contents($root . '/app/config/app.php', <<<'PHP'
<?php

return [
    'router' => ['pages_dir' => '../outside'],
];
PHP);

        try {
            $this->expectException(\RuntimeException::class);
            ConfigLoader::load($root);
        } finally {
            @unlink($root . '/app/config/app.php');
            @rmdir($root . '/app/config');
            @rmdir($root . '/app');
            @rmdir($root);
        }
    }

    public function testRouterPagesDirectoryRejectsAppPrefix(): void
    {
        foreach (['app', 'app/pages'] as $pagesDir) {
            $root = sys_get_temp_dir() . '/ttdf-config-' . uniqid('', true);
            mkdir($root . '/app/config', 0777, true);
            file_put_contents(
                $root . '/app/config/app.php',
                "<?php\n\nreturn ['router' => ['pages_dir' => '" . $pagesDir . "']];\n"
            );

            try {
                ConfigLoader::load($root);
                self::fail('Expected pages_dir to be rejected: ' . $pagesDir);
            } catch (\RuntimeException $exception) {
                self::assertStringContainsString('pages_dir', $exception->getMessage());
            } finally {
                @unlink($root . '/app/config/app.php');
                @rmdir($root . '/app/config');
                @rmdir($root . '/app');
                @rmdir($root);
            }
        }
    }

    public function testPublicConfigDropsVersionAndAssetsAndKeepsHiddenSettingsDefaults(): void
    {
        $root = sys_get_temp_dir() . '/ttdf-config-' . uniqid('', true);
        mkdir($root . '/app/config', 0777, true);
        file_put_contents($root . '/app/config/app.php', <<<'PHP'
<?php

return [
    'version' => '9.9.9',
    'debug' => true,
    'assets' => ['cdn' => 'https://cdn.invalid.test'],
];
PHP);

        try {
            $config = ConfigLoader::load($root);

            self::assertArrayNotHasKey('version', $config);
            self::assertArrayNotHasKey('assets', $config);
            self::assertSame('ttdf', $config['settings']['table']);
            self::assertSame(2, $config['settings']['schema_version']);
            self::assertTrue($config['debug']);
        } finally {
            @unlink($root . '/app/config/app.php');
            @rmdir($root . '/app/config');
            @rmdir($root . '/app');
            @rmdir($root);
        }
    }

    public function testSettingsTableEnvironmentOverrideStillWorks(): void
    {
        putenv('TTDF_SETTINGS_TABLE=ttdf_custom');

        $config = ConfigLoader::load(dirname(__DIR__, 2));

        self::assertSame('ttdf_custom', $config['settings']['table']);
        self::assertArrayNotHasKey('version', $config);
        self::assertArrayNotHasKey('assets', $config);
    }
}
