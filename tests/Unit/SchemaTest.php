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
        mkdir($this->root . '/app', 0777, true);
        file_put_contents($this->root . '/app/setup.php', <<<'PHP'
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
        @unlink($this->root . '/app/setup.php');
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
}
