<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// 配置文件加载
$configPath = __DIR__ . '/../app/TTDF.Config.php';
if (!file_exists($configPath)) {
    throw new RuntimeException('TTDF配置文件未找到! 请检查路径: ' . $configPath);
}

$TTDF_CONFIG = require $configPath;
if (!is_array($TTDF_CONFIG)) {
    throw new RuntimeException('TTDF配置文件格式无效');
}

define('TTDF_CONFIG', $TTDF_CONFIG);
define('__FRAMEWORK_VER__', '3.0.1_fix');
define('__TYPECHO_GRAVATAR_PREFIX__', TTDF_CONFIG['GRAVATAR_PREFIX'] ?? 'https://cravatar.cn/avatar/');
define('__TTDF_RESTAPI__', TTDF_CONFIG['REST_API']['ENABLED'] ?? false);
define('__TTDF_RESTAPI_ROUTE__', TTDF_CONFIG['REST_API']['ROUTE'] ?? 'ty-json');

/**
 * 错误处理Trait
 */
trait ErrorHandler
{
    protected static function handleError($message, $e, $defaultValue = '', $logLevel = E_USER_WARNING)
    {
        $errorMessage = $message . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
        error_log($errorMessage);
        
        if (TTDF_CONFIG['DEBUG'] ?? false) {
            trigger_error($errorMessage, $logLevel);
        }
        
        return $defaultValue;
    }
}

/**
 * 单例Widget Trait
 */
trait SingletonWidget
{
    /** @var Widget\Archive|null */
    private static $widget;

    private static function getArchive()
    {
        if (self::$widget === null) {
            try {
                self::$widget = \Widget\Archive::widget('Widget_Archive');
            } catch (Exception $e) {
                throw new RuntimeException('初始化Widget失败: ' . $e->getMessage(), 0, $e);
            }
        }
        return self::$widget;
    }
}

class TTDF_Main
{
    use ErrorHandler;

    /** @var array 已加载模块 */
    private static $loadedModules = [];

    /**
     * 运行框架
     */
    public static function run()
    {
        // 加载核心模块
        self::loadCoreModules();
        
        // 加载Widgets
        self::loadWidgets();
        
        // 加载可选模块
        self::loadOptionalModules();
        
        // 配置检查
        if (!defined('TTDF_CONFIG')) {
            throw new RuntimeException('TTDF配置未初始化');
        }
    }

    /**
     * 加载核心模块
     */
    private static function loadCoreModules()
    {
        require_once __DIR__ . '/Modules/Database.php';
        
        if (TTDF_CONFIG['DEBUG'] ?? false) {
            require_once __DIR__ . '/Modules/Debug.php';
        }
    }

    /**
     * 加载Widgets
     */
    private static function loadWidgets()
    {
        $widgetFiles = [
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

        foreach ($widgetFiles as $file) {
            require_once __DIR__ . '/Widget/' . $file;
        }
    }

    /**
     * 加载可选模块
     */
    private static function loadOptionalModules()
    {
        $moduleFiles = [
            'OPP.php',
            'Api.php',
            'Function.php',
            'Options.php',
            'RouterAuto.php',
        ];

        foreach ($moduleFiles as $file) {
            require_once __DIR__ . '/Modules/' . $file;
        }

        if (TTDF_CONFIG['TYAJAX_ENABLED'] ?? false) {
            require_once __DIR__ . '/Widget/TyAjax.php';
        }
    }

    /**
     * 初始化框架
     */
    public static function init()
    {
        // PHP版本检查
        if (version_compare(PHP_VERSION, '8.1', '<')) {
            exit('PHP版本需要8.1及以上, 请先升级!');
        }

        // 运行框架
        self::run();

        // HTML压缩
        if (TTDF_CONFIG['COMPRESS_HTML'] ?? false) {
            ob_start(function ($buffer) {
                return TTDF::compressHtml($buffer);
            });
        }
    }
}

// 初始化框架
try {
    TTDF_Main::init();
} catch (Exception $e) {
    if (TTDF_CONFIG['DEBUG'] ?? false) {
        throw $e;
    }
    error_log('Framework init error: ' . $e->getMessage());
    exit('系统初始化失败');
}