<?php

/**
 * GetPost Class
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

require_once 'ClassDB.php';

class GetPost extends Typecho_Widget {
    use ErrorHandler, SingletonWidget;

    private function __construct() {}
    private function __clone() {}
    public function __wakeup() {}

    // 获取标题
    public static function Title()
    {
        try {
            echo self::getArchive()->title;
        } catch (Exception $e) {
            self::handleError('获取标题失败', $e);
        }
    }

    // 获取日期
    public static function Date($format = 'Y-m-d')
    {
        try {
            return self::getArchive()->date($format);
        } catch (Exception $e) {
            return self::handleError('获取日期失败', $e, '');
        }
    }

    // 获取分类
    public static function Category($split = ',', $link = true, $default = '暂无分类')
    {
        try {
            echo self::getArchive()->category($split, $link, $default);
        } catch (Exception $e) {
            self::handleError('获取分类失败', $e);
            echo $default;
        }
    }

    // 获取标签
    public static function Tags($split = ',', $link = true, $default = '暂无标签')
    {
        try {
            echo self::getArchive()->tags($split, $link, $default);
        } catch (Exception $e) {
            self::handleError('获取标签失败', $e);
            echo $default;
        }
    }

    // 获取摘要
    public static function Excerpt($length = 0)
    {
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
    public static function Permalink()
    {
        try {
            echo self::getArchive()->permalink;
        } catch (Exception $e) {
            self::handleError('获取永久链接失败', $e);
        }
    }

    // 获取内容
    public static function Content()
    {
        try {
            echo self::getArchive()->content;
        } catch (Exception $e) {
            self::handleError('获取内容失败', $e);
        }
    }

    // 获取文章数
    public static function PostsNum()
    {
        try {
            echo self::getArchive()->postsNum;
        } catch (Exception $e) {
            self::handleError('获取文章数失败', $e);
        }
    }

    // 获取页面数
    public static function PagesNum()
    {
        try {
            echo self::getArchive()->pagesNum;
        } catch (Exception $e) {
            self::handleError('获取页面数失败', $e);
        }
    }

    // 获取标题
    public static function ArchiveTitle($format = '', $default = '', $connector = '')
    {
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
    public static function Author()
    {
        try {
            echo self::getArchive()->author->screenName;
        } catch (Exception $e) {
            self::handleError('获取作者失败', $e);
        }
    }

    // 获取作者头像
    public static function AuthorAvatar($size = 128)
    {
        try {
            echo self::getArchive()->author->gravatar($size);
        } catch (Exception $e) {
            self::handleError('获取作者头像失败', $e);
        }
    }

    // 获取作者链接
    public static function AuthorPermalink()
    {
        try {
            echo self::getArchive()->author->permalink;
        } catch (Exception $e) {
            self::handleError('获取作者链接失败', $e);
        }
    }

    // 获取文章字数
    public static function WordCount() {
        $cid = self::getArchive()->cid;
        $db = DB::getInstance();
        $text = $db->getArticleText($cid);
        $text = preg_replace("/[^\x{4e00}-\x{9fa5}]/u", "", $text);
        echo mb_strlen($text, 'UTF-8');
    }
}