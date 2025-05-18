<?php

/**
 * 主题配置文件
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class Main
{
    public static function init()
    {
        // 引入框架配置文件
        require_once 'Core/TTDF.Config.php';
        // 引入主题自定义代码
        if(file_exists(__DIR__ . '/Core/Code.php')) {
            // 先在`Code`目录下创建`Code.php`文件
            require_once 'Core/Code.php';
        }
    }
}
Main::init();