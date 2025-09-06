<?php

/**
 * GetPost 方法
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class GetPost extends Typecho_Widget
{
    use ErrorHandler, SingletonWidget;

    /**
     * 当前文章实例
     * @var Typecho_Widget|null
     */
    private static $_currentArchive;

    /**
     * 私有构造函数，防止外部实例化
     */
    private function __construct() {}

    /**
     * 私有克隆方法，防止克隆实例
     */
    private function __clone() {}

    /**
     * 禁用反序列化
     */
    public function __wakeup() {}

    /**
     * 获取当前文章实例
     * 如果 `_currentArchive` 为空，则调用 `getArchive` 方法初始化
     * @return Typecho_Widget
     */
    public static function getCurrentArchive() // 修改 protected -> public
    {
        return self::$_currentArchive ?? self::getArchive();
    }

    /**
     * 绑定当前文章实例
     * 
     * @param Typecho_Widget $archive 文章实例
     */
    public static function bindArchive($archive)
    {
        self::$_currentArchive = $archive;
    }

    /**
     * 解除当前文章实例的绑定
     * 将 `_currentArchive` 设置为 null，释放资源
     */
    public static function unbindArchive()
    {
        self::$_currentArchive = null;
    }

    /**
     * 文章列表获取
     * 支持自定义查询参数或默认获取下一篇文章
     * 
     * @param array|null $params 查询参数
     * @return Typecho_Widget 返回文章实例或空对象
     */
    public static function List($params = null)
    {
        try {
            if ($params) {
                $alias = 'custom_' . md5(serialize($params));
                $widget = \Widget\Archive::allocWithAlias(
                    $alias,
                    is_array($params) ? http_build_query($params) : $params
                );
                $widget->execute();
                self::$_currentArchive = $widget;
                return $widget;
            }

            if (method_exists(self::getArchive(), 'Next')) {
                return self::getArchive()->Next();
            }
            throw new Exception('List 方法不存在');
        } catch (Exception $e) {
            self::handleError('List 调用失败', $e);
            return new \Typecho_Widget_Helper_Empty();
        }
    }

    /**
     * 获取随机文章列表
     * 
     * @param int $pageSize 随机文章数量
     * @return array 返回随机文章列表
     */
    public static function RandomPosts($pageSize = 3)
    {
        try {
            $posts = DB::getInstance()->getRandomPosts($pageSize);
            return $posts;
        } catch (Exception $e) {
            self::handleError('获取随机文章失败', $e);
            return [];
        }
    }

    /**
     * 渲染随机文章列表
     * 
     * @param int $pageSize 随机文章数量
     * @param bool $echo 是否直接输出，默认为 true
     * @return void
     */
    public static function RenderRandomPosts($pageSize = 3, $echo = true)
    {
        try {
            $posts = self::RandomPosts($pageSize);

            if ($echo) {
                foreach ($posts as $post) {
                    echo '<a href="' . $post['permalink'] . '">' . $post['title'] . '</a><br>';
                }
            }

            return $posts;
        } catch (Exception $e) {
            self::handleOutputError('渲染随机文章失败', $e, $echo);
        }
    }

    // 数据获取方法

    /**
     * 获取文章CID
     * 
     * @param bool $echo 是否直接输出，默认为 true
     * @return int|null 返回文章CID或直接输出
     */
    public static function Cid($echo = true)
    {
        try {
            $cid = self::getCurrentArchive()->cid;
            return self::outputValue($cid, $echo);
        } catch (Exception $e) {
            return self::handleOutputError('获取Cid失败', $e, $echo);
        }
    }

    /**
     * 获取文章标题
     * 
     * @param bool $echo 是否直接输出，默认为 true
     * @return string|null 返回标题字符串或直接输出
     */
    public static function Title($echo = true)
    {
        try {
            $title = self::getCurrentArchive()->title;
            return self::outputValue($title, $echo);
        } catch (Exception $e) {
            return self::handleOutputError('获取标题失败', $e, $echo);
        }
    }

    /**
     * 获取文章日期
     * 
     * @param string $format 日期格式，默认为 'Y-m-d'
     * @param bool $echo 是否直接输出，默认为 true
     * @return string|null 返回日期字符串或直接输出
     */
    public static function Date($format = 'Y-m-d', $echo = true)
    {
        try {
            $date = self::getCurrentArchive()->date($format);
            return self::outputValue($date, $echo);
        } catch (Exception $e) {
            return self::handleOutputError('获取日期失败', $e, $echo, '');
        }
    }

    /**
     * 获取文章分类
     * 
     * @param string $split 分隔符，默认为 ','
     * @param bool $link 是否生成链接，默认为 true
     * @param string $default 默认值，默认为 '暂无分类'
     * @param bool $echo 是否直接输出，默认为 true
     * @return string|null 返回分类字符串或直接输出
     */
    public static function Category($split = ',', $link = true, $default = '暂无分类', $echo = true)
    {
        try {
            $category = self::getCurrentArchive()->category($split, $link, $default);
            return self::outputValue($category, $echo);
        } catch (Exception $e) {
            return self::handleOutputError('获取分类失败', $e, $echo, $default);
        }
    }

    /**
     * 获取文章标签
     * 
     * @param string $split 分隔符，默认为 ','
     * @param bool $link 是否生成链接，默认为 true
     * @param string $default 默认值，默认为 '暂无标签'
     * @param bool $echo 是否直接输出，默认为 true
     * @return string|null 返回标签字符串或直接输出
     */
    public static function Tags($split = ',', $link = true, $default = '暂无标签', $echo = true)
    {
        try {
            $tags = self::getCurrentArchive()->tags($split, $link, $default);
            return self::outputValue($tags, $echo);
        } catch (Exception $e) {
            return self::handleOutputError('获取标签失败', $e, $echo, $default);
        }
    }

    /**
     * 获取文章摘要
     * 
     * @param int $length 摘要长度，0 表示不限制
     * @param bool $echo 是否直接输出，默认为 true
     * @return string|null 返回摘要字符串或直接输出
     */
    public static function Excerpt($length = 0, $echo = true)
    {
        try {
            $excerpt = strip_tags(self::getCurrentArchive()->excerpt);
            $excerpt = $length > 0 ? mb_substr($excerpt, 0, $length, 'UTF-8') : $excerpt;
            return self::outputValue($excerpt, $echo);
        } catch (Exception $e) {
            return self::handleOutputError('获取摘要失败', $e, $echo);
        }
    }

    /**
     * 获取文章永久链接
     * 
     * @param bool $echo 是否直接输出，默认为 true
     * @return string|null 返回链接字符串或直接输出
     */
    public static function Permalink($echo = true)
    {
        try {
            $permalink = self::getCurrentArchive()->permalink;
            return self::outputValue($permalink, $echo);
        } catch (Exception $e) {
            return self::handleOutputError('获取链接失败', $e, $echo);
        }
    }

    /**
     * 获取文章内容
     * 
     * @param bool $echo 是否直接输出，默认为 true
     * @return string|null 返回内容字符串或直接输出
     */
    public static function Content($echo = true)
    {
        try {
            $content = self::getCurrentArchive()->content;
            return self::outputValue($content, $echo);
        } catch (Exception $e) {
            return self::handleOutputError('获取内容失败', $e, $echo);
        }
    }

    /**
     * 获取归档标题
     * 
     * @param string $format 格式化字符串，默认为空
     * @param string $default 默认值，默认为空
     * @param string $connector 连接符，默认为空
     * @param bool $echo 是否直接输出，默认为 true
     * @return string|null 返回标题字符串或直接输出
     */
    public static function ArchiveTitle($format = '', $default = '', $connector = '', $echo = true)
    {
        try {
            $title = empty($format)
                ? self::getCurrentArchive()->archiveTitle
                : self::getCurrentArchive()->archiveTitle($format, $default, $connector);
            return self::outputValue($title, $echo);
        } catch (Exception $e) {
            return self::handleOutputError('获取标题失败', $e, $echo);
        }
    }

    /**
     * 获取文章作者名称
     * 
     * @param bool $echo 是否直接输出，默认为 true
     * @return string|null 返回作者名称或直接输出
     */
    public static function Author($echo = true)
    {
        try {
            $author = self::getCurrentArchive()->author->screenName;
            return self::outputValue($author, $echo);
        } catch (Exception $e) {
            return self::handleOutputError('获取作者失败', $e, $echo);
        }
    }

    /**
     * 获取文章作者头像
     * 
     * @param int $size 头像尺寸，默认为 128
     * @param bool $echo 是否直接输出，默认为 true
     * @return string|null 返回头像 URL 或直接输出
     */
    public static function AuthorAvatar($size = 128, $echo = true)
    {
        try {
            $avatar = self::getCurrentArchive()->author->gravatar($size);
            return self::outputValue($avatar, $echo);
        } catch (Exception $e) {
            return self::handleOutputError('获取头像失败', $e, $echo);
        }
    }

    /**
     * 获取文章作者链接
     * 
     * @param bool $echo 是否直接输出，默认为 true
     * @return string|null 返回作者链接或直接输出
     */
    public static function AuthorPermalink($echo = true)
    {
        try {
            $link = self::getCurrentArchive()->author->permalink;
            return self::outputValue($link, $echo);
        } catch (Exception $e) {
            return self::handleOutputError('获取作者链接失败', $e, $echo);
        }
    }

    /**
     * 统计文章字数
     * 
     * @param bool $echo 是否直接输出，默认为 true
     * @return int|null 返回字数或直接输出
     */
    public static function WordCount($echo = true)
    {
        try {
            $cid = self::getCurrentArchive()->cid;
            $text = DB::getInstance()->getArticleText($cid);
            $text = preg_replace("/[^\x{4e00}-\x{9fa5}]/u", "", $text);
            $count = mb_strlen($text, 'UTF-8');
            return self::outputValue($count, $echo);
        } catch (Exception $e) {
            return self::handleOutputError('统计字数失败', $e, $echo);
        }
    }

    /**
     * 获取文章总数
     * 
     * @param bool $echo 是否直接输出，默认为 true
     * @return int|null 返回文章总数或直接输出
     */
    public static function PostsNum($echo = true)
    {
        try {
            $count = DB::getInstance()->getArticleCount();
            return self::outputValue($count, $echo);
        } catch (Exception $e) {
            return self::handleOutputError('获取文章数失败', $e, $echo);
        }
    }

    /**
     * 从数据库获取文章标题
     * 
     * @param bool $echo 是否直接输出，默认为 true
     * @return string|null 返回标题字符串或直接输出
     */
    public static function DB_Title($echo = true)
    {
        try {
            $title = DB::getInstance()->getArticleTitle(self::getCurrentArchive()->cid);
            return self::outputValue($title, $echo);
        } catch (Exception $e) {
            return self::handleOutputError('获取数据库标题失败', $e, $echo);
        }
    }

    /**
     * 从数据库获取文章内容
     * 
     * @param bool $echo 是否直接输出，默认为 true
     * @return string|null 返回内容字符串或直接输出
     */
    public static function DB_Content($echo = true)
    {
        try {
            $content = DB::getInstance()->getArticleContent(self::getCurrentArchive()->cid);
            return self::outputValue($content, $echo);
        } catch (Exception $e) {
            return self::handleOutputError('获取数据库内容失败', $e, $echo);
        }
    }

    /**
     * 从数据库获取文章内容并转换为 HTML
     * 
     * @param bool $echo 是否直接输出，默认为 true
     * @return string|null 返回 HTML 内容或直接输出
     */
    public static function DB_Content_Html($echo = true)
    {
        try {
            $content = DB::getInstance()->getArticleContent(self::getCurrentArchive()->cid);
            $content = preg_replace('/<!--.*?-->/', '', $content); // 移除注释避免干扰markdown解析
            $html = Markdown::convert($content);
            return self::outputValue($html, $echo);
        } catch (Exception $e) {
            return self::handleOutputError('转换HTML失败', $e, $echo);
        }
    }

    /**
     * 统一输出处理方法
     * 
     * @param mixed $value 输出值
     * @param bool $echo 是否直接输出
     * @return mixed 返回值或直接输出
     */
    private static function outputValue($value, $echo)
    {
        if ($echo) {
            echo $value;
            return null;
        }
        return $value;
    }

    /**
     * 统一错误处理方法
     * 
     * @param string $message 错误信息
     * @param Exception $exception 异常对象
     * @param bool $echo 是否直接输出
     * @param mixed $default 默认返回值
     * @return mixed 返回默认值或直接输出
     */
    private static function handleOutputError($message, $exception, $echo, $default = '')
    {
        self::handleError($message, $exception);
        return self::outputValue($default, $echo);
    }
}
