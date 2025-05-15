<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
class TTDF_Widget
{
    use ErrorHandler;

    private function __construct() {}
    private function __clone() {}
    public function __wakeup() {}

    /**
     * 获取加载时间
     *  @param bool|null $echo 当设置为 true 时，会直接输出；
     *                        当设置为 false 时，则返回结果值。
     */
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
        TTDF::WidgetFile('HeadSeo');
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
