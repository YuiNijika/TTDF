<?php
/**
 * Get Functions
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
    
    private static function getArchive() {
        if (is_null(self::$widget)) {
            try {
                self::$widget = \Widget\Archive::widget('Widget_Archive');
            } catch (Exception $e) {
                throw new Exception('无法初始化 Widget 实例: ' . $e->getMessage());
            }
        }
        return self::$widget;
    }
}

class Get {
    use ErrorHandler, SingletonWidget;
    
    private function __construct() {}
    private function __clone() {}
    public function __wakeup() {}

    /**
     * HelloWorld
     * 
     */
    public static function HelloWorld(?bool $echo = true) {
        if ($echo) echo '您已成功安装开发框架！<br>这是显示在index.php中的默认内容。';
        
        return '您已成功安装开发框架！<br>这是显示在index.php中的默认内容。';
    }

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
    public static function Footer() {
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
    public static function SiteUrl(?bool $echo = true) {
        try {
            $SiteUrl = \Helper::options()->siteUrl;
            
            if ($echo) echo $SiteUrl;
            
            return $SiteUrl;
        } catch (Exception $e) {
            return self::handleError('获取站点URL失败', $e);
        }
    }

    /**
     * 返回堆栈（数组）中每一行的值
     * 一般用于循环输出文章
     *
     * @return mixed
     */
    public static function Next() {
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
    public static function FrameworkVer(?bool $echo = true) {
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
    public static function TypechoVer(?bool $echo = true) {
        try {
            $TypechoVer = \Helper::options()->Version;
            
            if ($echo) echo $TypechoVer;
            
            return $TypechoVer;
        } catch (Exception $e) {
            return self::handleError('获取Typecho版本失败', $e);
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
            return self::getArchive()->fields->$param;
        } catch (Exception $e) {
            return self::handleError('获取字段失败', $e);
        }
    }

    // 引入文件
    public static function Need($file) {
        try {
            return self::getArchive()->need($file);
        } catch (Exception $e) {
            return self::handleError('获取文件失败', $e);
        }
    }

    // 判断页面类型
    public static function Is($type) {
        try {
            return self::getArchive()->is($type);
        } catch (Exception $e) {
            return self::handleError('判断页面类型失败', $e, false);
        }
    }

    // 分页导航
    public static function PageNav($prev = '&laquo; 前一页', $next = '后一页 &raquo;') {
        try {
            self::getArchive()->pageNav($prev, $next);
        } catch (Exception $e) {
            self::handleError('分页导航失败', $e);
        }
    }

    // 获取总数
    public static function Total() {
        try {
            return self::getArchive()->getTotal();
        } catch (Exception $e) {
            return self::handleError('获取总数失败', $e, 0);
        }
    }

    // 获取页面大小
    public static function PageSize() {
        try {
            return self::getArchive()->parameter->pageSize;
        } catch (Exception $e) {
            return self::handleError('获取页面大小失败', $e, 10);
        }
    }

    // 获取页面链接
    public static function PageLink($html = '', $next = '') {
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
    public static function CurrentPage() {
        try {
            return self::getArchive()->_currentPage;
        } catch (Exception $e) {
            return self::handleError('获取当前页码失败', $e, 1);
        }
    }

    // 获取页面Permalink
    public static function Permalink() {
        try {
            return self::getArchive()->permalink();
        } catch (Exception $e) {
            return self::handleError('获取页面Url失败', $e);
        }
    }
}

class GetTheme {
    use ErrorHandler, SingletonWidget;
    
    private function __construct() {}
    private function __clone() {}
    public function __wakeup() {}

    /**
     * 获取主题目录的 Url 地址（末尾带 / ）
     *
     * @param bool|null $echo 当设置为 true 时，会直接输出；
     *                        当设置为 false 时，则返回结果径,
     *                        若额外的只传入$path，则只能输出。
     * @param string|null $path 子路径，就是主题文件夹相对于主题根目录的相对路径，路径开头 / 随意，结尾 / 同步到输出 Url。
     * @param string|null $theme 自定义模版名称，默认为当前模板。
     * @return string|null 
     * @throws Exception
     */
    public static function Url(?bool $echo = true, ?string $path = null, ?string $theme = null) {
        try {
            if (!$echo && !isset($path)) {
                return \Helper::options()->themeUrl;
            }else if($echo && isset($theme)) {
                echo \Helper::options()->themeUrl($path, $theme);
            }
            
            \Helper::options()->themeUrl($path, $theme);
        } catch (Exception $e) {
            return self::handleError('获取主题URL失败', $e);
        }
    }
    
    /**
     * 获取主题的绝对路径（末尾不带 / ）
     *
     * @param bool|null $echo 当设置为 true 时，会直接输出；
     *                        当设置为 false 时，则返回结果径。
     * @return string|null 
     * @throws Exception
     */
    public static function Dir(?bool $echo = true) {
        try {
            $Dir = self::getArchive()->getThemeDir();
            
            if ($echo) echo $Dir;
            
            return $Dir; 
        } catch (Exception $e) {
            return self::handleError('获取主题绝对路径失败', $e);
        }        
    }

    /**
     * 定义AssetsUrl
     * 防止之前写的主题失效
     */
    public static function AssetsUrl() {
        return self::Url(false, 'Assets');
    }
    /**
     * 获取主题名称
     *
     * @param bool|null $echo 当设置为 true 时，会直接输出；
     *                        当设置为 false 时，则返回结果。
     * @return string|null 
     * @throws Exception
     */
    public static function Name(?bool $echo = true) {
        try {
            $Name = \Helper::options()->theme;
            
            if ($echo) echo $Name;
            
            return $Name; 
        } catch (Exception $e) {
            return self::handleError('获取主题名称失败', $e);
        }
    }

    /**
     * 获取主题作者
     *
     * @param bool|null $echo 当设置为 true 时，会直接输出；
     *                        当设置为 false 时，则返回结果。
     * @return string|null 
     * @throws Exception
     */
    public static function Author(?bool $echo = true) {
        try {
            $author = \Typecho\Plugin::parseInfo(dirname(__DIR__) . '/index.php');
            
            if (empty($author['author'])) $author['author'] = null;
            
            if ($echo) echo $author['author'];
            
            return $author['author'];
        } catch (Exception $e) {
            return self::handleError('获取主题作者失败', $e);
        }
    }

    /**
     * 获取主题版本
     *
     * @param bool|null $echo 当设置为 true 时，会直接输出；
     *                        当设置为 false 时，则返回结果。
     * @return string|null 
     * @throws Exception
     */
    public static function Ver(?bool $echo = true) {
        try {
            $ver = \Typecho\Plugin::parseInfo(dirname(__DIR__) . '/index.php');
            
            if (empty($ver['version'])) $ver['version'] = null;
            
            if ($echo)  echo $ver['version'];
            
            return $ver;
        } catch (Exception $e) {
            return self::handleError('获取主题版本失败', $e);
        }
    }
}

class GetPost {
    use ErrorHandler, SingletonWidget;
    
    private function __construct() {}
    private function __clone() {}
    public function __wakeup() {}

    // 获取标题
    public static function Title() {
        try {
            echo self::getArchive()->title;
        } catch (Exception $e) {
            self::handleError('获取标题失败', $e);
        }
    }

    // 获取日期
    public static function Date($format = 'Y-m-d') {
        try {
            return self::getArchive()->date($format);
        } catch (Exception $e) {
            return self::handleError('获取日期失败', $e, '');
        }
    }

    // 获取分类
    public static function Category($split = ',', $link = true, $default = '暂无分类') {
        try {
            echo self::getArchive()->category($split, $link, $default);
        } catch (Exception $e) {
            self::handleError('获取分类失败', $e);
            echo $default;
        }
    }

    // 获取标签
    public static function Tags($split = ',', $link = true, $default = '暂无标签') {
        try {
            echo self::getArchive()->tags($split, $link, $default);
        } catch (Exception $e) {
            self::handleError('获取标签失败', $e);
            echo $default;
        }
    }
    // 获取摘要
    public static function Excerpt($length = 0) {
        try {
            $excerpt = strip_tags(self::getArchive()->excerpt);
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
            echo self::getArchive()->permalink;
        } catch (Exception $e) {
            self::handleError('获取永久链接失败', $e);
        }
    }

    // 获取内容
    public static function Content() {
        try {
            echo self::getArchive()->content;
        } catch (Exception $e) {
            self::handleError('获取内容失败', $e);
        }
    }

    // 获取文章数
    public static function PostsNum() {
        try {
            echo self::getArchive()->postsNum;
        } catch (Exception $e) {
            self::handleError('获取文章数失败', $e);
        }
    }

    // 获取页面数
    public static function PagesNum() {
        try {
            echo self::getArchive()->pagesNum;
        } catch (Exception $e) {
            self::handleError('获取页面数失败', $e);
        }
    }

    // 获取标题
    public static function ArchiveTitle($format = '', $default = '', $connector = '') {
        try {
            if (empty($format)) {
                echo self::getArchive()->archiveTitle;
            } else {
                echo self::getArchive()->archiveTitle($format, $default, $connector);
            }
        } catch (Exception $e) {
            self::handleError('获取标题失败', $e);
        }
    }

    // 获取作者
    public static function Author() {
        try {
            echo self::getArchive()->author->screenName;
        } catch (Exception $e) {
            self::handleError('获取作者失败', $e);
        }
    }
    
    // 获取作者头像
    public static function AuthorAvatar($size = 128) {
        try {
            echo self::getArchive()->author->gravatar($size);
        } catch (Exception $e) {
            self::handleError('获取作者头像失败', $e);
        }
    }

    // 获取作者链接
    public static function AuthorPermalink() {
        try {
            echo self::getArchive()->author->permalink;
        } catch (Exception $e) {
            self::handleError('获取作者链接失败', $e);
        }
    }
}

class GetComments {
    use ErrorHandler, SingletonWidget;
    
    private function __construct() {}
    private function __clone() {}
    public function __wakeup() {}

    // 获取评论
    public static function Comments() {
        try {
            echo self::getArchive()->comments;
        } catch (Exception $e) {
            self::handleError('获取评论失败', $e);
        }
    }

    // 获取评论页面
    public static function CommentsPage() {
        try {
            echo self::getArchive()->commentsPage;
        } catch (Exception $e) {
            self::handleError('获取评论页面失败', $e);
        }
    }

    // 获取评论列表
    public static function CommentsList() {
        try {
            echo self::getArchive()->commentsList;
        } catch (Exception $e) {
            self::handleError('获取评论列表失败', $e);
        }
    }

    // 获取评论数
    public static function CommentsNum() {
        try {
            echo self::getArchive()->commentsNum;
        } catch (Exception $e) {
            self::handleError('获取评论数失败', $e);
        }
    }

    // 获取评论id
    public static function RespondId() {
        try {
            echo self::getArchive()->respondId;
        } catch (Exception $e) {
            self::handleError('获取评论id失败', $e);
        }
    }

    // 取消回复
    public static function CancelReply() {
        try {
            echo self::getArchive()->cancelReply();
        } catch (Exception $e) {
            self::handleError('取消回复失败', $e);
        }
    }

    // Remember
    public static function Remember($field) {
        try {
            echo self::getArchive()->remember($field);
        } catch (Exception $e) {
            self::handleError('获取Remember失败', $e);
        }
    }

    // 获取评论表单
    public static function CommentsForm() {
        try {
            echo self::getArchive()->commentsForm;
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
    public function __wakeup() {}

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
    public function __wakeup() {}

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
