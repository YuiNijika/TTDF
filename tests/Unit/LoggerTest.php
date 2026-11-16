<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TTDF\Diagnostics\Logger;

final class LoggerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/ttdf-log-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        $file = $this->directory . '/error.log';
        @unlink($file);
        @unlink($file . '.1');
        @unlink($file . '.2');
        @unlink($file . '.3');
        @rmdir($this->directory);
    }

    public function testSensitiveContextKeysAreRedacted(): void
    {
        $file = $this->directory . '/error.log';
        $logger = new Logger($file);

        $logger->write('error', 'request failed', [
            'token' => 'super-secret-token',
            'mail' => 'alice@example.test',
            'ip' => '203.0.113.9',
            'cookie' => 'session=abc',
            'nested' => [
                'password' => 'hunter2',
                'safe' => 'visible',
            ],
        ]);

        $contents = (string) file_get_contents($file);

        self::assertStringContainsString('[redacted]', $contents);
        self::assertStringContainsString('visible', $contents);
        self::assertStringNotContainsString('super-secret-token', $contents);
        self::assertStringNotContainsString('alice@example.test', $contents);
        self::assertStringNotContainsString('203.0.113.9', $contents);
        self::assertStringNotContainsString('session=abc', $contents);
        self::assertStringNotContainsString('hunter2', $contents);
    }

    public function testLogMessagesCannotInjectExtraLines(): void
    {
        $file = $this->directory . '/error.log';
        $logger = new Logger($file);

        $logger->write('error', "first line\n[2026-01-01] FAKE: injected");

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        self::assertCount(1, $lines);
        self::assertStringContainsString('first line [2026-01-01] FAKE: injected', $lines[0]);
    }

    public function testSuffixedSecretKeysAreRedacted(): void
    {
        $file = $this->directory . '/error.log';
        $logger = new Logger($file);

        $logger->write('error', 'request failed', [
            'access_token' => 'access-value',
            'client_secret' => 'client-value',
            'api_key' => 'api-value',
            'safe_field' => 'visible',
        ]);

        $contents = (string) file_get_contents($file);

        self::assertStringNotContainsString('access-value', $contents);
        self::assertStringNotContainsString('client-value', $contents);
        self::assertStringNotContainsString('api-value', $contents);
        self::assertStringContainsString('visible', $contents);
    }

    public function testObjectPropertiesAreRedactedRecursively(): void
    {
        $file = $this->directory . '/error.log';
        $logger = new Logger($file);
        $payload = new \stdClass();
        $payload->token = 'object-token';
        $payload->safe = 'visible-object-value';
        $payload->nested = (object) [
            'password' => 'object-password',
            'safe' => 'nested-visible',
        ];

        $logger->write('error', 'object context', ['payload' => $payload]);

        $contents = (string) file_get_contents($file);

        self::assertStringContainsString('visible-object-value', $contents);
        self::assertStringContainsString('nested-visible', $contents);
        self::assertStringNotContainsString('object-token', $contents);
        self::assertStringNotContainsString('object-password', $contents);
    }

    public function testCamelCaseCredentialKeysAreRedacted(): void
    {
        $file = $this->directory . '/error.log';
        $logger = new Logger($file);

        $logger->write('error', 'camel case context', [
            'accessToken' => 'camel-token',
            'refreshToken' => 'refresh-token',
            'userEmail' => 'camel@example.test',
            'safeField' => 'visible',
        ]);

        $contents = (string) file_get_contents($file);

        self::assertStringContainsString('visible', $contents);
        self::assertStringNotContainsString('camel-token', $contents);
        self::assertStringNotContainsString('refresh-token', $contents);
        self::assertStringNotContainsString('camel@example.test', $contents);
    }
}
