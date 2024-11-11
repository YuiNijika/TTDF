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
        return Helper::options()->siteUrl;
    }

    public static function AssetsUrl() {
        return Helper::options()->themeUrl('Assets');
    }

    // 添加错误处理
    public static function FrameworkVer() {
        try {
            $ver = Typecho_Plugin::parseInfo(dirname(__DIR__) . '/Config/Config.php');
            echo $ver['version'] ?? '未知版本';
        } catch (Exception $e) {
            echo '获取版本失败';
        }
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
        return \Widget_Archive::widget('Widget_Archive')->need($file);
    }

    // 获取当前页面类型
    public static function Is($type) {
        return \Widget_Archive::widget('Widget_Archive')->is($type);
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
    private static $widget;

    // 优化单例模式实现
    private static function getWidget() {
        if (is_null(self::$widget)) {
            try {
                self::$widget = \Widget_Archive::widget('Widget_Archive');
            } catch (Exception $e) {
                throw new Exception('无法初始化Widget实例');
            }
        }
        return self::$widget;
    }

    // 获取标题
    public static function Title() {
        try {
            return self::getWidget()->title;
        } catch (Exception $e) {
            return '';
        }
    }

    // 获取日期
    // 添加格式化选项
    public static function Date($format = 'Y-m-d') {
        try {
            return self::getWidget()->date($format);
        } catch (Exception $e) {
            return '';
        }
    }

    // 获取分类
    public static function Category() {
        echo self::getWidget()->category();
    }

    // 获取标签
    public static function Tags() {
        echo self::getWidget()->tags();
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
        echo self::getWidget()->commentsNum();
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
    public static function ArchiveTitle($format = '', $default = '', $connector = '') {
        if (empty($format)) {
            echo self::getWidget()->archiveTitle;
        } else {
            echo self::getWidget()->archiveTitle($format, $default, $connector);
        }
    }

    // 获取当前页面作者
    public static function Author() {
        echo self::getWidget()->author->screenName;
    }

    // 获取当前页面作者链接
    public static function AuthorPermalink() {
        echo self::getWidget()->author->permalink;
    }
}

class GetFunctions {
    // 获取加载时间
    public static function TimerStop() {
        echo timer_stop();
    }
    // 统计字数
    // 添加参数验证
    public static function ArtCount($cid) {
        if (!is_numeric($cid)) {
            return 0;
        }
        return art_count($cid);
    }
}

// GetJsonData
class GetJsonData {   
    // 获取并输出 JSON 数据中的标题
    public static function JsonTitle($data) {
        if (!is_array($data)) {
            return '无效的数据格式';
        }
        return isset($data['title']) 
            ? htmlspecialchars($data['title'], ENT_QUOTES, 'UTF-8')
            : '暂无标题';
    }

    // 获取并输出 JSON 数据中的内容
    public static function JsonContent($data) {
        if (!is_array($data)) {
            return '无效的数据格式';
        }
        return isset($data['content'])
            ? htmlspecialchars($data['content'], ENT_QUOTES, 'UTF-8')
            : '暂无内容';
    }

    // 获取并输出 JSON 数据中的发布时间
    public static function JsonDate($data) {
        if (!is_array($data)) {
            return '无效的数据格式';
        }
        return isset($data['date'])
            ? htmlspecialchars($data['date'], ENT_QUOTES, 'UTF-8')
            : '暂无日期';
    }

    // 获取并输出 JSON 数据中的 URL
    public static function JsonUrl($data) {
        if (!is_array($data)) {
            return '无效的数据格式';
        }
        return isset($data['url'])
            ? htmlspecialchars($data['url'], ENT_QUOTES, 'UTF-8')
            : '暂无链接';
    }
}