<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use TTDF\Compat\Upload;

final class UploadDirectoryTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testUploadDirectoryUsesTypechoOverride(): void
    {
        define('__TYPECHO_UPLOAD_DIR__', '/custom/uploads/');

        self::assertSame('custom/uploads', Upload::directory());
        self::assertTrue(Upload::isUploadPath('/custom/uploads/2026/11/photo.jpg'));
        self::assertFalse(Upload::isUploadPath('/usr/uploads/2026/11/photo.jpg'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testUploadUrlFallsBackToTypechoUploadUrlConstant(): void
    {
        define('__TYPECHO_UPLOAD_DIR__', '/custom/uploads');
        define('__TYPECHO_UPLOAD_URL__', 'https://cdn.example.test/assets/');

        self::assertSame(
            'https://cdn.example.test/assets/custom/uploads/photo.jpg',
            Upload::url('/custom/uploads/photo.jpg', 'Missing\\UploadHandler')
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDeleteUsesTypechoUploadRootConstant(): void
    {
        $root = sys_get_temp_dir() . '/ttdf-upload-' . bin2hex(random_bytes(6));
        $directory = $root . '/files';
        if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
            self::fail('Unable to create temporary upload directory');
        }

        $file = $directory . '/photo.jpg';
        file_put_contents($file, 'image');

        define('__TYPECHO_UPLOAD_DIR__', '/files');
        define('__TYPECHO_UPLOAD_ROOT_DIR__', $root);

        try {
            self::assertTrue(Upload::delete('/files/photo.jpg'));
            self::assertFileDoesNotExist($file);
        } finally {
            @unlink($file);
            @rmdir($directory);
            @rmdir($root);
        }
    }
}
