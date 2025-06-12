<?php

/**
 * TTDF Class
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class TTDF
{
    use ErrorHandler;

    private static $timestart;
    private static $timeend;

    private function __construct() {}
    private function __clone() {}
    public function __wakeup() {}

    /**
     * 计时器开始
     * @return bool
     */
    public static function TimerStart()
    {
        $mtime = explode(' ', microtime());
        self::$timestart = $mtime[1] + $mtime[0];
        return true;
    }

    /**
     * 计时器结束
     * @param int $display 是否直接输出
     * @param int $precision 精度
     * @return string
     */
    public static function TimerStop($display = 0, $precision = 3)
    {
        $mtime = explode(' ', microtime());
        self::$timeend = $mtime[1] + $mtime[0];
        $timetotal = number_format(self::$timeend - self::$timestart, $precision);
        $r = $timetotal < 1 ? $timetotal * 1000 . " ms" : $timetotal . " s";
        if ($display) {
            echo $r;
        }
        return $r;
    }

    /**
     * HTML压缩
     * @param string $html HTML内容
     * @return string
     */
    public static function CompressHtml($html) {
        // 跳过标签
        $chunks = preg_split('/(<script.*?>.*?<\/script>|<style.*?>.*?<\/style>|<pre.*?>.*?<\/pre>)/msi', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        $compressed = '';
        foreach ($chunks as $chunk) {
            if (preg_match('/^(<script|<style|<pre)/i', $chunk)) {
                $compressed .= $chunk; // 保留原始内容
            } else {
                // 仅压缩HTML
                $chunk = preg_replace('/\s+/', ' ', $chunk); // 合并空格
                $chunk = preg_replace('/>\s+</', '><', $chunk); // 去除标签间空格
                $compressed .= $chunk;
            }
        }
        return $compressed;
    }

    /**
     * HelloWorld
     * @param bool $echo 是否输出
     */
    public static function HelloWorld(?bool $echo = true)
    {
        if ($echo) echo '您已成功安装开发框架！<br>这是显示在index.php中调用的默认内容。';
        return '您已成功安装开发框架！<br>这是显示在index.php中调用的默认内容。';
    }

    /**
     * 获取PHP版本
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
     * @param bool|null $echo 是否输出
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
     * @param bool|null $echo 是否输出
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
            if ($echo) echo TTDF::TimerStop();
            ob_start();
            echo TTDF::TimerStop();
            $content = ob_get_clean();
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
    <meta property="og:locale" content="<?php echo Get::Options('lang') ? Get::Options('lang') : 'zh-CN' ?>" />
    <meta property="og:type" content="website" />
    <meta property="og:url" content="<?php Get::PageUrl(); ?>" />
    <meta property="og:site_name" content="<?php Get::Options('title', true) ?>" />
    <meta property="og:title" content="<?php TTDF_SEO_Title(); ?>" />
    <meta name="og:description" content="<?php TTDF_SEO_Description(); ?>" />
    <meta name="twitter:card" content="summary" />
    <meta name="twitter:domain" content="<?php Get::Options('siteDomain', true) ?>" />
    <meta name="twitter:title" property="og:title" itemprop="name" content="<?php TTDF_SEO_Title(); ?>" />
    <meta name="twitter:description" property="og:description" itemprop="description" content="<?php TTDF_SEO_Description(); ?>" />
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
        Get::Header(true, 'description,keywords,generator,template,pingback,EditURI,wlwmanifest,alternate,twitter:domain,twitter:card,twitter:description,twitter:title,og:url,og:site_name,og:type');
?>
    <link rel="canonical" href="<?php Get::PageUrl(true, false, null, true); ?>" />
<?php
    }
}

// 初始化计时器
TTDF::TimerStart();

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