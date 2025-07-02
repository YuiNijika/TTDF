<?php
/**
 * 感谢选择`TTDF`进行开发
 * 如需自定义代码请在`/Core/Code.php`中编写
 * 框架的基础配置在`/Core/TTDF.Config.php`中修改
 * 如果觉得还不错的话请给`TTDF`点个 *Star* 谢谢!
 * @link https://github.com/ShuShuicu/TTDF
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class Main
{
    public static function init()
    {
        // 引入框架配置文件
        require_once __DIR__ . '/Core/TTDF.Config.php';
        // 引入主题自定义代码
        if(file_exists(__DIR__ . '/Core/Code.php')) {
            // 先在`Core`目录下创建`Code.php`文件
            require_once __DIR__ . '/Core/Code.php';
        }
    }
}
Main::init();