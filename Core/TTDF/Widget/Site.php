<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
class Site
{
    use ErrorHandler;

    private function __construct() {}
    private function __clone() {}
    public function __wakeup() {}

    /**
     * 输出站点名称
     * @param bool $echo 是否输出
     * @return string
     */
    public static function Name(?bool $echo = true)
    {
        try {
            $name = Get::Options('title', true);
            if ($echo) echo $name;
            return $name;
        } catch (Exception $e) {
            return self::handleError('获取站点名称失败', $e);
        }
    }

    /**
     * 输出站点描述
     * @param bool $echo 是否输出
     * @return string
     */
    public static function Description(?bool $echo = true)
    {
        try {
            $description = Get::Options('description', true);
            if ($echo) echo $description;
            return $description;
        } catch (Exception $e) {
            return self::handleError('获取站点描述失败', $e);
        }
    }

    /**
     * 输出站点关键字
     * @param bool $echo 是否输出
     * @return string
     */
    public static function Keywords(?bool $echo = true)
    {
        try {
            $keywords = Get::Options('keywords', true);
            if ($echo) echo $keywords;
            return $keywords;
        } catch (Exception $e) {
            return self::handleError('获取站点关键字失败', $e);
        }
    }

    /**
     * 输出站点语言
     * @param bool $echo 是否输出
     * @return string
     */
    public static function Language(?bool $echo = true)
    {
        try {
            $language = Get::Options('lang', true);
            if ($echo) echo $language;
            return $language;
        } catch (Exception $e) {
            return self::handleError('获取站点语言失败', $e);
        }
    }

    /**
     * 输出站点编码
     * @param bool $echo 是否输出
     * @return string
     */
    public static function Charset(?bool $echo = true)
    {
        try {
            $charset = Get::Options('charset', true);
            if ($echo) echo $charset;
            return $charset;
        } catch (Exception $e) {
            return self::handleError('获取站点编码失败', $e);
        }
    }

    /**
     * 输出站点URL
     * @param bool $echo 是否输出
     * @return string
     */
    public static function Url(?bool $echo = true)
    {
        try {
            $url = Get::Options('siteUrl', true);
            if ($echo) echo $url;
            return $url;
        }catch (Exception $e) {
            return self::handleError('获取站点URL失败', $e);
        }
    }

    /**
     * 输出页面URL
     * @param bool $echo 是否输出
     * @return string
     */
    public static function PageUrl(?bool $echo = true)
    {
        try {
            $pageUrl = Get::PageUrl(true, false, null, true);
            if ($echo) echo $pageUrl;
            return $pageUrl;
        } catch (Exception $e) {
            return self::handleError('获取页面URL失败', $e);
        }
    }

    /**
     * 输出站点主题
     * @param bool $echo 是否输出
     * @return string
     */
    public static function Theme(?bool $echo = true)
    {
        try {
            $theme = Get::Options('theme', true);
            if ($echo) echo $theme;
            return $theme;
        } catch (Exception $e) {
            return self::handleError('获取站点主题失败', $e);
        }
    }
}