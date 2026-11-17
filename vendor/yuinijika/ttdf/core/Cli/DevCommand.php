<?php

declare(strict_types=1);

namespace TTDF\Cli;

use Throwable;

final class DevCommand
{
    /**
     * @param array{
     *     vite: bool,
     *     webpack: bool,
     *     off: bool,
     *     status: bool,
     *     dry-run: bool
     * } $options
     */
    public static function run(string $root, array $options): int
    {
        $file = $root . '/app/config/app.php';

        try {
            $state = ConfigEditor::state($file);
        } catch (Throwable $exception) {
            return self::fail($exception->getMessage());
        }

        if ($options['status'] || (!$options['vite'] && !$options['webpack'] && !$options['off'])) {
            self::printState($file, $state);
            return 0;
        }

        if ($options['vite'] && $options['webpack']) {
            return self::fail('Choose only one bundler: --vite or --webpack.');
        }

        $updates = self::updates($options);
        if ($options['dry-run']) {
            self::printState($file, self::merge($state, $updates));
            self::write('Dry run: app/config/app.php was not modified.');
            return 0;
        }

        try {
            ConfigEditor::apply($file, $updates);
            $state = ConfigEditor::state($file);
        } catch (Throwable $exception) {
            return self::fail($exception->getMessage());
        }

        self::printState($file, $state);
        self::write(self::nextStep($options));

        return 0;
    }

    /**
     * @param array<string, array<string, bool>> $updates
     * @param array{vite: array{enabled: bool, dev: bool}, webpack: array{enabled: bool, dev: bool}} $state
     * @return array{vite: array{enabled: bool, dev: bool}, webpack: array{enabled: bool, dev: bool}}
     */
    private static function merge(array $state, array $updates): array
    {
        if (isset($updates['vite']['enabled'])) {
            $state['vite']['enabled'] = (bool) $updates['vite']['enabled'];
        }
        if (isset($updates['vite']['dev'])) {
            $state['vite']['dev'] = (bool) $updates['vite']['dev'];
        }
        if (isset($updates['webpack']['enabled'])) {
            $state['webpack']['enabled'] = (bool) $updates['webpack']['enabled'];
        }
        if (isset($updates['webpack']['dev'])) {
            $state['webpack']['dev'] = (bool) $updates['webpack']['dev'];
        }

        return $state;
    }

    /**
     * @param array{
     *     vite: bool,
     *     webpack: bool,
     *     off: bool,
     *     status: bool,
     *     dry-run: bool
     * } $options
     * @return array<string, array<string, bool>>
     */
    private static function updates(array $options): array
    {
        if ($options['off']) {
            if ($options['vite']) {
                return ['vite' => ['dev' => false]];
            }

            if ($options['webpack']) {
                return ['webpack' => ['dev' => false]];
            }

            return [
                'vite' => ['dev' => false],
                'webpack' => ['dev' => false],
            ];
        }

        if ($options['vite']) {
            return [
                'vite' => ['enabled' => true, 'dev' => true],
                'webpack' => ['enabled' => false, 'dev' => false],
            ];
        }

        return [
            'webpack' => ['enabled' => true, 'dev' => true],
            'vite' => ['enabled' => false, 'dev' => false],
        ];
    }

    /**
     * @param array{
     *     vite: bool,
     *     webpack: bool,
     *     off: bool,
     *     status: bool,
     *     dry-run: bool
     * } $options
     */
    private static function nextStep(array $options): string
    {
        if ($options['off']) {
            return 'Dev mode disabled. Rebuild production assets with pnpm --dir vite build or pnpm --dir webpack build.';
        }

        if ($options['vite']) {
            return 'Vite dev mode enabled. Run: pnpm --dir vite dev';
        }

        return 'Webpack dev mode enabled. Run: pnpm --dir webpack dev';
    }

    /**
     * @param array{vite: array{enabled: bool, dev: bool}, webpack: array{enabled: bool, dev: bool}} $state
     */
    private static function printState(string $file, array $state): void
    {
        self::write('Config: ' . $file);
        self::write(sprintf(
            'vite:    enabled=%s dev=%s',
            self::bool($state['vite']['enabled']),
            self::bool($state['vite']['dev'])
        ));
        self::write(sprintf(
            'webpack: enabled=%s dev=%s',
            self::bool($state['webpack']['enabled']),
            self::bool($state['webpack']['dev'])
        ));
    }

    private static function bool(bool $value): string
    {
        return $value ? 'true' : 'false';
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
