<?php

declare(strict_types=1);

namespace TTDF\Theme;

/**
 * Resolves where theme settings live.
 *
 * `__TTDF_DATABASE_TABLE__` in Typecho's `config.inc.php` selects the driver:
 *
 * - undefined or truthy: TTDF's dedicated `ttdf` table (default)
 * - explicitly falsy: Typecho's own `options` table under `ttdf:{theme}`
 *
 * Kept as a pure helper so the decision can be tested without defining a
 * process-wide constant.
 */
final class StorageMode
{
    public const DEDICATED_TABLE = 'ttdf-table';

    public const TYPECHO_OPTIONS = 'typecho-options';

    private const DISABLED_VALUES = ['', '0', 'false', 'off', 'no'];

    /**
     * @param mixed $flag Raw constant value, or `null` when it is not defined.
     */
    public static function dedicatedTableEnabled(mixed $flag): bool
    {
        if ($flag === null) {
            return true;
        }

        if (is_bool($flag)) {
            return $flag;
        }

        if (is_int($flag) || is_float($flag)) {
            return $flag != 0;
        }

        return !in_array(strtolower(trim((string) $flag)), self::DISABLED_VALUES, true);
    }

    public static function label(mixed $flag): string
    {
        return self::dedicatedTableEnabled($flag) ? self::DEDICATED_TABLE : self::TYPECHO_OPTIONS;
    }
}
