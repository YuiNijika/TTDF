<?php
/**
 * Get Functions
 * @author 鼠子Tomoriゞ
 * @link https://blog.miomoe.cn/
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class Get {
    public static function HelloWorld() {
        echo '您已成功安装开发框架！<br>这是显示在index.php中的默认内容。';
    }
    // get站点Url
    public static function SiteUrl() {
        $SiteUrl = Helper::options()->siteUrl;
        echo $SiteUrl;
    }
    // get主题Url
    public static function ThemeUrl() {
        $ThemeUrl = Helper::options()->themeUrl;
        echo $ThemeUrl;
    }
    // getAssetsUrl
    public static function AssetsUrl() {
        $AssetsUrl = Helper::options()->themeUrl('assets');
        echo $AssetsUrl;
    }
    // get主题版本号
    public static function ThemeVer() {
        $ver = Typecho_Plugin::parseInfo(dirname(__DIR__) . '/index.php');
        echo $ver['version'];
    }
    // get框架版本号
    public static function FrameworkVer() {
        $ver = Typecho_Plugin::parseInfo(dirname(__DIR__) . '/inc/Config.php');
        echo $ver['version'];
    }
    // get设置内容
    public static function Options($param) {
        $Options = Helper::options()->$param;
        return $Options;
    }
    // get标题
    public static function Title() {
        echo \Widget_Archive::widget('Widget_Archive')->title();
    }
    // get日期
    public static function Date() {
        echo \Widget_Archive::widget('Widget_Archive')->date();
    }
    // get分类
    public static function Category() {
        echo \Widget_Archive::widget('Widget_Archive')->category(',', true, '暂无分类');
    }
    // get标签
    public static function Tags() {
        echo \Widget_Archive::widget('Widget_Archive')->tags(',', true, '暂无标签');
    }
    // get摘要
    public static function Excerpt() {
        echo \Widget_Archive::widget('Widget_Archive')->excerpt();
    }
    // get链接
    public static function Permalink() {
        echo \Widget_Archive::widget('Widget_Archive')->permalink();
    }
    // get文章内容
    public static function Content() {
        echo \Widget_Archive::widget('Widget_Archive')->content();
    }
    // get评论数
    public static function CommentsNum() {
        echo \Widget_Archive::widget('Widget_Archive')->commentsNum('暂无评论', '1 条评论', '%d 条评论');
    }
    // get文章数
    public static function PostsNum() {
        echo \Widget_Archive::widget('Widget_Archive')->postsNum();
    }
    // get页面数
    public static function PagesNum() {
        echo \Widget_Archive::widget('Widget_Archive')->pagesNum();
    }
    // get当前页码
    public static function CurrentPage() {
        echo \Widget_Archive::widget('Widget_Archive')->currentPage();
    }
    // get当前页面标题
    public static function ArchiveTitle() {
        echo \Widget_Archive::widget('Widget_Archive')->archiveTitle();
    }
    // get当前页面作者
    public static function Author() {
        echo \Widget_Archive::widget('Widget_Archive')->author();
    }
    // get当前页面作者链接
    public static function AuthorPermalink() {
        echo \Widget_Archive::widget('Widget_Archive')->author->permalink();
    }
    // get当前页面类型
    public static function Is() {
        echo \Widget_Archive::widget('Widget_Archive')->is();
    }
}
