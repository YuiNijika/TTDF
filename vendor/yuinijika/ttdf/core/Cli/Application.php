<?php

declare(strict_types=1);

namespace TTDF\Cli;

final class Application
{
    /**
     * @param list<string> $argv
     */
    public static function run(array $argv): int
    {
        $args = array_slice($argv, 1);
        if ($args === [] || in_array($args[0], ['help', '--help', '-h'], true)) {
            self::write(self::usage());
            return 0;
        }

        $command = array_shift($args);
        if ($command !== 'dev') {
            return self::fail('Unknown command: ' . $command);
        }

        $options = self::parseOptions($args);
        if ($options['error'] !== null) {
            return self::fail((string) $options['error']);
        }

        $root = self::locateRoot($options['root']);
        if ($root === null) {
            return self::fail(
                'Cannot find a TTDF theme root. Run from the theme directory or pass --root=PATH.'
            );
        }

        return DevCommand::run($root, $options);
    }

    /**
     * @param list<string> $args
     * @return array{
     *     vite: bool,
     *     webpack: bool,
     *     off: bool,
     *     status: bool,
     *     dry-run: bool,
     *     root: ?string,
     *     error: ?string
     * }
     */
    private static function parseOptions(array $args): array
    {
        $options = [
            'vite' => false,
            'webpack' => false,
            'off' => false,
            'status' => false,
            'dry-run' => false,
            'root' => null,
            'error' => null,
        ];

        $count = count($args);
        for ($index = 0; $index < $count; $index++) {
            $arg = $args[$index];

            if ($arg === '--vite') {
                $options['vite'] = true;
                continue;
            }

            if ($arg === '--webpack') {
                $options['webpack'] = true;
                continue;
            }

            if ($arg === '--off') {
                $options['off'] = true;
                continue;
            }

            if ($arg === '--status') {
                $options['status'] = true;
                continue;
            }

            if ($arg === '--dry-run') {
                $options['dry-run'] = true;
                continue;
            }

            if ($arg === '--root' && isset($args[$index + 1])) {
                $options['root'] = $args[++$index];
                continue;
            }

            if (str_starts_with($arg, '--root=')) {
                $options['root'] = substr($arg, 7);
                continue;
            }

            $options['error'] = 'Unknown option: ' . $arg;
            return $options;
        }

        return $options;
    }

    private static function locateRoot(?string $root): ?string
    {
        if ($root !== null && $root !== '') {
            $resolved = realpath($root);
            if ($resolved !== false && is_file($resolved . '/app/config/app.php')) {
                return str_replace('\\', '/', $resolved);
            }

            return null;
        }

        $directory = getcwd();
        if ($directory === false) {
            return null;
        }

        $resolved = realpath($directory);
        $directory = $resolved !== false ? $resolved : $directory;

        while (true) {
            if (is_file($directory . '/app/config/app.php')) {
                return str_replace('\\', '/', $directory);
            }

            $parent = dirname($directory);
            if ($parent === $directory) {
                return null;
            }

            $directory = $parent;
        }
    }

    private static function usage(): string
    {
        return implode(PHP_EOL, [
            'TTDF CLI',
            '',
            'Usage:',
            '  php vendor/yuinijika/ttdf/bin/ttdf dev --vite',
            '  php vendor/yuinijika/ttdf/bin/ttdf dev --webpack',
            '  php vendor/yuinijika/ttdf/bin/ttdf dev --off',
            '  php vendor/yuinijika/ttdf/bin/ttdf dev --status',
            '',
            'Options:',
            '  --vite       Enable Vite dev mode and disable webpack.',
            '  --webpack    Enable webpack dev mode and disable Vite.',
            '  --off        Disable dev mode without disabling the selected bundler.',
            '  --status     Show the current bundler configuration.',
            '  --dry-run    Print the result without writing app/config/app.php.',
            '  --root=PATH  Theme root; defaults to the nearest parent with app/config/app.php.',
            '  -h, --help   Show this help.',
        ]);
    }

    private static function fail(string $message): int
    {
        fwrite(STDERR, $message . PHP_EOL);

        return 1;
    }

    private static function write(string $message): void
    {
        echo $message . PHP_EOL;
    }
}
