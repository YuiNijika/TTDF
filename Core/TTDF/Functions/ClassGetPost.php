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
    public static function Title($echo = true)
    {
        try {
            $title = self::getArchive()->title;
            if ($echo) {
                echo $title;
            } else {
                return $title;
            }
        } catch (Exception $e) {
            self::handleError('获取标题失败', $e);
            if ($echo) {
                echo '';
            } else {
                return '';
            }
        }
    }

    // 获取日期
    public static function Date($format = 'Y-m-d', $echo = true)
    {
        try {
            $date = self::getArchive()->date($format);
            if ($echo) {
                echo $date;
            } else {
                return $date;
            }
        } catch (Exception $e) {
            $result = self::handleError('获取日期失败', $e, '');
            if ($echo) {
                echo $result;
            } else {
                return $result;
            }
        }
    }

    // 获取分类
    public static function Category($split = ',', $link = true, $default = '暂无分类', $echo = true)
    {
        try {
            $category = self::getArchive()->category($split, $link, $default);
            if ($echo) {
                echo $category;
            } else {
                return $category;
            }
        } catch (Exception $e) {
            self::handleError('获取分类失败', $e);
            if ($echo) {
                echo $default;
            } else {
                return $default;
            }
        }
    }

    // 获取标签
    public static function Tags($split = ',', $link = true, $default = '暂无标签', $echo = true)
    {
        try {
            $tags = self::getArchive()->tags($split, $link, $default);
            if ($echo) {
                echo $tags;
            } else {
                return $tags;
            }
        } catch (Exception $e) {
            self::handleError('获取标签失败', $e);
            if ($echo) {
                echo $default;
            } else {
                return $default;
            }
        }
    }

    // 获取摘要
    public static function Excerpt($length = 0, $echo = true)
    {
        try {
            $excerpt = strip_tags(self::getArchive()->excerpt);
            if ($length > 0) {
                $excerpt = mb_substr($excerpt, 0, $length, 'UTF-8');
            }
            if ($echo) {
                echo $excerpt;
            } else {
                return $excerpt;
            }
        } catch (Exception $e) {
            self::handleError('获取摘要失败', $e);
            if ($echo) {
                echo '';
            } else {
                return '';
            }
        }
    }

    // 获取永久链接
    public static function Permalink($echo = true)
    {
        try {
            $permalink = self::getArchive()->permalink;
            if ($echo) {
                echo $permalink;
            } else {
                return $permalink;
            }
        } catch (Exception $e) {
            self::handleError('获取永久链接失败', $e);
            if ($echo) {
                echo '';
            } else {
                return '';
            }
        }
    }

    // 获取内容
    public static function Content($echo = true)
    {
        try {
            $content = self::getArchive()->content;
            if ($echo) {
                echo $content;
            } else {
                return $content;
            }
        } catch (Exception $e) {
            self::handleError('获取内容失败', $e);
            if ($echo) {
                echo '';
            } else {
                return '';
            }
        }
    }

    // 获取标题
    public static function ArchiveTitle($format = '', $default = '', $connector = '', $echo = true)
    {
        try {
            if (empty($format)) {
                $archiveTitle = self::getArchive()->archiveTitle;
            } else {
                $archiveTitle = self::getArchive()->archiveTitle($format, $default, $connector);
            }
            if ($echo) {
                echo $archiveTitle;
            } else {
                return $archiveTitle;
            }
        } catch (Exception $e) {
            self::handleError('获取标题失败', $e);
            if ($echo) {
                echo '';
            } else {
                return '';
            }
        }
    }

    // 获取作者
    public static function Author($echo = true)
    {
        try {
            $author = self::getArchive()->author->screenName;
            if ($echo) {
                echo $author;
            } else {
                return $author;
            }
        } catch (Exception $e) {
            self::handleError('获取作者失败', $e);
            if ($echo) {
                echo '';
            } else {
                return '';
            }
        }
    }

    // 获取作者头像
    public static function AuthorAvatar($size = 128, $echo = true)
    {
        try {
            $avatar = self::getArchive()->author->gravatar($size);
            if ($echo) {
                echo $avatar;
            } else {
                return $avatar;
            }
        } catch (Exception $e) {
            self::handleError('获取作者头像失败', $e);
            if ($echo) {
                echo '';
            } else {
                return '';
            }
        }
    }

    // 获取作者链接
    public static function AuthorPermalink($echo = true)
    {
        try {
            $authorPermalink = self::getArchive()->author->permalink;
            if ($echo) {
                echo $authorPermalink;
            } else {
                return $authorPermalink;
            }
        } catch (Exception $e) {
            self::handleError('获取作者链接失败', $e);
            if ($echo) {
                echo '';
            } else {
                return '';
            }
        }
    }

    /**
     * 通过查询数据库获取
     * @param bool $echo 是否输出
     * @return int|string
     * @throws Typecho_Db_Exception
     */
    // 获取文章字数
    public static function WordCount($echo = true)
    {
        try {
            $cid = self::getArchive()->cid;
            $db = DB::getInstance();
            $text = $db->getArticleText($cid);
            $text = preg_replace("/[^\x{4e00}-\x{9fa5}]/u", "", $text);
            $wordCount = mb_strlen($text, 'UTF-8');
            if ($echo) {
                echo $wordCount;
            } else {
                return $wordCount;
            }
        } catch (Exception $e) {
            self::handleError('获取文章字数失败', $e);
            if ($echo) {
                echo '';
            } else {
                return '';
            }
        }
    }
    // 获取文章数量
    public static function PostsNum($echo = true)
    {
        try {
            $db = DB::getInstance();
            $postsNum = $db->getArticleCount();
            if ($echo) {
                echo $postsNum;
            } else {
                return $postsNum;
            }
        } catch (Exception $e) {
            self::handleError('获取文章数量失败', $e);
            if ($echo) {
                echo '';
            } else {
                return '';
            }
        }
    }
    
    // 获取文章标题
    public static function DB_Title($echo = true)
    {
        try {
            $cid = self::getArchive()->cid;
            $db = DB::getInstance();
            $title = $db->getArticleTitle($cid);
            if ($echo) {
                echo $title;
            } else {
                return $title;
            }
        } catch (Exception $e) {
            self::handleError('获取文章标题失败', $e);
            if ($echo) {
                echo '';
            } else {
                return '';
            }
        }
    }

    // 获取文章内容 Markdown 格式
    public static function DB_Content($echo = true)
    {
        try {
            $cid = self::getArchive()->cid;
            $db = DB::getInstance();
            $content = $db->getArticleContent($cid);
            if ($echo) {
                echo $content;
            } else {
                return $content;
            }
        } catch (Exception $e) {
            self::handleError('获取文章内容失败', $e);
            if ($echo) {
                echo '';
            } else {
                return '';
            }
        }
    }

    // 获取文章内容 HTML 格式
    public static function DB_Content_Html($echo = true)
    {
        try {
            $cid = self::getArchive()->cid;
            $db = DB::getInstance();
            $content = $db->getArticleContent($cid);
            $content = Markdown::convert($content);
            if ($echo) {
                echo $content;
            } else {
                return $content;
            }
        } catch (Exception $e) {
            self::handleError('获取文章内容失败', $e);
            if ($echo) {
                echo '';
            } else {
                return '';
            }
        }
    }
}