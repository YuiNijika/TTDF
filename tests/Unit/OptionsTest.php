<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TTDF\Theme\Options;

final class OptionsTest extends TestCase
{
    public function testThemeSettingsAreStoredAsOneJsonDocument(): void
    {
        $store = new InMemoryOptionsStore();
        $options = new Options($store, 'Theme');

        $options->merge([
            'SubTitle' => 'Hello',
            'RESTAPI_Switch' => true,
            'Templates' => ['post' => 'Post'],
        ]);

        self::assertSame('Hello', $options->get('SubTitle'));
        self::assertTrue($options->get('RESTAPI_Switch'));
        self::assertSame(['post' => 'Post'], $options->get('Templates'));
        self::assertSame([
            'SubTitle' => 'Hello',
            'RESTAPI_Switch' => true,
            'Templates' => ['post' => 'Post'],
        ], json_decode((string) $store->get('Theme'), true, 512, JSON_THROW_ON_ERROR));
        self::assertNull($store->get('Theme_SubTitle'));
        self::assertSame([], $options->backups());
    }

    public function testSettingsStoreNeverWritesTypechoNativeOptionsTable(): void
    {
        $path = dirname(__DIR__, 2) . '/vendor/yuinijika/ttdf/core/Theme/TypechoOptionsStore.php';
        $source = file_get_contents($path);

        self::assertIsString($source);
        self::assertStringNotContainsString("'table.options'", $source);
        self::assertStringNotContainsString('typecho_options', $source);
        self::assertStringContainsString("return 'table.' . \$this->table;", $source);
    }

    public function testEveryOverwriteBacksUpThePreviousJson(): void
    {
        $store = new InMemoryOptionsStore();
        $options = new Options($store, 'Theme');

        $options->set('SubTitle', 'First');
        $options->set('SubTitle', 'Second');

        $backups = $options->backups();
        self::assertCount(1, $backups);
        self::assertMatchesRegularExpression('/^Theme:backup_\d{10}$/', $backups[0]['key']);
        self::assertSame(['SubTitle' => 'First'], $backups[0]['settings']);
        self::assertSame('Second', $options->get('SubTitle'));
        self::assertSame($backups[0]['key'], $options->lastBackupKey());
    }

    public function testManualBackupUsesTheRequiredKeyFormat(): void
    {
        $store = new InMemoryOptionsStore();
        $options = new Options($store, 'Theme');
        $options->set('SubTitle', 'Hello');

        $key = $options->backup(1_700_000_000);

        self::assertSame('Theme:backup_1700000000', $key);
        self::assertSame('{"SubTitle":"Hello"}', $store->get('Theme:backup_1700000000'));
        self::assertSame('Theme:backup_', $options->backupPrefix());
    }

    public function testBackupCanBeRestored(): void
    {
        $store = new InMemoryOptionsStore();
        $options = new Options($store, 'Theme');
        $options->set('SubTitle', 'Old');
        $key = (string) $options->backup(1_700_000_001);
        $options->set('SubTitle', 'New');

        self::assertTrue($options->restore($key));
        self::assertSame('Old', $options->get('SubTitle'));
        self::assertFalse($options->restore('Other:backup_1700000001'));
        self::assertFalse($options->restore('Theme:backup_404'));
    }

    public function testAllOnlyReadsTheCurrentThemeJsonRow(): void
    {
        $store = new InMemoryOptionsStore([
            'Theme' => '{"SubTitle":"Hello","RESTAPI_Switch":true}',
            'Other' => '{"SubTitle":"Other theme","Secret":"do-not-leak"}',
            'Theme_SubTitle' => '"legacy-row"',
        ]);
        $options = new Options($store, 'Theme');

        self::assertSame([
            'SubTitle' => 'Hello',
            'RESTAPI_Switch' => true,
        ], $options->all());
        self::assertSame('fallback', $options->get('Missing', 'fallback'));
    }

    public function testExistsRequiresAValidJsonDocument(): void
    {
        $store = new InMemoryOptionsStore(['Theme' => '{invalid-json']);
        $options = new Options($store, 'Theme');

        self::assertFalse($options->exists());

        $store->set('Theme', '"legacy-scalar"');
        self::assertFalse($options->exists());

        $store->set('Theme', '{"SubTitle":"Hello"}');
        self::assertTrue($options->exists());
    }

    public function testExplicitEmptyStringIsDistinctFromMissingValue(): void
    {
        $store = new InMemoryOptionsStore(['Theme' => '{"REST_Token_Env":""}']);
        $options = new Options($store, 'Theme');

        self::assertSame('', $options->get('REST_Token_Env', null));
        self::assertNull($options->get('Missing', null));
    }

    public function testImportRejectsSpecialKeys(): void
    {
        $options = new Options(new InMemoryOptionsStore(), 'Theme');

        $this->expectException(InvalidArgumentException::class);
        $options->import(['__schema_version' => '9']);
    }

