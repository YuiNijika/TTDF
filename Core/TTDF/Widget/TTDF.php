<?php

/**
 * TTDF Class
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * 加载时间
 * @return bool
 */
function TTDF_TimerStart()
{
    global $timestart;
    $mtime     = explode(' ', microtime());
    $timestart = $mtime[1] + $mtime[0];
    return true;
}
TTDF_TimerStart();
function TTDF_TimerStop($display = 0, $precision = 3)
{
    global $timestart, $timeend;
    $mtime     = explode(' ', microtime());
    $timeend   = $mtime[1] + $mtime[0];
    $timetotal = number_format($timeend - $timestart, $precision);
    $r         = $timetotal < 1 ? $timetotal * 1000 . " ms" : $timetotal . " s";
    if ($display) {
        echo $r;
    }
    return $r;
}
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
    public static function Modules($TTDF)
    {
        require_once __DIR__ . '/../Modules/' .  $TTDF . '.php';
    }
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

class TTDF_Widget
{
    use ErrorHandler;

    private function __construct() {}
    private function __clone() {}
    public function __wakeup() {}

    public static function TimerStop(?bool $echo = true)
    {
        try {
            if ($echo) echo TTDF_TimerStop();
            ob_start();  // 开启输出缓冲
            echo TTDF_TimerStop();
            $content = ob_get_clean();  // 获取缓冲区内容并清除缓冲区
            return $content;
        } catch (Exception $e) {
            return self::handleError('获取加载时间失败', $e);
        }
    }

    /**
     * SEO
     * @return string
     */
    public static function SEO($OG = true)
    {
        TTDF::Modules('UseSeo');
    if ($OG) { ?>
    <meta name="og:description" content="<?php echo TTDF_SEO_Description(); ?>" />
    <meta property="og:locale" content="<?php echo Get::Options('lang') ? Get::Options('lang') : 'zh-CN' ?>" />
    <meta property="og:type" content="website" />
    <meta property="og:site_name" content="<?php Get::Options('title', true) ?>" />
    <meta property="og:title" content="<?php echo TTDF_SEO_Title(); ?>" />
    <meta property="og:url" content="<?php Get::PageUrl(); ?>" />
    <?php }
    }

    /**
     * HeadMeta
     * @return string
     */
    public static function HeadMeta($HeadSeo = true)
    {
?>
<meta charset="<?php Get::Options('charset', true) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, shrink-to-fit=no" />
    <meta name="renderer" content="webkit" />
    <?php if ($HeadSeo) { self::SEO(); } ?>
<meta name="generator" content="Typecho <?php TTDF::TypechoVer(true) ?>" />
    <meta name="framework" content="TTDF <?php TTDF::Ver(true) ?>" />
    <meta name="template" content="<?php GetTheme::Name(true) ?>" />
<?php 
        Get::Header(true, 'description,keywords,generator,template,pingback,EditURI,wlwmanifest,alternate');
?>
    <link rel="canonical" href="<?php Get::PageUrl(true, false, null, true); ?>" />
<?php
    }
}

/**
 * 默认钩子
 * 添加头部元信息
 */
TTDF_Hook::add_action('load_head', function ($skipHead = false) {
    TTDF_Widget::HeadMeta();
});
TTDF_Hook::add_action('load_foot', function () {
    Get::Footer(true);
    ?>
<script type="text/javascript">
        console.log("\n %c %s \n", "color: #fff; background: #34495e; padding:5px 0;", "TTDF v<?php TTDF::Ver() ?>");
        console.log('页面加载耗时 <?php TTDF_Widget::TimerStop(); ?>');
    </script>
<?php
});
