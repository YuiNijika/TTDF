<?php

declare(strict_types=1);

namespace TTDF\Http\Rest;

use TTDF\Compat\Typecho;
use TTDF\Framework;
use TTDF\Http\Request;
use TTDF\Http\Response;
use TTDF\Theme\Options;
use TTDF\Theme\Settings;
use TTDF\Theme\Templates;
use TTDF\Theme\Customizer;
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

        $saved = Settings::save($settings);
        if ($saved === 0 && $settings !== []) {
            Response::error('No valid settings were provided', 422);
        }

        Response::success([
            'saved' => $saved,
            'message' => '设置已更新',
            'backup' => Options::instance()->lastBackupKey(),
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
            'categories' => Settings::categoryOptions(),
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

        $count = Settings::import($settings);
        if ($count === 0) {
            Response::error('No valid settings were provided', 422);
        }

        Response::success([
            'imported' => $count,
            'backup' => Options::instance()->lastBackupKey(),
        ]);
    }

    public function migrate(): never
    {
        $migrator = new Migrator(Options::instance(), $this->root);
        Response::success($migrator->run());
    }

    public function backup(): never
    {
        Response::success([
            'backup' => Settings::backup(),
            'backups' => Settings::backups(),
        ]);
    }

    public function backups(): never
    {
        Response::success(Settings::backups());
    }

    public function restore(): never
    {
        $body = Request::json();
        $key = isset($body['key']) && is_string($body['key']) ? trim($body['key']) : '';
        if ($key === '' || Settings::restore($key) === false) {
            Response::error('Backup not found', 422);
        }

        Response::success([
            'restored' => $key,
            'backup' => Options::instance()->lastBackupKey(),
        ]);
    }

    public function diagnostics(): never
    {
        $logFile = $this->root . '/logs/error.log';

        Response::success([
            'phpVersion' => PHP_VERSION,
            'typechoVersion' => Typecho::version(),
            'frameworkVersion' => Framework::VERSION,
            'restEnabled' => State::enabled(),
            'restTokenEnabled' => State::tokenEnabled(),
            'settingsStorage' => 'json-document',
            'settingsDriver' => Options::usesDedicatedTable() ? 'ttdf-table' : 'typecho-options',
            'settingsTable' => Options::storageTable(),
            'logWritable' => is_dir(dirname($logFile)) ? is_writable(dirname($logFile)) : is_writable($this->root),
            'logFile' => $logFile,
        ]);
    }

    /**
     * Create the optional `/admin/ttdf-custom-theme/` shim. The canonical
     * customizer entry stays `options-theme.php?panel=ttdf-custom-theme`.
     */
    public function customizerBridge(): never
    {
        $result = Customizer::installBridge();
        if (!$result['ok']) {
            Response::error($result['message'], 422);
        }

        Response::success([
            'installed' => Customizer::bridgeInstalled(),
            'url' => Customizer::bridgeUrl(),
            'message' => $result['message'],
        ]);
    }
}
