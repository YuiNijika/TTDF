<?php

declare(strict_types=1);

namespace TTDF\Theme;

use TTDF\Compat\Typecho;
use TTDF\Framework;
use TTDF\Http\Rest\Prefixes;
use TTDF\Support\Escaper;

/**
 * Full-page visual customizer for the active theme.
 *
 * Typecho's admin is a physical directory, so `installBridge()` writes a small
 * `/admin/ttdf-custom-theme/` shim that boots Typecho's `common.php` (session,
 * CSRF, first-run checks) and then renders a standalone document. A panel
 * inside `options-theme.php` stays available as a fallback when the admin
 * directory is not writable.
 */
final class Customizer
{
    public const PANEL = 'ttdf-custom-theme';

    public const BRIDGE_DIR = 'ttdf-custom-theme';

    public static function url(): string
    {
        $bridge = self::bridgeUrl();
        if ($bridge !== '') {
            return $bridge;
        }

        $admin = self::adminUrl('options-theme.php');

        if ($admin === '') {
            return '';
        }

        return $admin . '?panel=' . rawurlencode(self::PANEL);
    }

    public static function bridgeUrl(): string
    {
        $admin = self::adminUrl('');

        return $admin === '' ? '' : rtrim($admin, '/') . '/' . self::BRIDGE_DIR . '/';
    }

    /**
     * `options-theme.php` is the always-available entry: Typecho renders it for
     * administrators and it needs no writable admin directory.
     */
    public static function isRequested(): bool
    {
        $panel = $_GET['panel'] ?? null;

        return is_string($panel) && trim($panel, '/') === self::PANEL;
    }

    public static function render(): void
    {
        if (!Typecho::isAdmin()) {
            return;
        }

        echo '<style>
            body.ttdf-customizer-body{margin:0;padding:0;overflow:hidden}
            body.ttdf-customizer-body > header.typecho-head-nav,
            body.ttdf-customizer-body > footer.typecho-foot{display:none!important}
            body.ttdf-customizer-body > main.main{display:block;width:100%;max-width:none;margin:0;padding:0}
            body.ttdf-customizer-body main.main > .body.container{display:block;width:100%;max-width:none;margin:0;padding:0}
            body.ttdf-customizer-body .typecho-page-title,
            body.ttdf-customizer-body .typecho-option-tabs,
            body.ttdf-customizer-body #typecho-nav{display:none!important}
            body.ttdf-customizer-body .typecho-page-main{display:block;width:100%;max-width:none;margin:0;padding:0}
            body.ttdf-customizer-body .typecho-page-main > [class*="col-"]{width:100%;max-width:none;flex:none;margin:0;padding:0}
            body.ttdf-customizer-body #ttdf-custom-theme{width:100%;min-height:100vh}
        </style>' . PHP_EOL;
        echo '<script>document.body.classList.add("ttdf-customizer-body");</script>' . PHP_EOL;
        echo '<div id="ttdf-custom-theme" class="ttdf-custom-theme-mount"></div>' . PHP_EOL;
        echo '<script>window.ttdfCustomizerInfo = ' . Escaper::js(self::payload()) . ';</script>' . PHP_EOL;

        Assets::printStaticStyles(['app.css']);
        Assets::printStaticModules(['custom-theme.js']);
    }

    /**
     * Standalone document used by the `/admin/ttdf-custom-theme/` route. It
     * intentionally skips Typecho's admin chrome so the preview owns the whole
     * viewport, like WordPress' Customizer.
     */
    public static function renderDocument(): void
    {
        if (!Typecho::isAdmin()) {
            return;
        }

        $title = Typecho::themeName() . ' - 主题定制';

        echo '<!DOCTYPE html>' . PHP_EOL;
        echo '<html lang="zh-CN">' . PHP_EOL;
        echo '<head>' . PHP_EOL;
        echo '<meta charset="utf-8">' . PHP_EOL;
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">' . PHP_EOL;
        echo '<meta name="robots" content="noindex, nofollow">' . PHP_EOL;
        echo '<title>' . Escaper::html($title) . '</title>' . PHP_EOL;
        Assets::printStaticStyles(['app.css']);
        echo '</head>' . PHP_EOL;
        echo '<body class="ttdf-customizer-body">' . PHP_EOL;
        echo '<div id="ttdf-custom-theme" class="ttdf-custom-theme-mount"></div>' . PHP_EOL;
        echo '<script>window.ttdfCustomizerInfo = ' . Escaper::js(self::payload()) . ';</script>' . PHP_EOL;
        Assets::printStaticModules(['custom-theme.js']);
        echo '</body>' . PHP_EOL;
        echo '</html>' . PHP_EOL;
    }

    /**
     * @return array<string, string>
     */
    private static function payload(): array
    {
        $siteUrl = Typecho::siteUrl();
        $adminUrl = self::adminUrl('');
        if ($adminUrl === '') {
            $adminUrl = rtrim($siteUrl, '/') . '/admin/';
        }
        $indexUrl = Typecho::indexUrl();
        if ($indexUrl === '') {
            $indexUrl = $siteUrl;
        }
        $optionsUrl = self::adminUrl('options-theme.php');
        if ($optionsUrl === '') {
            $optionsUrl = $adminUrl;
        }

        return [
            'apiUrl' => rtrim($indexUrl, '/') . '/' . Prefixes::v1() . '/admin',
            'nonce' => Typecho::securityToken(),
            'themeName' => Typecho::themeName(),
            'themeVersion' => Typecho::themeVersion(),
            'ttdfVersion' => Framework::VERSION,
            'siteUrl' => $siteUrl,
            'backUrl' => $adminUrl,
            'optionsUrl' => $optionsUrl,
            'bridgeUrl' => self::bridgeUrl(),
        ];
    }

