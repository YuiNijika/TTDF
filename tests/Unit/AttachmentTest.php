<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TTDF\Compat\Upload;
use TTDF\Http\Rest\Attachment;

final class Upload12
{
    public static function attachmentHandle(array $content): string
    {
        return 'https://example.test' . (string) $content['attachment']->path;
    }
}

final class UnsafeUpload
{
    public static function attachmentHandle(array $content): string
    {
        return 'javascript:alert(1)';
    }
}

final class AttachmentTest extends TestCase
{
    public function testMetadataParsesTypechoAttachmentJson(): void
    {
        $text = '{"name":"photo.jpg","path":"/usr/uploads/2026/11/photo.jpg","size":2048,"type":"jpg","mime":"image/jpeg"}';
        $metadata = Attachment::metadata($text);

        self::assertSame('/usr/uploads/2026/11/photo.jpg', $metadata['path']);
        self::assertSame('photo.jpg', $metadata['name']);
        self::assertSame(2048, $metadata['size']);
        self::assertSame('jpg', $metadata['type']);
        self::assertSame('image/jpeg', $metadata['mime']);
        self::assertTrue($metadata['isImage']);
    }

    public function testMetadataFallsBackToLegacyPathText(): void
    {
        $metadata = Attachment::metadata('/usr/uploads/legacy.pdf');

        self::assertSame('/usr/uploads/legacy.pdf', $metadata['path']);
        self::assertSame('legacy.pdf', $metadata['name']);
        self::assertSame('pdf', $metadata['type']);
        self::assertSame(0, $metadata['size']);
        self::assertFalse($metadata['isImage']);
    }

    public function testMetadataDoesNotTreatInvalidJsonAsAPath(): void
    {
        $metadata = Attachment::metadata('{"path":');

        self::assertSame('', $metadata['path']);
        self::assertSame('', $metadata['name']);
        self::assertSame(0, $metadata['size']);
        self::assertSame('', Attachment::url('{"path":'));
    }

    public function testUrlUsesTypecho13ConfigSignature(): void
    {
        $text = '{"path":"/usr/uploads/photo.jpg","type":"jpg"}';

        self::assertSame('https://example.test/usr/uploads/photo.jpg', Attachment::url($text));
    }

    public function testUrlUsesTypecho12ArraySignature(): void
    {
        self::assertSame(
            'https://example.test/usr/uploads/photo.jpg',
            Upload::url('/usr/uploads/photo.jpg', Upload12::class)
        );
    }

    public function testUrlKeepsAbsoluteUrlsAndRejectsUnsafeHandlerOutput(): void
    {
        self::assertSame(
            'https://cdn.example.test/photo.jpg',
            Attachment::url('https://cdn.example.test/photo.jpg')
        );
        self::assertSame(
            '',
            Attachment::url('/usr/uploads/photo.jpg', UnsafeUpload::class)
        );
    }

    public function testEmptyAttachmentPathReturnsEmptyUrl(): void
    {
        self::assertSame('', Attachment::url(''));
        self::assertSame('', Attachment::url('{"path":""}'));
    }
}
