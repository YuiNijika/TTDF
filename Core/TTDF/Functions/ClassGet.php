<?php

/**
 * Get Class
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class Get
{
    use ErrorHandler, SingletonWidget;

    private function __construct() {}
    private function __clone() {}
    public function __wakeup() {}

    /**
     * 输出header头部元数据
     * 
     * 此方法会基于一组预定义的键名来过滤相关数据（预定义键名如下：
     * - 'description'
     * - 'keywords'
     * - 'generator'
     * - 'template'
     * - 'pingback'
     * - 'xmlrpc'
     * - 'wlw'
     * - 'rss2'
     * - 'rss1'
     * - 'commentReply'
     * - 'antiSpam'
     * - 'social'
     * - 'atom'
     * ），若传递符合这些预定义键名对应的值，则起到过滤这些值的作用。
     *
     * @param string|null $rule 规则
     * @param bool|null $echo 当设置为 true 时，会直接输出；
     *                        当设置为 false 时，则返回结果值。
     * @return string 头部信息输出
     * @throws self::handleError()
     */
    public static function Header(?bool $echo = true, ?string $rule = null)
    {
        try {
            if ($echo) self::getArchive()->header($rule);

            ob_start();  // 开启输出缓冲
            self::getArchive()->header($rule);
            $content = ob_get_clean();  // 获取缓冲区内容并清除缓冲区

            return $content;
        } catch (Exception $e) {
            return self::handleError('获取Header失败', $e);
        }
    }

    /**
     * 执行页脚自定义内容
     * 即输出 self::pluginHandle()->call('footer', $this); footer钩子。
     * 
     * @return mixed
     */
    public static function Footer()
    {
        try {
            ob_start();
            $Footer = self::getArchive()->footer();
            $content = ob_get_clean();

            if (!empty($content)) return $Footer;

            return self::getArchive()->footer();
        } catch (Exception $e) {
            return self::handleError('获取Footer失败', $e);
        }
    }

    /**
     * 获取站点URL
     * 
     * @param bool|null $echo 当设置为 true 时，会直接输出；
     *                        当设置为 false 时，则返回结果值。
     * @return string
     */
    public static function SiteUrl(?bool $echo = true)
    {
        try {
            $SiteUrl = \Helper::options()->siteUrl;

            if ($echo) echo $SiteUrl;

            return $SiteUrl;
        } catch (Exception $e) {
            return self::handleError('获取站点URL失败', $e);
        }
    }
    /**
     * 获取站点名称
     * 
     * @param bool|null $echo 当设置为 true 时，会直接输出；
     *                        当设置为false 时，则返回结果值。
     * @return string
     */
    public static function SiteName(?bool $echo = true)
    {
        try {
            $SiteName = \Helper::options()->title;

            if ($echo) echo $SiteName;

            return $SiteName;
        } catch (Exception $e) {
            return self::handleError('获取站点名称失败', $e);
        }
    }
    /**
     * 获取站点关键字
     */
    public static function SiteKeywords(?bool $echo = true)
    {
        try {
            $SiteKeywords = \Helper::options()->keywords;

            if ($echo) echo $SiteKeywords;

            return $SiteKeywords;
        } catch (Exception $e) {
            return self::handleError('获取站点关键字失败', $e);
        }
    }
    /**
     * 获取站点描述
     * 
     * @param bool|null $echo 当设置为 true 时，会直接输出；
     *                        当设置为 false 时，则返回结果值。
     * @return string
     */
    public static function SiteDescription(?bool $echo = true)
    {
        try {
            $SiteDescription = \Helper::options()->description;

            if ($echo) echo $SiteDescription;

            return $SiteDescription;
        } catch (Exception $e) {
            return self::handleError('获取站点描述失败', $e);
        }
    }

    /**
     * 返回堆栈（数组）中每一行的值
     * 一般用于循环输出文章
     *
     * @return mixed
     */
    public static function Next()
    {
        try {
            if (method_exists(self::getArchive(), 'Next')) {
                return self::getArchive()->Next();
            }
            throw new Exception('Next 方法不存在');
        } catch (Exception $e) {
            return self::handleError('Next 调用失败', $e, null);
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
    public static function FrameworkVer(?bool $echo = true)
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

    // 获取配置参数
    public static function Options($param, ?bool $echo = false)
    {
        try {
            $value = Helper::options()->$param;

            if ($echo) {
                echo $value;
            }

            return $value;
        } catch (Exception $e) {
            return self::handleError('获取配置参数失败', $e);
        }
    }

    // 获取字段
    public static function Fields($param)
    {
        try {
            return self::getArchive()->fields->$param;
        } catch (Exception $e) {
            return self::handleError('获取字段失败', $e);
        }
    }

    /**
     * 引入文件
     * 
     * @param string $file 文件名
     * @return mixed
     */
    public static function Need($file)
    {
        try {
            return self::getArchive()->need($file);
        } catch (Exception $e) {
            return self::handleError('获取文件失败', $e);
        }
    }

    /**
     * 拼接文件路径
     * 
     * @param string $base 基础路径
     * @param string $file 文件名
     * @return string
     */
    private static function buildFilePath($base, $file)
    {
        return $base . '/' . $file . '.php';
    }

    // 引入文件
    public static function File($file)
    {
        return self::Need(self::buildFilePath('', $file));
    }

    // 引入Template目录文件
    public static function Template($file)
    {
        return self::Need(self::buildFilePath('Template', $file));
    }

    // 引入Core文件
    public static function Core($file)
    {
        return self::Need(self::buildFilePath('Core', $file));
    }

    public static function CoreFunctions($file)
    {
        return self::Need(self::buildFilePath('Core/TTDF/Functions', $file));
    }

    // 判断页面类型
    public static function Is($type)
    {
        try {
            return self::getArchive()->is($type);
        } catch (Exception $e) {
            return self::handleError('判断页面类型失败', $e, false);
        }
    }

    // 分页导航
    public static function PageNav($prev = '&laquo; 前一页', $next = '后一页 &raquo;')
    {
        try {
            self::getArchive()->pageNav($prev, $next);
        } catch (Exception $e) {
            self::handleError('分页导航失败', $e);
        }
    }

    // 获取总数
    public static function Total()
    {
        try {
            return self::getArchive()->getTotal();
        } catch (Exception $e) {
            return self::handleError('获取总数失败', $e, 0);
        }
    }

    // 获取页面大小
    public static function PageSize()
    {
        try {
            return self::getArchive()->parameter->pageSize;
        } catch (Exception $e) {
            return self::handleError('获取页面大小失败', $e, 10);
        }
    }

    // 获取页面链接
    public static function PageLink($html = '', $next = '')
    {
        try {
            $widget = self::getArchive();
            if ($widget->have()) {
                $link = ($next === 'next') ? $widget->pageLink($html, 'next') : $widget->pageLink($html);
                echo $link;
            }
        } catch (Exception $e) {
            self::handleError('获取页面链接失败', $e);
        }
    }

    // 获取当前页码
    public static function CurrentPage()
    {
        try {
            return self::getArchive()->_currentPage;
        } catch (Exception $e) {
            return self::handleError('获取当前页码失败', $e, 1);
        }
    }

    // 获取页面Permalink
    public static function Permalink()
    {
        try {
            return self::getArchive()->permalink();
        } catch (Exception $e) {
            return self::handleError('获取页面Url失败', $e);
        }
    }
}
