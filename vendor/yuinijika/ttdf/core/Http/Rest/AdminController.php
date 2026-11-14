<?php

declare(strict_types=1);

namespace TTDF\Http\Rest;

use TTDF\Compat\Typecho;
use TTDF\Config\Config;
use TTDF\Framework;
use TTDF\Http\Request;
use TTDF\Http\Response;
use TTDF\Theme\Options;
use TTDF\Theme\Settings;
use TTDF\Theme\Templates;
use TTDF\Upgrade\Migrator;

final class AdminController
{
    public function __construct(private readonly string $root)
    {
    }

    public function optionsGet(): never
    {
        Response::success(Settings::values());
    }

    public function optionsPost(): never
    {
        $body = Request::json();
        $settings = isset($body['settings']) && is_array($body['settings']) ? $body['settings'] : $body;

        $validated = Settings::validate($settings);
        if ($validated === [] && $settings !== []) {
            Response::error('No valid settings were provided', 422);
        }

        $options = Options::instance();
        $options->ensureInstalled();
        foreach ($validated as $name => $value) {
            $options->set($name, $value);
        }

        Response::success([
            'saved' => count($validated),
            'message' => '设置已更新',
        ]);
    }

    public function schema(): never
    {
        Response::success(Settings::schema());
    }

    public function config(): never
    {
        Response::success([
            'tabs' => Settings::tabs(),
            'frameworkVersion' => Framework::VERSION,
            'components' => Templates::available($this->root),
            'templateSlots' => Templates::SLOTS,
        ]);
    }

    public function formData(): never
    {
        Response::success(Settings::values());
    }

    public function themeInfo(): never
    {
        Response::success(Settings::themeInfo());
    }

    public function export(): never
    {
        Response::success(Settings::export());
    }

    public function import(): never
    {
        $body = Request::json();
        $settings = isset($body['settings']) && is_array($body['settings']) ? $body['settings'] : $body;
        if ($settings === []) {
            Response::error('Missing settings', 422);
        }

        Options::instance()->ensureInstalled();
        $count = Settings::import($settings);
        Response::success(['imported' => $count]);
    }

    public function migrate(): never
    {
        $migrator = new Migrator(Options::instance(), $this->root);
        Response::success($migrator->run());
    }

    public function diagnostics(): never
    {
        $options = Options::instance();
        $logFile = $this->root . '/logs/error.log';

        Response::success([
            'phpVersion' => PHP_VERSION,
            'typechoVersion' => Typecho::version(),
            'frameworkVersion' => Framework::VERSION,
            'restEnabled' => State::enabled(),
            'settingsSchemaVersion' => $options->schemaVersion(),
            'settingsTable' => (string) Config::get('settings.table', 'ttdf'),
            'logWritable' => is_dir(dirname($logFile)) ? is_writable(dirname($logFile)) : is_writable($this->root),
            'logFile' => $logFile,
        ]);
    }
}