    /**
     * Copy a tiny shim into Typecho's admin directory. It boots Typecho's
     * `common.php` (session, CSRF, first-run checks) and the active theme's
     * `functions.php` so the TTDF autoloader is available, then renders the
     * standalone customizer document.
     *
     * @return array{ok: bool, path: string, message: string}
     */
    public static function installBridge(): array
    {
        $dir = self::adminDirectory();
        if ($dir === '') {
            return ['ok' => false, 'path' => '', 'message' => '无法定位 Typecho 后台目录'];
        }

        $path = $dir . '/' . self::BRIDGE_DIR . '/index.php';
        $target = dirname($path);
        $source = <<<'PHP'
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

define('__TYPECHO_ADMIN__', true);

require_once $root . '/config.inc.php';

// Typecho derives its site root (and therefore the cookie prefix) from
// SCRIPT_NAME. Running from a nested admin directory would shift both, so
// present this shim as the canonical `/admin/index.php` request.
$adminScript = rtrim((string) __TYPECHO_ADMIN_DIR__, '/') . '/index.php';
$_SERVER['SCRIPT_NAME'] = $adminScript;
$_SERVER['PHP_SELF'] = $adminScript;
$_SERVER['SCRIPT_FILENAME'] = $root . $adminScript;

\Widget\Init::alloc();
\Widget\Options::alloc()->to($options);
\Widget\User::alloc()->to($user);
\Widget\Security::alloc()->to($security);

if (!$user->pass('administrator', true)) {
    throw new \Typecho\Widget\Exception(_t('权限不足'), 403);
}

$themeFunctions = $options->themeFile($options->theme, 'functions.php');
if (is_file($themeFunctions)) {
    require_once $themeFunctions;
}

\TTDF\Theme\Customizer::renderDocument();
PHP;

        if (is_file($path)) {
            $existing = (string) file_get_contents($path);
            if ($existing === $source) {
                return ['ok' => true, 'path' => $path, 'message' => '入口已存在'];
            }
        }

        if (!is_dir($target) && !is_writable($dir)) {
            return ['ok' => false, 'path' => $path, 'message' => '后台目录不可写，请手动创建入口'];
        }

        if (!is_dir($target) && !mkdir($target, 0755, true) && !is_dir($target)) {
            return ['ok' => false, 'path' => $path, 'message' => '无法创建入口目录'];
        }

        return file_put_contents($path, $source) === false
            ? ['ok' => false, 'path' => $path, 'message' => '写入入口失败']
            : ['ok' => true, 'path' => $path, 'message' => '入口已创建'];
    }

    public static function bridgeInstalled(): bool
    {
        $dir = self::adminDirectory();

        return $dir !== '' && is_file($dir . '/' . self::BRIDGE_DIR . '/index.php');
    }

    /**
     * Best-effort install used by the settings screen so the documented
     * `/admin/ttdf-custom-theme/` entry exists without a manual step.
     */
    public static function ensureBridge(): bool
    {
        $result = self::installBridge();

        return $result['ok'];
    }

    /**
     * Typecho's admin URL accessor is a method on the real options widget but a
     * plain property in minimal stubs, so accept both shapes.
     */
    private static function adminUrl(string $path = ''): string
    {
        if (defined('__TYPECHO_ADMIN_DIR__')) {
            $site = Typecho::siteUrl();
            $dir = '/' . trim(str_replace('\\', '/', (string) constant('__TYPECHO_ADMIN_DIR__')), '/') . '/';
            if ($site !== '' && $dir !== '//') {
                $base = rtrim($site, '/') . $dir;

                return $path === '' ? $base : $base . ltrim($path, '/');
            }
        }

        $options = Typecho::options();
        if (!is_object($options)) {
            return '';
        }

        if (method_exists($options, 'adminUrl')) {
            $value = $options->adminUrl($path, true);

            return is_string($value) ? $value : '';
        }

        if (!property_exists($options, 'adminUrl')) {
            return '';
        }

        $value = $options->adminUrl;
        if (is_string($value)) {
            return $path === '' ? $value : rtrim($value, '/') . '/' . ltrim($path, '/');
        }

        return is_callable($value) ? (string) $value($path) : '';
    }

    private static function adminDirectory(): string
    {
        $root = defined('__TYPECHO_ROOT_DIR__') ? (string) constant('__TYPECHO_ROOT_DIR__') : '';
        $dir = defined('__TYPECHO_ADMIN_DIR__') ? (string) constant('__TYPECHO_ADMIN_DIR__') : '/admin/';
        if ($root === '') {
            return '';
        }

        $candidate = rtrim(str_replace('\\', '/', $root), '/') . '/' . trim(str_replace('\\', '/', $dir), '/');
        $real = realpath($candidate);

        return $real !== false && is_dir($real) ? $real : '';
    }
}
