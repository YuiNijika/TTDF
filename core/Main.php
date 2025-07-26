<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

if (file_exists(__DIR__ . '/../app/TTDF.Config.php')) {
    require_once __DIR__ . '/../app/TTDF.Config.php';
} else {
    throw new Exception('TTDF配置文件未找到! 请检查路径: ' . __DIR__ . '/../app/TTDF.Config.php');
}

define('__FRAMEWORK_VER__', '3.0.0_rc');
define('__TYPECHO_GRAVATAR_PREFIX__', TTDF_CONFIG['GRAVATAR_PREFIX'] ?? 'https://cravatar.cn/avatar/');
define('__TTDF_RESTAPI__', TTDF_CONFIG['REST_API']['ENABLED'] ?? false);
define('__TTDF_RESTAPI_ROUTE__', TTDF_CONFIG['REST_API']['ROUTE'] ?? 'ty-json');

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
            'TTDF.php',
            'AddRoute.php',
            'Get/Common.php',
            'Get/Site.php',
            'Get/Post.php',
            'Get/Theme.php',
            'Get/User.php',
            'Get/Comment.php',
        ];

        $moduleFiles = [
            'OPP.php',
            'Api.php',
            'Router.php',
            'Function.php',
            'Options.php'
        ];

        if (TTDF_CONFIG['DEBUG']) {
            require_once __DIR__ . '/Modules/Debug.php';
        }

        foreach ($widgetFiles as $file) {
            require_once __DIR__ . '/Widget/' . $file;
        }

        foreach ($moduleFiles as $file) {
            require_once __DIR__ . '/Modules/' . $file;
        }

        if (TTDF_CONFIG['TYAJAX_ENABLED']) {
            require_once __DIR__ . '/Widget/TyAjax.php';
        }

        if (!TTDF_CONFIG) {
            throw new RuntimeException('TTDF配置未初始化');
        }
    }

    public static function init()
    {
        if (version_compare(PHP_VERSION, '8.1', '<')) {
            die('PHP版本需要8.1及以上, 请先升级!');
        }

        self::run();

        // 在初始化时注册HTML压缩钩子
        if (TTDF_CONFIG['COMPRESS_HTML']) {
            ob_start(function ($buffer) {
                return TTDF::CompressHtml($buffer);
            });
        }
    }
}

TTDF_Main::init();
