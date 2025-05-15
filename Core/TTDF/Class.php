<?php

/**
 * Class Functions
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

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
/** Widget */
require_once 'Widget/DB.php';
require_once 'Widget/Tools.php';
require_once 'Widget/Get.php';
require_once 'Widget/GetTheme.php';
require_once 'Widget/GetPost.php';
require_once 'Widget/Comment.php';
require_once 'Widget/UserInfo.php';
require_once 'Widget/ClassTTDF.php';
require_once 'Widget/Widget.php';
require_once 'Widget/TyAjax.php';
/** API */
require_once 'Widget/Api.php';
require_once 'Widget/Functions.php';
require_once 'Widget/Options.php';