    public function testReplaceWritesOneDocumentAndPreservesBackupOfOldDocument(): void
    {
        $store = new InMemoryOptionsStore();
        $options = new Options($store, 'Theme');
        $options->set('SubTitle', 'Before');

        $options->replace(['SubTitle' => 'After']);

        self::assertSame(['SubTitle' => 'After'], $options->all());
        self::assertCount(1, $options->backups());
        self::assertSame(['SubTitle' => 'Before'], $options->backups()[0]['settings']);
    }

    public function testSetRequiresResolvedTheme(): void
    {
        $this->expectException(RuntimeException::class);

        (new Options(new InMemoryOptionsStore(), ''))->set('SubTitle', 'Hello');
    }

    public function testUnresolvedThemeNeverReadsOrListsRows(): void
    {
        $store = new InMemoryOptionsStore(['Theme' => '{"SubTitle":"theme"}']);
        $options = new Options($store, '');

        self::assertSame('fallback', $options->get('SubTitle', 'fallback'));
        self::assertSame([], $options->all());
        self::assertFalse($options->exists());
    }

    public function testDeleteRequiresResolvedTheme(): void
    {
        $this->expectException(RuntimeException::class);

        (new Options(new InMemoryOptionsStore(), ''))->delete('SubTitle');
    }

    public function testThemeNameWithHyphenUsesTheExactDocumentName(): void
    {
        $store = new InMemoryOptionsStore();
        $options = new Options($store, 'classic-22');

        $options->set('SubTitle', 'Hello');

        self::assertSame('{"SubTitle":"Hello"}', $store->get('classic-22'));
        self::assertSame(['SubTitle' => 'Hello'], $options->all());
    }

    public function testInstanceRefreshesThemeResolvedAfterBoot(): void
    {
        $helper = new \stdClass();
        $helper->theme = '';
        \Helper::$optionsOverride = $helper;
        Options::reset();

        try {
            $early = Options::instance();
            self::assertSame('', $early->themeName());

            $helper->theme = 'Theme';
            $late = Options::instance();
            self::assertSame('Theme', $late->themeName());
            self::assertNotSame($early, $late);
        } finally {
            \Helper::$optionsOverride = null;
            Options::reset();
        }
    }

    public function testBackupKeyFallsBackToTheCompactMarkerWhenTheFullNameDoesNotFit(): void
    {
        $store = new InMemoryOptionsStore([], 32, 'ttdf:');
        $options = new Options($store, 'classic-22');

        $options->set('SubTitle', 'First');
        $options->set('SubTitle', 'Second');

        $backups = $options->backups();
        self::assertCount(1, $backups);
        self::assertMatchesRegularExpression('/^ttdf:classic-22:b_\d{10}$/', $backups[0]['key']);
        self::assertTrue($options->isBackupKey($backups[0]['key']));
        self::assertSame('{"SubTitle":"First"}', $store->get($backups[0]['key']));
        self::assertSame('Second', $options->get('SubTitle'));

        self::assertTrue($options->restore($backups[0]['key']));
        self::assertSame('First', $options->get('SubTitle'));
    }

    public function testBackupsReadBothMarkerFormsAndIgnoreOtherThemes(): void
    {
        $store = new InMemoryOptionsStore([
            'Theme:backup_1700000000' => '{"SubTitle":"full"}',
            'Theme:b_1700000001' => '{"SubTitle":"compact"}',
            'Other:backup_1700000002' => '{"SubTitle":"other"}',
        ]);
        $options = new Options($store, 'Theme');

        $backups = $options->backups();

        self::assertSame(
            ['Theme:b_1700000001', 'Theme:backup_1700000000'],
            array_column($backups, 'key')
        );
        self::assertFalse($options->isBackupKey('Other:backup_1700000002'));
        self::assertFalse($options->restore('Other:backup_1700000002'));
    }

    public function testThemeNameThatDoesNotFitTheStorageColumnIsRejectedOnWrite(): void
    {
        $store = new InMemoryOptionsStore([], 32, 'ttdf:');
        $options = new Options($store, str_repeat('a', 30));

        self::assertSame([], $options->backupPrefixes());
        self::assertFalse($options->isBackupKey('ttdf:' . str_repeat('a', 30) . ':backup_1700000000'));
        self::assertNull($options->get('SubTitle'));

        $this->expectException(RuntimeException::class);
        $options->set('SubTitle', 'Hello');
    }

    public function testDedicatedTableIsTheDefaultStorageMode(): void
    {
        self::assertTrue(Options::usesDedicatedTable());
        self::assertSame('ttdf', Options::storageTable());
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testFalseDatabaseTableConstantMovesSettingsIntoTypechoOptions(): void
    {
        define('__TTDF_DATABASE_TABLE__', false);

        self::assertFalse(Options::usesDedicatedTable());
        self::assertSame('options', Options::storageTable());
    }
}
