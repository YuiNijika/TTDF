<?php

/**
 * TTDF Class
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
class TTDF
{

    use ErrorHandler;

    private function __construct() {}
    private function __clone() {}
    public function __wakeup() {}
    /**
     * HelloWorld
     * 
     * @param bool $echo 是否输出
     */
    public static function HelloWorld(?bool $echo = true)
    {
        if ($echo) echo '您已成功安装开发框架！<br>这是显示在index.php中调用的默认内容。';

        return '您已成功安装开发框架！<br>这是显示在index.php中调用的默认内容。';
    }

    /**
     * 获取PHP版本
     * 
     * @param bool $echo 是否输出
     * @return string
     */
    public static function PHPVer(?bool $echo = true)
    {
        try {
            $PHPVer = PHP_VERSION;

            if ($echo) echo $PHPVer;

            return $PHPVer;
        } catch (Exception $e) {
            return self::handleError('获取PHP版本失败', $e);
        }
    }


    /**
     * 获取框架版本
     *
     * @param bool|null $echo 当设置为 true 时，会直接输出；
     *                        当设置为 false 时，则返回结果值。
     * @return string|null 
     * @throws Exception
     */
    public static function Ver(?bool $echo = true)
    {
        try {
            $FrameworkVer = __FRAMEWORK_VER__;

            if ($echo) echo $FrameworkVer;

            return $FrameworkVer;
        } catch (Exception $e) {
            return self::handleError('获取框架版本失败', $e);
        }
    }

    /**
     * 获取 typecho 版本
     *
     * @param bool|null $echo 当设置为 true 时，会直接输出；
     *                        当设置为 false 时，则返回结果值。
     * @return string|null 
     * @throws Exception
     */
    public static function TypechoVer(?bool $echo = true)
    {
        try {
            $TypechoVer = \Helper::options()->Version;

            if ($echo) echo $TypechoVer;

            return $TypechoVer;
        } catch (Exception $e) {
            return self::handleError('获取Typecho版本失败', $e);
        }
    }

    /**
     * 引入函数库
     * 
     * @param string $TTDF
     */
    public static function WidgetFile($TTDF)
    {
        require_once $TTDF . '.php';
    }
    /**
     * HeadMeta
     */
    public static function HeadMeta($skipHead = false)
    {
?>
        <meta name="renderer" content="webkit" />
        <meta name="generator" content="Typecho <?php TTDF::TypechoVer(true) ?>" />
        <meta name="framework" content="TTDF <?php TTDF::Ver(true) ?>" />
        <meta name="template" content="<?php GetTheme::Name(true) ?>" />
    <?php
    }
    /**
     * HeadMetaOG
     */
}

/**
 * 钩子类
 */
class TTDF_Hook
{
    private static $actions = [];

    /**
     * 注册钩子
     * @param string $hook_name 钩子名称
     * @param callable $callback 回调函数
     */
    public static function add_action($hook_name, $callback)
    {
        if (!isset(self::$actions[$hook_name])) {
            self::$actions[$hook_name] = [];
        }
        self::$actions[$hook_name][] = $callback;
    }

    /**
     * 执行钩子
     * @param string $hook_name 钩子名称
     * @param mixed $args 传递给回调函数的参数
     */
    public static function do_action($hook_name, $args = null)
    {
        if (isset(self::$actions[$hook_name])) {
            foreach (self::$actions[$hook_name] as $callback) {
                call_user_func($callback, $args);
            }
        }
    }
}
