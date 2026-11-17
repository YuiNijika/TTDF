<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TTDF\Theme\Schema;

final class SchemaTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        Schema::reset();
        $this->root = sys_get_temp_dir() . '/ttdf-schema-' . uniqid('', true);
        mkdir($this->root . '/app/config', 0777, true);
        file_put_contents($this->root . '/app/config/theme.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'General' => [
        'title' => 'General',
        'fields' => [
            ['type' => 'Html', 'name' => 'Notice', 'content' => '<p>Notice</p>'],
            ['type' => 'Text', 'name' => 'SubTitle', 'value' => ''],
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

    public function testHtmlFieldsArePresentationalAndNotStored(): void
    {
        $tabs = Schema::tabs($this->root);
        $fields = Schema::fields($this->root);

        self::assertCount(2, $tabs['General']['fields']);
        self::assertArrayNotHasKey('Notice', $fields);
        self::assertArrayHasKey('SubTitle', $fields);
        self::assertSame(['SubTitle'], Schema::names($this->root));
    }

    public function testInvalidFieldNamesAreRejected(): void
    {
        file_put_contents($this->root . '/app/config/theme.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'General' => [
        'title' => 'General',
        'fields' => [
            ['type' => 'Text', 'name' => 'Valid_Name'],
            ['type' => 'Text', 'name' => 'bad-name'],
            ['type' => 'Text', 'name' => '../escape'],
            ['type' => 'Text', 'name' => ''],
            ['type' => 'Text', 'name' => str_repeat('a', 101)],
        ],
    ],
];
PHP);

        Schema::reset();

        self::assertSame(['Valid_Name'], array_keys(Schema::fields($this->root)));
    }

    public function testNestedTabsAreExposedAsSectionsAndFieldsAreFlattened(): void
    {
        file_put_contents($this->root . '/app/config/theme.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'General' => [
        'title' => '基础设置',
        'tabs' => [
            'general' => [
                'title' => '一般设置',
                'description' => '站点标识。',
                'fields' => [
                    ['type' => 'Text', 'name' => 'SubTitle'],
                ],
            ],
            'colors' => [
                'title' => '颜色设置',
                'fields' => [
                    ['type' => 'ColorPicker', 'name' => 'Color_Accent'],
                ],
            ],
        ],
    ],
];
PHP);

        Schema::reset();

        $tab = Schema::tabs($this->root)['General'];
        self::assertSame(['general', 'colors'], array_column($tab['sections'], 'id'));
        self::assertSame(['SubTitle', 'Color_Accent'], array_keys(Schema::fields($this->root)));
        self::assertSame(['SubTitle', 'Color_Accent'], Schema::names($this->root));
    }

    public function testNestedTabSectionIdsAreUnique(): void
    {
        file_put_contents($this->root . '/app/config/theme.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'General' => [
        'title' => '基础设置',
        'tabs' => [
            'first' => [
                'id' => 'general',
                'title' => '一般设置',
                'fields' => [
                    ['type' => 'Text', 'name' => 'SubTitle'],
                ],
            ],
            'second' => [
                'id' => 'general',
                'title' => '重复分组',
                'fields' => [
                    ['type' => 'Text', 'name' => 'FooterText'],
                ],
            ],
        ],
    ],
];
PHP);

        Schema::reset();

        $sections = Schema::tabs($this->root)['General']['sections'];
        self::assertSame(['general', 'general-2'], array_column($sections, 'id'));
        self::assertSame(['SubTitle', 'FooterText'], array_keys(Schema::fields($this->root)));
    }

    public function testAliasesMapLegacyNamesToTheCurrentFieldAndAreValidated(): void
    {
        file_put_contents($this->root . '/app/config/theme.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'General' => [
        'title' => 'General',
        'fields' => [
            [
                'type' => 'Text',
                'name' => 'Logo',
                'aliases' => ['logoUrl', 'logo_url', 'bad-alias', '', 'Logo', 'SubTitle'],
            ],
            ['type' => 'Text', 'name' => 'SubTitle'],
            ['type' => 'Text', 'name' => 'Plain', 'aliases' => 'logoUrl'],
        ],
    ],
];
PHP);

        Schema::reset();

        self::assertSame(
            ['logoUrl' => 'Logo', 'logo_url' => 'Logo'],
            Schema::aliases($this->root)
        );
        self::assertSame(['Logo', 'SubTitle', 'Plain'], Schema::names($this->root));

        Schema::reset();

        self::assertSame(['logoUrl' => 'Logo', 'logo_url' => 'Logo'], Schema::aliases($this->root));
    }
}
