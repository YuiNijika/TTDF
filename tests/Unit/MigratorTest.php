<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TTDF\Upgrade\Migrator;
use TTDF\Theme\Options;
use TTDF\Theme\Schema;

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
        foreach (glob($this->root . '/logs/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->root . '/logs');
        @rmdir($this->root);
    }

    public function testMigrationIsIdempotentAndCoercesTypes(): void
    {
        $store = new InMemoryOptionsStore([
            'Theme_SubTitle' => 'Legacy title',
            'Theme_RESTAPI_Switch' => 'true',
            '__schema_version' => '1',
        ], 1);
        $options = new Options($store, 'Theme');
        $migrator = new Migrator($options, $this->root);

        $first = $migrator->run();
        self::assertSame('migrated', $first['status']);
        self::assertTrue($options->get('RESTAPI_Switch'));
        self::assertSame('Legacy title', $options->get('SubTitle'));

        $second = $migrator->run();
        self::assertSame('already-current', $second['status']);
    }

    public function testOtherThemeRowsAreLeftUntouched(): void
    {
        $store = new InMemoryOptionsStore([
            'Theme_SubTitle' => 'Legacy title',
            'Other_SubTitle' => 'plain legacy value',
            '__schema_version' => '1',
        ], 1);
        $options = new Options($store, 'Theme');

        (new Migrator($options, $this->root))->run();

        self::assertSame('"Legacy title"', $store->get('Theme_SubTitle'));
        self::assertSame('plain legacy value', $store->get('Other_SubTitle'));
    }

    public function testBackupOnlyContainsCurrentThemeRows(): void
    {
        $store = new InMemoryOptionsStore([
            'Theme_SubTitle' => 'Legacy title',
            'Other_SubTitle' => 'other theme secret',
            '__schema_version' => '1',
        ], 1);
        $options = new Options($store, 'Theme');

        $result = (new Migrator($options, $this->root))->run();

        self::assertNotNull($result['backup']);
        $backup = (string) file_get_contents((string) $result['backup']);
        self::assertStringContainsString('Theme_SubTitle', $backup);
        self::assertStringNotContainsString('Other_SubTitle', $backup);
        self::assertStringNotContainsString('other theme secret', $backup);
    }
}
