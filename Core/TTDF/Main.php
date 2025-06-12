<?php

/**
 * Class Functions
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

if (version_compare(PHP_VERSION, '7.4', '<')) {
    die('您的PHP版本低于7.4, 请升级PHP版本!');
}

// 设置框架版本
define('__FRAMEWORK_VER__', '2.3.2'); 
// 配置默认头像源
define('__TYPECHO_GRAVATAR_PREFIX__', $defineTTDFConfig['Modules']['GravatarPrefix'] ?? 'https://cravatar.cn/avatar/');
// 主题注册设置项为 * TTDF_RESTAPI_Switch * 则代理启用REST API
define('__TTDF_RESTAPI__', $defineTTDFConfig['Modules']['RestApi'] ?? false);
define('__TTDF_RESTAPI_ROUTE__', $defineTTDFConfig['Modules']['RestApiRoute'] ?? 'ty-json');

trait ErrorHandler
{
    protected static function handleError($message, $e, $defaultValue = '')
    {
        error_log($message . ': ' . $e->getMessage());
        return $defaultValue;
    }
}

trait SingletonWidget
{
    private static $widget;

    private static function getArchive()
    {
        if (is_null(self::$widget)) {
            try {
                self::$widget = \Widget\Archive::widget('Widget_Archive');
            } catch (Exception $e) {
                throw new Exception('无法初始化 Widget 实例: ' . $e->getMessage());
            }
        }
        return self::$widget;
    }
}
class TTDF_Main
{
    use ErrorHandler;

    public static function init()
    {
        /** Widget */
        require_once 'Widget/DB.php';
        require_once 'Widget/Tools.php';
        require_once 'Widget/Get.php';
        require_once 'Widget/Site.php';
        require_once 'Widget/GetTheme.php';
        require_once 'Widget/GetPost.php';
        require_once 'Widget/Comment.php';
        require_once 'Widget/GetUser.php';
        require_once 'Widget/UserInfo.php';
        require_once 'Widget/TTDF.php';
        /** Modules */
        require_once 'Modules/OPP.php';
        require_once 'Modules/Api.php';
        if ($defineTTDFConfig['Modules']['TyAjax'] ?? false) {
            require_once 'Modules/TyAjax.php';
        }
        require_once 'Modules/FormElement.php';
        require_once 'Modules/Options.php';
        if ($defineTTDFConfig['Modules']['Fields'] ?? false) {
            require_once 'Modules/Fields.php';
        }
    }
}
TTDF_Main::init();