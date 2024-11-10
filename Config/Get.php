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

    // 获取站点URL
    public static function SiteUrl() {
        echo Helper::options()->siteUrl;
    }

    // 获取主题Assets URL
    public static function AssetsUrl() {
        echo Helper::options()->themeUrl('Assets');
    }

    // 获取框架版本号
    public static function FrameworkVer() {
        $ver = Typecho_Plugin::parseInfo(dirname(__DIR__) . '/Config/Config.php');
        echo $ver['version'];
    }

    // 获取Typecho版本号
    public static function TypechoVer() {
        echo Helper::options()->Version;
    }

    // 获取设置内容
    public static function Options($param) {
        return Helper::options()->$param;
    }

    // 引入文件
    public static function Need($file) {
        Typecho_Widget::widget('Widget_Archive')->need($file);
    }

    // 获取当前页面类型
    public static function Is() {
        echo \Widget_Archive::widget('Widget_Archive')->is();
    }
}

/**
 * Get Theme Functions
 */
class GetTheme {
    // 获取主题URL
    public static function Url() {
        echo Helper::options()->themeUrl;
    }

    // 获取主题名称
    public static function Name() {
        echo Helper::options()->theme;
    }

    // 获取主题作者
    public static function Author() {
        $author = Typecho_Plugin::parseInfo(dirname(__DIR__) . '/index.php');
        echo $author['author'];
    }

    // 获取主题版本号
    public static function Ver() {
        $ver = Typecho_Plugin::parseInfo(dirname(__DIR__) . '/index.php');
        echo $ver['version'];
    }
}

/**
 * Get Post Functions
 */
class GetPost {
    // 静态变量保存Widget实例
    private static $widget;

    // 获取Widget实例
    private static function getWidget() {
        if (is_null(self::$widget)) {
            self::$widget = \Widget_Archive::widget('Widget_Archive');
        }
        return self::$widget;
    }

    // 获取标题
    public static function Title() {
        echo self::getWidget()->title;
    }

    // 获取日期
    public static function Date() {
        echo self::getWidget()->date('Y-m-d');
    }

    // 获取分类
    public static function Category() {
        echo self::getWidget()->category(',', true, '暂无分类');
    }

    // 获取标签
    public static function Tags() {
        echo self::getWidget()->tags(',', true, '暂无标签');
    }

    // 获取摘要
    public static function Excerpt() {
        echo self::getWidget()->excerpt;
    }

    // 获取链接
    public static function Permalink() {
        echo self::getWidget()->permalink;
    }

    // 获取文章内容
    public static function Content() {
        echo self::getWidget()->content;
    }

    // 获取评论数
    public static function CommentsNum() {
        echo self::getWidget()->commentsNum('暂无评论', '1 条评论', '%d 条评论');
    }

    // 获取文章数
    public static function PostsNum() {
        echo self::getWidget()->postsNum;
    }

    // 获取页面数
    public static function PagesNum() {
        echo self::getWidget()->pagesNum;
    }

    // 获取当前页码
    public static function CurrentPage() {
        echo self::getWidget()->currentPage;
    }

    // 获取当前页面标题
    public static function ArchiveTitle() {
        echo self::getWidget()->archiveTitle;
    }

    // 获取当前页面作者
    public static function Author() {
        echo self::getWidget()->author;
    }

    // 获取当前页面作者链接
    public static function AuthorPermalink() {
        echo self::getWidget()->author->permalink;
    }
}
