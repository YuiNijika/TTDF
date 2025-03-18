<?php

/**
 * GetPost Class
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class GetPost extends Typecho_Widget
{
    use ErrorHandler, SingletonWidget;

    private static $_currentArchive;
    
    private function __construct() {}
    private function __clone() {}
    public function __wakeup() {}

    /**
     * 获取当前文章实例
     */
    private static function getCurrentArchive()
    {
        return self::$_currentArchive ?? self::getArchive();
    }

    /**
     * 解除实例绑定
     */
    public static function unbindArchive()
    {
        self::$_currentArchive = null;
    }

    /**
     * 文章列表获取
     * @param array|null $params 查询参数
     * @return Typecho_Widget
     */
    public static function List($params = null)
    {
        try {
            if ($params) {
                $alias = 'custom_'.md5(serialize($params));
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

    // 数据获取方法
    
    public static function Title($echo = true)
    {
        try {
            $title = self::getCurrentArchive()->title;
            return self::outputValue($title, $echo);
        } catch (Exception $e) {
            return self::handleOutputError('获取标题失败', $e, $echo);
        }
    }

    public static function Date($format = 'Y-m-d', $echo = true)
    {
        try {
            $date = self::getCurrentArchive()->date($format);
            return self::outputValue($date, $echo);
        } catch (Exception $e) {
            return self::handleOutputError('获取日期失败', $e, $echo, '');
        }
    }

    public static function Category($split = ',', $link = true, $default = '暂无分类', $echo = true)
    {
        try {
            $category = self::getCurrentArchive()->category($split, $link, $default);
            return self::outputValue($category, $echo);
        } catch (Exception $e) {
            return self::handleOutputError('获取分类失败', $e, $echo, $default);
        }
    }

    public static function Tags($split = ',', $link = true, $default = '暂无标签', $echo = true)
    {
        try {
            $tags = self::getCurrentArchive()->tags($split, $link, $default);
            return self::outputValue($tags, $echo);
        } catch (Exception $e) {
            return self::handleOutputError('获取标签失败', $e, $echo, $default);
        }
    }

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

    public static function Permalink($echo = true)
    {
        try {
            $permalink = self::getCurrentArchive()->permalink;
            return self::outputValue($permalink, $echo);
        } catch (Exception $e) {
            return self::handleOutputError('获取链接失败', $e, $echo);
        }
    }

    public static function Content($echo = true)
    {
        try {
            $content = self::getCurrentArchive()->content;
            return self::outputValue($content, $echo);
        } catch (Exception $e) {
            return self::handleOutputError('获取内容失败', $e, $echo);
        }
    }

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

    public static function Author($echo = true)
    {
        try {
            $author = self::getCurrentArchive()->author->screenName;
            return self::outputValue($author, $echo);
        } catch (Exception $e) {
            return self::handleOutputError('获取作者失败', $e, $echo);
        }
    }

    public static function AuthorAvatar($size = 128, $echo = true)
    {
        try {
            $avatar = self::getCurrentArchive()->author->gravatar($size);
            return self::outputValue($avatar, $echo);
        } catch (Exception $e) {
            return self::handleOutputError('获取头像失败', $e, $echo);
        }
    }

    public static function AuthorPermalink($echo = true)
    {
        try {
            $link = self::getCurrentArchive()->author->permalink;
            return self::outputValue($link, $echo);
        } catch (Exception $e) {
            return self::handleOutputError('获取作者链接失败', $e, $echo);
        }
    }

    public static function WordCount($echo = true)
    {
        try {
            $cid = self::getCurrentArchive()->cid;
            $text = TTDF_DB::getInstance()->getArticleText($cid);
            $text = preg_replace("/[^\x{4e00}-\x{9fa5}]/u", "", $text);
            $count = mb_strlen($text, 'UTF-8');
            return self::outputValue($count, $echo);
        } catch (Exception $e) {
            return self::handleOutputError('统计字数失败', $e, $echo);
        }
    }

    public static function PostsNum($echo = true)
    {
        try {
            $count = TTDF_DB::getInstance()->getArticleCount();
            return self::outputValue($count, $echo);
        } catch (Exception $e) {
            return self::handleOutputError('获取文章数失败', $e, $echo);
        }
    }

    public static function DB_Title($echo = true)
    {
        try {
            $title = TTDF_DB::getInstance()->getArticleTitle(self::getCurrentArchive()->cid);
            return self::outputValue($title, $echo);
        } catch (Exception $e) {
            return self::handleOutputError('获取数据库标题失败', $e, $echo);
        }
    }

    public static function DB_Content($echo = true)
    {
        try {
            $content = TTDF_DB::getInstance()->getArticleContent(self::getCurrentArchive()->cid);
            return self::outputValue($content, $echo);
        } catch (Exception $e) {
            return self::handleOutputError('获取数据库内容失败', $e, $echo);
        }
    }

    public static function DB_Content_Html($echo = true)
    {
        try {
            $content = TTDF_DB::getInstance()->getArticleContent(self::getCurrentArchive()->cid);
            $html = Markdown::convert($content);
            return self::outputValue($html, $echo);
        } catch (Exception $e) {
            return self::handleOutputError('转换HTML失败', $e, $echo);
        }
    }

    /**
     * 统一输出处理方法
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
     */
    private static function handleOutputError($message, $exception, $echo, $default = '')
    {
        self::handleError($message, $exception);
        return self::outputValue($default, $echo);
    }
}