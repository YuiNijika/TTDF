<?php
/**
 * Get Functions
 * @author 鼠子Tomoriゞ
 * @link https://blog.miomoe.cn/
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class Get {
    private static $widget;
    
    private function __construct() {}
    private function __clone() {}
    private function __wakeup() {}

    private static function getWidget() {
        if (is_null(self::$widget)) {
            try {
                self::$widget = \Widget_Archive::widget('Widget_Archive');
            } catch (Exception $e) {
                throw new Exception('无法初始化Widget实例: ' . $e->getMessage());
            }
        }
        return self::$widget;
    }

    public static function HelloWorld() {
        echo '您已成功安装开发框架！<br>这是显示在index.php中的默认内容。';
    }

    // 获取站点URL
    public static function SiteUrl() {
        echo Helper::options()->siteUrl;
    }

    // 获取框架版本
    public static function FrameworkVer() {
        try {
            $ver = Typecho_Plugin::parseInfo(dirname(__DIR__) . '/Config/Config.php');
            echo $ver['version'] ?? '未知版本';
        } catch (Exception $e) {
            error_log('获取框架版本失败: ' . $e->getMessage());
            echo '获取版本失败';
        }
    }

    // 获取Typecho版本
    public static function TypechoVer() {
        echo Helper::options()->Version;
    }

    // 获取指定参数的配置值
    public static function Options($param) {
        return Helper::options()->$param;
    }

    // 获取指定文件
    public static function Need($file) {
        return \Widget_Archive::widget('Widget_Archive')->need($file);
    }

    // 判断页面类型
    public static function Is($type) {
        return \Widget_Archive::widget('Widget_Archive')->is($type);
    }

    // 分页PageNav
    public static function PageNav($prev = '&laquo; 前一页', $next = '后一页 &raquo;'){
        self::getWidget()->pageNav($prev, $next);
    }

    // 分页PageLink
    public static function PageLink($html = '', $next = ''){
        $widget = self::getWidget();
        if ($next === 'next') {
            if ($widget->have()) {
                $link = $widget->pageLink($html, 'next');
                echo $link;
            }
        } else {
            if ($widget->have()) {
                $link = $widget->pageLink($html);
                echo $link;
            }
        }
    }

    // 获取当前页码
    public static function CurrentPage() {
        try {
            $widget = self::getWidget();
            return $widget->_currentPage;
        } catch (Exception $e) {
            error_log('获取当前页码失败: ' . $e->getMessage());
                return 1;
            }
        }

}

class GetTheme {
    private function __construct() {}
    private function __clone() {}
    private function __wakeup() {}

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
        try {
            $author = Typecho_Plugin::parseInfo(dirname(__DIR__) . '/index.php');
            echo $author['author'];
        } catch (Exception $e) {
            error_log('获取主题作者失败: ' . $e->getMessage());
            echo '';
        }
    }

    // 获取主题版本
    public static function Ver() {
        try {
            $ver = Typecho_Plugin::parseInfo(dirname(__DIR__) . '/index.php');
            echo $ver['version'];
        } catch (Exception $e) {
            error_log('获取主题版本失败: ' . $e->getMessage());
            echo '';
        }
    }

    // 获取主题Assets目录URL
    public static function AssetsUrl() {
        echo Helper::options()->themeUrl('Assets');
    }
    
}

class GetPost {
    private static $widget;
    
    private function __construct() {}
    private function __clone() {}
    private function __wakeup() {}

    private static function getWidget() {
        if (is_null(self::$widget)) {
            try {
                self::$widget = \Widget_Archive::widget('Widget_Archive');
            } catch (Exception $e) {
                throw new Exception('无法初始化Widget实例: ' . $e->getMessage());
            }
        }
        return self::$widget;
    }

    // 获取文章标题
    public static function Title() {
        try {
            echo self::getWidget()->title;
        } catch (Exception $e) {
            error_log('获取标题失败: ' . $e->getMessage());
            return '';
        }
    }

    // 获取文章日期
    public static function Date($format = 'Y-m-d') {
        try {
            return self::getWidget()->date($format);
        } catch (Exception $e) {
            error_log('获取日期失败: ' . $e->getMessage());
            return '';
        }
    }

    // 获取文章分类
    public static function Category() {
        try {
            echo self::getWidget()->category();
        } catch (Exception $e) {
            error_log('获取分类失败: ' . $e->getMessage());
        }
    }

    // 获取文章分类URL
    public static function CategoryUrl() {
        try {
            echo self::getWidget()->categoryUrl();
        } catch (Exception $e) {
            error_log('获取分类URL失败: ' . $e->getMessage());
        }
    }

    // 获取文章标签 
    public static function Tags() {
        try {
            $tags = self::getWidget()->tags;
            if ($tags) {
                $tagNames = array();
                foreach ($tags as $tag) {
                    $tagNames[] = $tag['name'];
                }
                echo implode(',', $tagNames);
            } else {
                echo '暂无标签';
            }
        } catch (Exception $e) {
            error_log('获取标签失败: ' . $e->getMessage());
            echo '暂无标签';
        }
    }

    // 获取文章标签URL
    public static function TagsUrl() {
        try {
            echo self::getWidget()->tagsUrl();
        } catch (Exception $e) {
            error_log('获取标签URL失败: ' . $e->getMessage());
        }
    }

    // 获取文章摘要
    public static function Excerpt($length = 0) {
        try {
            $excerpt = strip_tags(self::getWidget()->excerpt);
            if ($length > 0) {
                $excerpt = mb_substr($excerpt, 0, $length, 'UTF-8');
            }
            echo $excerpt;
        } catch (Exception $e) {
            error_log('获取摘要失败: ' . $e->getMessage());
        }
    }

    // 获取文章永久链接
    public static function Permalink() {
        try {
            echo self::getWidget()->permalink;
        } catch (Exception $e) {
            error_log('获取永久链接失败: ' . $e->getMessage());
        }
    }

    // 获取文章内容
    public static function Content() {
        try {
            echo self::getWidget()->content;
        } catch (Exception $e) {
            error_log('获取内容失败: ' . $e->getMessage());
        }
    }

    // 获取文章数
    public static function PostsNum() {
        try {
            echo self::getWidget()->postsNum;
        } catch (Exception $e) {
            error_log('获取文章数失败: ' . $e->getMessage());
        }
    }

    // 获取页面数
    public static function PagesNum() {
        try {
            echo self::getWidget()->pagesNum;
        } catch (Exception $e) {
            error_log('获取页面数失败: ' . $e->getMessage());
        }
    }

    // 获取当前页码
    public static function CurrentPage() {
        try {
            echo self::getWidget()->currentPage;
        } catch (Exception $e) {
            error_log('获取当前页码失败: ' . $e->getMessage());
        }
    }

    // 获取归档标题
    public static function ArchiveTitle($format = '', $default = '', $connector = '') {
        try {
            if (empty($format)) {
                echo self::getWidget()->archiveTitle;
            } else {
                echo self::getWidget()->archiveTitle($format, $default, $connector);
            }
        } catch (Exception $e) {
            error_log('获取归档标题失败: ' . $e->getMessage());
        }
    }

    // 获取文章作者
    public static function Author() {
        try {
            echo self::getWidget()->author->screenName;
        } catch (Exception $e) {
            error_log('获取作者失败: ' . $e->getMessage());
        }
    }

    // 获取文章作者链接
    public static function AuthorPermalink() {
        try {
            echo self::getWidget()->author->permalink;
        } catch (Exception $e) {
            error_log('获取作者链接失败: ' . $e->getMessage());
        }
    }
}

class GetComments {
    private static $widget;
    
    private function __construct() {}
    private function __clone() {}
    private function __wakeup() {}

    private static function getWidget() {
        if (is_null(self::$widget)) {
            try {
                self::$widget = \Widget_Archive::widget('Widget_Archive');
            } catch (Exception $e) {
                throw new Exception('无法初始化Widget实例: ' . $e->getMessage());
            }
        }
        return self::$widget;
    }

    // 获取评论
    public static function Comments() {
        try {
            echo self::getWidget()->comments;
        } catch (Exception $e) {
            error_log('获取评论失败: ' . $e->getMessage());
        }
    }

    // 获取评论页面
    public static function CommentsPage() {
        try {
            echo self::getWidget()->commentsPage;
        } catch (Exception $e) {
            error_log('获取评论页面失败: ' . $e->getMessage());
        }
    }

    // 获取评论列表
    public static function CommentsList() {
        try {
            echo self::getWidget()->commentsList;
        } catch (Exception $e) {
            error_log('获取评论列表失败: ' . $e->getMessage());
        }
    }

    // 获取评论数
    public static function CommentsNum() {
        try {
            echo self::getWidget()->commentsNum;
        } catch (Exception $e) {
            error_log('获取评论数失败: ' . $e->getMessage());
        }
    }

    // 获取评论表单
    public static function CommentsForm() {
        try {
            echo self::getWidget()->commentsForm;
        } catch (Exception $e) {
            error_log('获取评论表单失败: ' . $e->getMessage());
        }
    }
}

class GetFunctions {
    private function __construct() {}
    private function __clone() {}
    private function __wakeup() {}

    // 获取加载时间
    public static function TimerStop() {
        echo timer_stop();
    }

    // 获取文章字数
    public static function ArtCount($cid) {
        if (!is_numeric($cid)) {
            error_log('ArtCount: 无效的CID参数');
            return 0;
        }
        return art_count($cid);
    }

    public static function WordCount($content, $echo = true) {
        if (empty($content)) {
            return 0;
        }
        
        $wordCount = mb_strlen(strip_tags($content), 'UTF-8');
        
        if ($echo) {
            echo $wordCount;
        }
        return $wordCount;
    }
}

class GetJsonData {   
    private function __construct() {}
    private function __clone() {}
    private function __wakeup() {}

    public static function Tomori() {
        if (function_exists('outputJsonData')) {
            outputJsonData();
        }
    }

    /**
     * @param array $data
     * @return string
     */
    public static function JsonTitle($data) {
        if (!is_array($data)) {
            error_log('JsonTitle: 无效的数据格式');
            return '无效的数据格式';
        }
        return isset($data['title']) 
            ? htmlspecialchars($data['title'], ENT_QUOTES, 'UTF-8')
            : '暂无标题';
    }

    /**
     * @param array $data
     * @return string
     */
    public static function JsonContent($data) {
        if (!is_array($data)) {
            error_log('JsonContent: 无效的数据格式');
            return '无效的数据格式';
        }
        return isset($data['content'])
            ? htmlspecialchars($data['content'], ENT_QUOTES, 'UTF-8')
            : '暂无内容';
    }

    /**
     * @param array $data
     * @return string
     */
    public static function JsonDate($data) {
        if (!is_array($data)) {
            error_log('JsonDate: 无效的数据格式');
            return '无效的数据格式';
        }
        return isset($data['date'])
            ? htmlspecialchars($data['date'], ENT_QUOTES, 'UTF-8')
            : '暂无日期';
    }

    /**
     * @param array $data
     * @return string
     */
    public static function JsonUrl($data) {
        if (!is_array($data)) {
            error_log('JsonUrl: 无效的数据格式');
            return '无效的数据格式';
        }
        return isset($data['url'])
            ? htmlspecialchars($data['url'], ENT_QUOTES, 'UTF-8')
            : '暂无链接';
    }
}
