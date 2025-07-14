<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

define('__TYPECHO_GRAVATAR_PREFIX__', $defineConfig['GravatarPrefix'] ?? 'https://cravatar.cn/avatar/');
define('__TTDF_RESTAPI__', !empty($defineConfig['RestApi']));
define('__TTDF_RESTAPI_ROUTE__', $defineConfig['RestApiRoute'] ?? 'ty-json');

trait ErrorHandler
{
    protected static function handleError($message, $e, $defaultValue = '', $logLevel = E_USER_WARNING)
    {
        error_log($message . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        return $defaultValue;
    }
}

trait SingletonWidget
{
    private static $widget;

    private static function getArchive()
    {
        if (self::$widget === null) {
            try {
                self::$widget = \Widget\Archive::widget('Widget_Archive');
            } catch (Exception $e) {
                throw new Exception('初始化Widget失败: ' . $e->getMessage());
            }
        }
        return self::$widget;
    }
}

class TTDF_Main
{
    use ErrorHandler;

    private static $loadedModules = [];

    public static function run()
    {
        $widgetFiles = [
            'DB.php',
            'Tools.php',
            'Get.php',
            'Site.php',
            'GetTheme.php',
            'GetPost.php',
            'Comment.php',
            'GetUser.php',
            'UserInfo.php',
            'TTDF.php'
        ];

        $moduleFiles = [
            'OPP.php',
            'Api.php',
            'FormElement.php',
            'Options.php'
        ];

        foreach ($widgetFiles as $file) {
            require_once __DIR__ . '/Widget/' . $file;
        }

        foreach ($moduleFiles as $file) {
            require_once __DIR__ . '/Modules/' . $file;
        }

        if (!empty($GLOBALS['defineConfig']['TyAjax'])) {
            require_once __DIR__ . '/Modules/TyAjax.php';
        }

        if (!empty($GLOBALS['defineConfig']['Fields'])) {
            require_once __DIR__ . '/Modules/Fields.php';
        }

        if (!isset($GLOBALS['defineConfig'])) {
            throw new RuntimeException('TTDF配置未初始化');
        }
    }

    public static function init()
    {
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            die('PHP版本需要7.4及以上, 请先升级!');
        }

        self::run();

        // 获取全局配置
        $defineConfig = $GLOBALS['defineConfig'];

        define('__FRAMEWORK_VER__', '2.3.4');

        // 在初始化时注册HTML压缩钩子
        if (!empty($defineConfig['CompressHtml'])) {
            ob_start(function ($buffer) {
                return TTDF::CompressHtml($buffer);
            });
        }
    }
}

TTDF_Main::init();
