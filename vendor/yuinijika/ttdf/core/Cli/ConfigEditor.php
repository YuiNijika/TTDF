<?php

declare(strict_types=1);

namespace TTDF\Cli;

use RuntimeException;

/**
 * Minimal line editor for the two optional frontend sections in app/config/app.php.
 *
 * The CLI must not execute the theme config, because theme config files are
 * guarded by __TYPECHO_ROOT_DIR__ and are meant for Typecho requests only.
 */
final class ConfigEditor
{
    private const SECTIONS = ['vite', 'webpack'];

    private const KEYS = ['enabled', 'dev'];

    /**
     * @return array{vite: array{enabled: bool, dev: bool}, webpack: array{enabled: bool, dev: bool}}
     */
    public static function state(string $file): array
    {
        $lines = self::readLines($file);
        $state = [
            'vite' => ['enabled' => false, 'dev' => false],
            'webpack' => ['enabled' => false, 'dev' => false],
        ];

        foreach (self::SECTIONS as $section) {
            $range = self::findSection($lines, $section);
            if ($range === null) {
                continue;
            }

            foreach (self::KEYS as $key) {
                $value = self::readValue($lines, $range['start'], $range['end'], $key);
                if ($value !== null) {
                    $state[$section][$key] = $value;
                }
            }
        }

        return $state;
    }

    /**
     * @param array<string, array<string, bool>> $updates
     */
    public static function apply(string $file, array $updates): void
    {
        $raw = @file_get_contents($file);
        if ($raw === false) {
            throw new RuntimeException('Unable to read configuration file: ' . $file);
        }

        $eol = str_contains($raw, "\r\n") ? "\r\n" : "\n";
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $raw));

        foreach ($updates as $section => $values) {
            if (!in_array($section, self::SECTIONS, true)) {
                continue;
            }

            foreach ($values as $key => $value) {
                if (!in_array($key, self::KEYS, true)) {
                    continue;
                }

                self::writeValue($lines, $section, $key, (bool) $value);
            }
        }

        $content = implode($eol, $lines);
        if (@file_put_contents($file, $content, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write configuration file: ' . $file);
        }
    }

    /**
     * @return array<int, string>
     */
    private static function readLines(string $file): array
    {
        $raw = @file_get_contents($file);
        if ($raw === false) {
            throw new RuntimeException('Unable to read configuration file: ' . $file);
        }

        return explode("\n", str_replace(["\r\n", "\r"], "\n", $raw));
    }

    /**
     * @param array<int, string> $lines
     */
    private static function writeValue(array &$lines, string $section, string $key, bool $value): void
    {
        $range = self::findSection($lines, $section);
        if ($range === null) {
            self::insertSection($lines, $section, [$key => $value]);
            return;
        }

        if ($range['start'] === $range['end']) {
            self::expandEmptySection($lines, $section, $range['start'], $key, $value);
            return;
        }

        for ($index = $range['start'] + 1; $index < $range['end']; $index++) {
            $pattern = '/^(\s*)\'' . preg_quote($key, '/') . '\'\s*=>\s*(?:true|false)\s*,?\s*$/i';
            if (preg_match($pattern, $lines[$index], $matches) === 1) {
                $lines[$index] = $matches[1]
                    . "'" . $key . "' => " . self::literal($value) . ',';
                return;
            }
        }

        $indent = self::indent($lines[$range['start']]) . '    ';
        array_splice(
            $lines,
            $range['start'] + 1,
            0,
            [$indent . "'" . $key . "' => " . self::literal($value) . ',']
        );
    }

    /**
     * @param array<int, string> $lines
     * @param array<string, bool> $values
     */
    private static function insertSection(array &$lines, string $section, array $values): void
    {
        $close = self::findClosingBracket($lines);
        if ($close === null) {
            throw new RuntimeException('Unable to locate the top-level configuration array close.');
        }

        $indent = self::indent($lines[$close]) . '    ';
        $block = [$indent . "'" . $section . "' => ["];
        foreach ($values as $key => $value) {
            $block[] = $indent . "    '" . $key . "' => " . self::literal((bool) $value) . ',';
        }
        $block[] = $indent . '],';

        array_splice($lines, $close, 0, $block);
    }

    /**
     * @param array<int, string> $lines
     */
    private static function expandEmptySection(
        array &$lines,
        string $section,
        int $index,
        string $key,
        bool $value
    ): void {
        $pattern = '/^(\s*)\'' . preg_quote($section, '/') . '\'\s*=>\s*\[\s*\]\s*,?\s*$/';
        if (preg_match($pattern, $lines[$index], $matches) !== 1) {
            throw new RuntimeException('Unsupported one-line configuration section: ' . $section);
        }

        $indent = $matches[1];
        $block = [
            $indent . "'" . $section . "' => [",
            $indent . "    '" . $key . "' => " . self::literal($value) . ',',
            $indent . '],',
        ];
        array_splice($lines, $index, 1, $block);
    }

    /**
     * @param array<int, string> $lines
     * @return array{start: int, end: int}|null
     */
    private static function findSection(array $lines, string $section): ?array
    {
        $count = count($lines);
        $pattern = '/^(\s*)\'' . preg_quote($section, '/') . '\'\s*=>\s*\[/';

        for ($start = 0; $start < $count; $start++) {
            if (preg_match($pattern, $lines[$start]) !== 1) {
                continue;
            }

            $depth = 0;
            for ($end = $start; $end < $count; $end++) {
                $depth += self::bracketDelta($lines[$end]);
                if ($depth <= 0) {
                    return ['start' => $start, 'end' => $end];
                }
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $lines
     */
    private static function findClosingBracket(array $lines): ?int
    {
        for ($index = count($lines) - 1; $index >= 0; $index--) {
            if (preg_match('/^\s*\];\s*$/', $lines[$index]) === 1) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $lines
     */
    private static function readValue(array $lines, int $start, int $end, string $key): ?bool
    {
        $pattern = '/^\s*\'' . preg_quote($key, '/') . '\'\s*=>\s*(true|false)\s*,?\s*$/i';
        for ($index = $start + 1; $index < $end; $index++) {
            if (preg_match($pattern, $lines[$index], $matches) === 1) {
                return strtolower($matches[1]) === 'true';
            }
        }

        return null;
    }

    private static function bracketDelta(string $line): int
    {
        $clean = preg_replace("/'(?:\\\\.|[^'\\\\])*'/", "''", $line) ?? $line;
        $clean = preg_replace('/"(?:\\\\.|[^"\\\\])*"/', '""', $clean) ?? $clean;
        $clean = preg_replace('#//.*$#', '', $clean) ?? $clean;
        $clean = preg_replace('/#.*$/', '', $clean) ?? $clean;

        return substr_count($clean, '[') - substr_count($clean, ']');
    }

    private static function indent(string $line): string
    {
        if (preg_match('/^\s*/', $line, $matches) !== 1) {
            return '';
        }

        return $matches[0];
    }

    private static function literal(bool $value): string
    {
        return $value ? 'true' : 'false';
    }
}
