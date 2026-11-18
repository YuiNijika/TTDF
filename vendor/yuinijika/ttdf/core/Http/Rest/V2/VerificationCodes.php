<?php

declare(strict_types=1);

namespace TTDF\Http\Rest\V2;

use RuntimeException;
use Throwable;
use TTDF\Compat\Typecho;
use TTDF\Support\Json;
use Typecho\Db;

/**
 * Short-lived e-mail verification codes.
 *
 * Codes are stored as individual rows in Typecho's options table. The row name
 * is a deterministic HMAC of the address and purpose, which keeps the address
 * out of the database key while still allowing a lookup after the request that
 * generated the code has ended. Only a password hash of the six digit code is
 * persisted.
 */
final class VerificationCodes
{
    private const PREFIX = 'ttdfv2c_';

    private const MAX_ATTEMPTS = 5;

    private const DEFAULT_TTL = 600;

    private const DEFAULT_INTERVAL = 60;

    private const PURPOSES = ['register', 'reset', 'email'];

    /**
     * @return array{code: string, expiresAt: int, retryAfter: int}
     */
    public static function issue(
        string $mail,
        string $purpose,
        int $ttl = self::DEFAULT_TTL,
        int $interval = self::DEFAULT_INTERVAL
    ): array {
        $mail = self::normalizeMail($mail);
        $purpose = self::purpose($purpose);
        $ttl = max(60, min(3600, $ttl));
        $interval = max(10, min(600, $interval));
        $name = self::key($mail, $purpose);
        $now = time();
        $existing = self::read($name);

        if (is_array($existing) && $now < (int) ($existing['sentAt'] ?? 0) + $interval) {
            throw new RateLimitException((int) ($existing['sentAt'] ?? $now) + $interval - $now);
        }

        $code = (string) random_int(100000, 999999);
        $hash = password_hash($code, PASSWORD_DEFAULT);

        $record = [
            'hash' => $hash,
            'purpose' => $purpose,
            'expiresAt' => $now + $ttl,
            'sentAt' => $now,
            'attempts' => 0,
        ];

        self::write($name, $record, $existing !== null);

        return [
            'code' => $code,
            'expiresAt' => (int) $record['expiresAt'],
            'retryAfter' => $interval,
        ];
    }

    public static function verify(string $mail, string $purpose, string $code): bool
    {
        $mail = self::normalizeMail($mail);
        $purpose = self::purpose($purpose);
        $name = self::key($mail, $purpose);
        $record = self::read($name);

        if (!is_array($record) || (string) ($record['purpose'] ?? '') !== $purpose) {
            return false;
        }

        $expiresAt = (int) ($record['expiresAt'] ?? 0);
        $attempts = (int) ($record['attempts'] ?? 0);
        if ($expiresAt <= time() || $attempts >= self::MAX_ATTEMPTS) {
            self::clear($mail, $purpose);
            return false;
        }

        $hash = (string) ($record['hash'] ?? '');
        if ($code !== '' && $hash !== '' && password_verify($code, $hash)) {
            self::clear($mail, $purpose);
            return true;
        }

        $record['attempts'] = $attempts + 1;
        self::write($name, $record, true);

        if ((int) $record['attempts'] >= self::MAX_ATTEMPTS) {
            self::clear($mail, $purpose);
        }

        return false;
    }

    public static function clear(string $mail, string $purpose): void
    {
        $name = self::key(self::normalizeMail($mail), self::purpose($purpose));

        try {
            Db::get()->query(
                Db::get()->delete('table.options')
                    ->where('name = ?', $name)
                    ->where('user = ?', 0)
            );
        } catch (Throwable) {
            // A missing or already consumed code needs no further cleanup.
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function read(string $name): ?array
    {
        $db = Db::get();
        $row = $db->fetchRow(
            $db->select('value')
                ->from('table.options')
                ->where('name = ?', $name)
                ->where('user = ?', 0)
                ->limit(1)
        );

        if (!is_array($row) || !array_key_exists('value', $row)) {
            return null;
        }

        $decoded = Json::decode((string) $row['value']);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $record
     */
    private static function write(string $name, array $record, bool $exists): void
    {
        $db = Db::get();
        $value = Json::encode($record);

        if ($exists) {
            $db->query(
                $db->update('table.options')
                    ->rows(['value' => $value])
                    ->where('name = ?', $name)
                    ->where('user = ?', 0)
            );
            return;
        }

        $db->query(
            $db->insert('table.options')->rows([
                'name' => $name,
                'user' => 0,
                'value' => $value,
            ])
        );
    }

    private static function key(string $mail, string $purpose): string
    {
        $seed = 'ttdf-rest-v2-code|' . Typecho::siteUrl() . '|' . (defined('__TYPECHO_ROOT_DIR__')
            ? (string) constant('__TYPECHO_ROOT_DIR__')
            : '');
        $digest = hash_hmac('sha256', $mail . '|' . $purpose, $seed);

        return self::PREFIX . substr($digest, 0, 20);
    }

    private static function normalizeMail(string $mail): string
    {
        return strtolower(trim($mail));
    }

    private static function purpose(string $purpose): string
    {
        $purpose = strtolower(trim($purpose));
        if (!in_array($purpose, self::PURPOSES, true)) {
            throw new RuntimeException('Invalid verification purpose');
        }

        return $purpose;
    }
}
