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

require_once 'Functions/ClassDB.php';
require_once 'Functions/ClassGet.php';
require_once 'Functions/ClassGetTheme.php';
require_once 'Functions/ClassGetPost.php';
require_once 'Functions/ClassGetAuthor.php';
require_once 'Functions/ClassGetComments.php';
require_once 'Functions/ClassGetFunctions.php';
require_once 'Functions/ClassTools.php';
require_once 'Functions/ClassTTDF.php';
