<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TTDF\Theme\Options;
use TTDF\Theme\Schema;
use TTDF\Upgrade\Migrator;

final class MigratorTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        Schema::reset();
        $this->root = sys_get_temp_dir() . '/ttdf-migrator-' . uniqid('', true);
        mkdir($this->root . '/app/config', 0777, true);
        file_put_contents($this->root . '/app/config/theme.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'General' => [
        'title' => 'General',
        'fields' => [
            ['type' => 'Text', 'name' => 'SubTitle', 'value' => ''],
            ['type' => 'Switch', 'name' => 'RESTAPI_Switch', 'value' => false],
            [
                'type' => 'TemplateMap',
                'name' => 'Templates',
                'value' => ['post' => 'Post', '404' => 'NotFound'],
                'components' => ['Post', 'Page', 'NotFound'],
            ],
            ['type' => 'Text', 'name' => 'Logo', 'value' => '', 'media' => true],
            ['type' => 'Text', 'name' => 'Slogan', 'value' => '', 'aliases' => ['sloganText']],
            ['type' => 'Number', 'name' => 'Width', 'value' => 720],
        ],
    ],
];
PHP);
    }

    protected function tearDown(): void
    {
        Schema::reset();
        @unlink($this->root . '/app/config/theme.php');
        @rmdir($this->root . '/app/config');
        @rmdir($this->root . '/app');
        @rmdir($this->root);
    }

    public function testMigrationWritesOneJsonDocumentAndIsIdempotent(): void
    {
        $store = new InMemoryOptionsStore([
            'Theme_SubTitle' => 'Legacy title',
            'Theme_RESTAPI_Switch' => 'true',
        ]);
        $options = new Options($store, 'Theme');

        $first = (new Migrator($options, $this->root))->run();

        self::assertSame('migrated', $first['status']);
        self::assertSame(2, $first['migrated']);
        self::assertSame('Legacy title', $options->get('SubTitle'));
        self::assertTrue($options->get('RESTAPI_Switch'));
        self::assertSame('Legacy title', $store->get('Theme_SubTitle'));
        self::assertSame(
            ['SubTitle' => 'Legacy title', 'RESTAPI_Switch' => true],
            json_decode((string) $store->get('Theme'), true, 512, JSON_THROW_ON_ERROR)
        );

        $second = (new Migrator($options, $this->root))->run();
        self::assertSame('already-current', $second['status']);
        self::assertSame(0, $second['migrated']);
    }

    public function testExistingJsonDocumentIsAlreadyCurrentAndLegacyRowsStayUntouched(): void
    {
        $store = new InMemoryOptionsStore([
            'Theme' => '{"SubTitle":"Current title"}',
            'Theme_SubTitle' => 'Legacy title',
            'Theme_RESTAPI_Switch' => 'true',
        ]);
        $options = new Options($store, 'Theme');

        $result = (new Migrator($options, $this->root))->run();

        self::assertSame('already-current', $result['status']);
        self::assertSame(0, $result['migrated']);
        self::assertNull($result['backup']);
        self::assertSame('Current title', $options->get('SubTitle'));
        self::assertNull($options->get('RESTAPI_Switch'));
        self::assertSame('{"SubTitle":"Current title"}', $store->get('Theme'));
        self::assertSame('true', $store->get('Theme_RESTAPI_Switch'));
        self::assertSame([], $options->backups());
    }

    public function testOtherThemeRowsAreLeftUntouched(): void
    {
        $store = new InMemoryOptionsStore([
            'Theme_SubTitle' => 'Legacy title',
            'Other_SubTitle' => 'plain legacy value',
            'Other' => '{"SubTitle":"Other JSON"}',
        ]);
        $options = new Options($store, 'Theme');

        (new Migrator($options, $this->root))->run();

        self::assertSame('plain legacy value', $store->get('Other_SubTitle'));
        self::assertSame('{"SubTitle":"Other JSON"}', $store->get('Other'));
        self::assertSame('{"SubTitle":"Legacy title"}', $store->get('Theme'));
    }

    public function testUnknownRowsAreIgnored(): void
    {
        $store = new InMemoryOptionsStore([
            'Theme_SubTitle' => 'Legacy title',
            'Theme_UnknownField' => 'sensitive legacy value',
            '__schema_version_Theme' => '1',
        ]);
        $options = new Options($store, 'Theme');

        (new Migrator($options, $this->root))->run();

        self::assertSame('sensitive legacy value', $store->get('Theme_UnknownField'));
        self::assertSame('1', $store->get('__schema_version_Theme'));
        self::assertSame(['SubTitle' => 'Legacy title'], $options->all());
    }

    public function testMigrationUsesSharedSchemaNormalization(): void
    {
        $store = new InMemoryOptionsStore([
            'Theme_Templates' => '{"post":"Post","tag":"Post","404":"NotFound"}',
            'Theme_Logo' => 'javascript:alert(1)',
            'Theme_Width' => '720',
        ]);
        $options = new Options($store, 'Theme');

        (new Migrator($options, $this->root))->run();

        self::assertSame(
            ['post' => 'Post', 'tag' => 'Post', '404' => 'NotFound'],
            $options->get('Templates')
        );
        self::assertSame('', $options->get('Logo'));
        self::assertSame(720, $options->get('Width'));
    }

    public function testLegacyUnprefixedRowsAreCopiedIntoTheThemeDocument(): void
    {
        $store = new InMemoryOptionsStore([
            'SubTitle' => 'Global legacy title',
            'Theme_SubTitle' => 'Theme title',
        ]);
        $options = new Options($store, 'Theme');

        (new Migrator($options, $this->root))->run();

        self::assertSame('Theme title', $options->get('SubTitle'));
        self::assertSame('Global legacy title', $store->get('SubTitle'));
    }

    public function testDeclaredAliasesAreMigratedUnderTheCurrentFieldName(): void
    {
        $store = new InMemoryOptionsStore([
            'Theme_sloganText' => 'Renamed legacy value',
            'Theme_Logo' => '12',
        ]);
        $options = new Options($store, 'Theme');

        $result = (new Migrator($options, $this->root))->run();

        self::assertSame('migrated', $result['status']);
        self::assertSame('Renamed legacy value', $options->get('Slogan'));
        self::assertNull($options->get('sloganText'));
        self::assertSame('Renamed legacy value', $store->get('Theme_sloganText'));
    }

    public function testCurrentFieldNameWinsOverItsAlias(): void
    {
        $store = new InMemoryOptionsStore([
            'Theme_sloganText' => 'Alias value',
            'Theme_Slogan' => 'Current value',
            'sloganText' => 'Unprefixed alias value',
        ]);
        $options = new Options($store, 'Theme');

        (new Migrator($options, $this->root))->run();

        self::assertSame('Current value', $options->get('Slogan'));
    }

    public function testAliasNamesAreNotPersistedAsKeys(): void
    {
        $store = new InMemoryOptionsStore([
            'Theme_sloganText' => 'Alias value',
        ]);
        $options = new Options($store, 'Theme');

        (new Migrator($options, $this->root))->run();

        self::assertSame(
            ['Slogan' => 'Alias value'],
            json_decode((string) $store->get('Theme'), true, 512, JSON_THROW_ON_ERROR)
        );
        self::assertNull($options->get('sloganText'));
    }
}
