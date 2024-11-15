<?php
/**
 * Get Functions
 * @author 鼠子Tomoriゞ
 * @link https://blog.miomoe.cn/
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

trait ErrorHandler {
    protected static function handleError($message, $e, $defaultValue = '') {
        error_log($message . ': ' . $e->getMessage());
        return $defaultValue;
    }
}

trait SingletonWidget {
    private static $widget;
    
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
}

class Get {
    use ErrorHandler, SingletonWidget;
    
    private function __construct() {}
    private function __clone() {}
    private function __wakeup() {}

    public static function HelloWorld() {
        echo '您已成功安装开发框架！<br>这是显示在index.php中的默认内容。';
    }

    // Header
    public static function Header() {
        try {
            return self::getWidget()->header();
        } catch (Exception $e) {
            return self::handleError('获取Header失败', $e);
        }
    }

    // Footer  
    public static function Footer() {
        try {
            return self::getWidget()->footer();
        } catch (Exception $e) {
            return self::handleError('获取Footer失败', $e);
        }
    }

    // 获取站点URL
    public static function SiteUrl() {
        try {
            echo Helper::options()->siteUrl;
        } catch (Exception $e) {
            self::handleError('获取站点URL失败', $e);
        }
    }

    // Next
    public static function Next() {
        try {
            if (method_exists(self::getWidget(), 'Next')) {
                return self::getWidget()->Next();
            }
            throw new Exception('Next 方法不存在');
        } catch (Exception $e) {
            return self::handleError('Next 调用失败', $e, null);
        }
    }

    // Metas
    public static function Metas($type) {
        try {
            return self::getWidget()->metas($type);
        } catch (Exception $e) {
            return self::handleError('获取Metas失败', $e, null);
        }
    }

    // 获取框架版本
    public static function FrameworkVer() {
        try {
            $ver = Typecho_Plugin::parseInfo(dirname(__DIR__) . '/Config/Config.php');
            echo $ver['version'] ?? '未知版本';
        } catch (Exception $e) {
            self::handleError('获取框架版本失败', $e);
            echo '获取版本失败';
        }
    }

    // 获取Typecho版本
    public static function TypechoVer() {
        try {
            echo Helper::options()->Version;
        } catch (Exception $e) {
            self::handleError('获取Typecho版本失败', $e);
        }
    }

    // 获取配置参数
    public static function Options($param) {
        try {
            return Helper::options()->$param;
        } catch (Exception $e) {
            return self::handleError('获取配置参数失败', $e);
        }
    }

    // 获取字段
    public static function Fields($param) {
        try {
            return self::getWidget()->fields->$param;
        } catch (Exception $e) {
            return self::handleError('获取字段失败', $e);
        }
    }

    // 引入文件
    public static function Need($file) {
        try {
            return self::getWidget()->need($file);
        } catch (Exception $e) {
            return self::handleError('获取文件失败', $e);
        }
    }

    // 判断页面类型
    public static function Is($type) {
        try {
            return self::getWidget()->is($type);
        } catch (Exception $e) {
            return self::handleError('判断页面类型失败', $e, false);
        }
    }

    // 分页导航
    public static function PageNav($prev = '&laquo; 前一页', $next = '后一页 &raquo;') {
        try {
            self::getWidget()->pageNav($prev, $next);
        } catch (Exception $e) {
            self::handleError('分页导航失败', $e);
        }
    }

    // 获取总数
    public static function Total() {
        try {
            return self::getWidget()->getTotal();
        } catch (Exception $e) {
            return self::handleError('获取总数失败', $e, 0);
        }
    }

    // 获取页面大小
    public static function PageSize() {
        try {
            return self::getWidget()->parameter->pageSize;
        } catch (Exception $e) {
            return self::handleError('获取页面大小失败', $e, 10);
        }
    }

    // 获取页面链接
    public static function PageLink($html = '', $next = '') {
        try {
            $widget = self::getWidget();
            if ($widget->have()) {
                $link = ($next === 'next') ? $widget->pageLink($html, 'next') : $widget->pageLink($html);
                echo $link;
            }
        } catch (Exception $e) {
            self::handleError('获取页面链接失败', $e);
        }
    }

    // 获取当前页码
    public static function CurrentPage() {
        try {
            return self::getWidget()->_currentPage;
        } catch (Exception $e) {
            return self::handleError('获取当前页码失败', $e, 1);
        }
    }

    // 获取页面Permalink
    public static function Permalink() {
        try {
            return self::getWidget()->permalink();
        } catch (Exception $e) {
            return self::handleError('获取页面Url失败', $e);
        }
    }
}

class GetTheme {
    use ErrorHandler;
    
    private function __construct() {}
    private function __clone() {}
    private function __wakeup() {}

    public static function Url() {
        try {
            echo Helper::options()->themeUrl;
        } catch (Exception $e) {
            self::handleError('获取主题URL失败', $e);
        }
    }

    // 获取主题名称
    public static function Name() {
        try {
            echo Helper::options()->theme;
        } catch (Exception $e) {
            self::handleError('获取主题名称失败', $e);
        }
    }

    // 获取主题作者
    public static function Author() {
        try {
            $author = Typecho_Plugin::parseInfo(dirname(__DIR__) . '/index.php');
            echo $author['author'];
        } catch (Exception $e) {
            self::handleError('获取主题作者失败', $e);
            echo '';
        }
    }

    // 获取主题版本
    public static function Ver() {
        try {
            $ver = Typecho_Plugin::parseInfo(dirname(__DIR__) . '/index.php');
            echo $ver['version'];
        } catch (Exception $e) {
            self::handleError('获取主题版本失败', $e);
            echo '';
        }
    }

    // 获取主题Assets目录URL
    public static function AssetsUrl() {
        try {
            echo Helper::options()->themeUrl('Assets');
        } catch (Exception $e) {
            self::handleError('获取主题Assets目录URL失败', $e);
        }
    }
}

class GetPost {
    use ErrorHandler, SingletonWidget;
    
    private function __construct() {}
    private function __clone() {}
    private function __wakeup() {}

    // 获取标题
    public static function Title() {
        try {
            echo self::getWidget()->title;
        } catch (Exception $e) {
            self::handleError('获取标题失败', $e);
        }
    }

    // 获取日期
    public static function Date($format = 'Y-m-d') {
        try {
            return self::getWidget()->date($format);
        } catch (Exception $e) {
            return self::handleError('获取日期失败', $e, '');
        }
    }

    // 获取分类
    public static function Category($split = ',', $link = true, $default = '暂无分类') {
        try {
            echo self::getWidget()->category($split, $link, $default);
        } catch (Exception $e) {
            self::handleError('获取分类失败', $e);
            echo $default;
        }
    }

    // 获取标签
    public static function Tags($split = ',', $link = true, $default = '暂无标签') {
        try {
            echo self::getWidget()->tags($split, $link, $default);
        } catch (Exception $e) {
            self::handleError('获取标签失败', $e);
            echo $default;
        }
    }
    // 获取摘要
    public static function Excerpt($length = 0) {
        try {
            $excerpt = strip_tags(self::getWidget()->excerpt);
            if ($length > 0) {
                $excerpt = mb_substr($excerpt, 0, $length, 'UTF-8');
            }
            echo $excerpt;
        } catch (Exception $e) {
            self::handleError('获取摘要失败', $e);
        }
    }

    // 获取永久链接
    public static function Permalink() {
        try {
            echo self::getWidget()->permalink;
        } catch (Exception $e) {
            self::handleError('获取永久链接失败', $e);
        }
    }

    // 获取内容
    public static function Content() {
        try {
            echo self::getWidget()->content;
        } catch (Exception $e) {
            self::handleError('获取内容失败', $e);
        }
    }

    // 获取文章数
    public static function PostsNum() {
        try {
            echo self::getWidget()->postsNum;
        } catch (Exception $e) {
            self::handleError('获取文章数失败', $e);
        }
    }

    // 获取页面数
    public static function PagesNum() {
        try {
            echo self::getWidget()->pagesNum;
        } catch (Exception $e) {
            self::handleError('获取页面数失败', $e);
        }
    }

    // 获取标题
    public static function ArchiveTitle($format = '', $default = '', $connector = '') {
        try {
            if (empty($format)) {
                echo self::getWidget()->archiveTitle;
            } else {
                echo self::getWidget()->archiveTitle($format, $default, $connector);
            }
        } catch (Exception $e) {
            self::handleError('获取标题失败', $e);
        }
    }

    // 获取作者
    public static function Author() {
        try {
            echo self::getWidget()->author->screenName;
        } catch (Exception $e) {
            self::handleError('获取作者失败', $e);
        }
    }

    // 获取作者链接
    public static function AuthorPermalink() {
        try {
            echo self::getWidget()->author->permalink;
        } catch (Exception $e) {
            self::handleError('获取作者链接失败', $e);
        }
    }
}

class GetComments {
    use ErrorHandler, SingletonWidget;
    
    private function __construct() {}
    private function __clone() {}
    private function __wakeup() {}

    // 获取评论
    public static function Comments() {
        try {
            echo self::getWidget()->comments;
        } catch (Exception $e) {
            self::handleError('获取评论失败', $e);
        }
    }

    // 获取评论页面
    public static function CommentsPage() {
        try {
            echo self::getWidget()->commentsPage;
        } catch (Exception $e) {
            self::handleError('获取评论页面失败', $e);
        }
    }

    // 获取评论列表
    public static function CommentsList() {
        try {
            echo self::getWidget()->commentsList;
        } catch (Exception $e) {
            self::handleError('获取评论列表失败', $e);
        }
    }

    // 获取评论数
    public static function CommentsNum() {
        try {
            echo self::getWidget()->commentsNum;
        } catch (Exception $e) {
            self::handleError('获取评论数失败', $e);
        }
    }

    // 获取评论id
    public static function RespondId() {
        try {
            echo self::getWidget()->respondId;
        } catch (Exception $e) {
            self::handleError('获取评论id失败', $e);
        }
    }

    // 取消回复
    public static function CancelReply() {
        try {
            echo self::getWidget()->cancelReply();
        } catch (Exception $e) {
            self::handleError('取消回复失败', $e);
        }
    }

    // Remember
    public static function Remember($field) {
        try {
            echo self::getWidget()->remember($field);
        } catch (Exception $e) {
            self::handleError('获取Remember失败', $e);
        }
    }

    // 获取评论表单
    public static function CommentsForm() {
        try {
            echo self::getWidget()->commentsForm;
        } catch (Exception $e) {
            self::handleError('获取评论表单失败', $e);
        }
    }

    // 获取分页
    public static function PageNav($prev = '&laquo; 前一页', $next = '后一页 &raquo;') {
        try {
            // 使用评论专用的 Widget
            $comments = \Widget_Comments_Archive::widget('Widget_Comments_Archive');
            $comments->pageNav($prev, $next);
        } catch (Exception $e) {
            self::handleError('评论分页导航失败', $e);
        }
    }
}

class GetFunctions {
    use ErrorHandler;
    
    private function __construct() {}
    private function __clone() {}
    private function __wakeup() {}

    // 获取加载时间
    public static function TimerStop() {
        try {
            echo timer_stop();
        } catch (Exception $e) {
            self::handleError('获取加载时间失败', $e);
        }
    }

    // 获取文章字数
    public static function ArtCount($cid) {
        try {
            if (!is_numeric($cid)) {
                throw new Exception('无效的CID参数');
            }
            return art_count($cid);
        } catch (Exception $e) {
            return self::handleError('获取文章字数失败', $e, 0);
        }
    }

    // 获取字数
    public static function WordCount($content, $echo = true) {
        try {
            if (empty($content)) {
                return 0;
            }
            $wordCount = mb_strlen(strip_tags($content), 'UTF-8');
            if ($echo) {
                echo $wordCount;
            }
            return $wordCount;
        } catch (Exception $e) {
            return self::handleError('字数统计失败', $e, 0);
        }
    }
}

class GetJsonData {   
    use ErrorHandler;
    
    private function __construct() {}
    private function __clone() {}
    private function __wakeup() {}

    private static function validateData($data, $field) {
        if (!is_array($data)) {
            self::handleError("JsonData: {$field}数据格式无效", new Exception());
            return false;
        }
        return true;
    }

    // 输出JSON数据
    public static function Tomori() {
        try {
            if (function_exists('outputJsonData')) {
                outputJsonData();
            }
        } catch (Exception $e) {
            self::handleError('输出JSON数据失败', $e);
        }
    }

    // 获取标题
    public static function JsonTitle($data) {
        if (!self::validateData($data, 'title')) {
            return '无效的数据格式';
        }
        return isset($data['title']) 
            ? htmlspecialchars($data['title'], ENT_QUOTES, 'UTF-8')
            : '暂无标题';
    }

    // 获取内容
    public static function JsonContent($data) {
        if (!self::validateData($data, 'content')) {
            return '无效的数据格式';
        }
        return isset($data['content'])
            ? htmlspecialchars($data['content'], ENT_QUOTES, 'UTF-8')
            : '暂无内容';
    }

    // 获取日期
    public static function JsonDate($data) {
        if (!self::validateData($data, 'date')) {
            return '无效的数据格式';
        }
        return isset($data['date'])
            ? htmlspecialchars($data['date'], ENT_QUOTES, 'UTF-8')
            : '暂无日期';
    }

    // 获取链接
    public static function JsonUrl($data) {
        if (!self::validateData($data, 'url')) {
            return '无效的数据格式';
        }
    return isset($data['url'])
        ? htmlspecialchars($data['url'], ENT_QUOTES, 'UTF-8')
        : '暂无链接';
    }
}
