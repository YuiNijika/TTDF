<?php

/**
 * Author Class
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class GetAuthor extends Typecho_Widget
{
    use ErrorHandler, SingletonWidget;

    private function __construct() {}
    private function __clone() {}
    public function __wakeup() {}

    // 获取作者
    public static function Name($echo = true)
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
    public static function Avatar($size = 128, $echo = true)
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
    public static function Permalink($echo = true)
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

    // 获取作者邮箱
    public static function Email($echo = true)
    {
        try {
            $email = self::getArchive()->author->mail;
            if ($echo) {
                echo $email;
            } else {
                return $email;
            }
        } catch (Exception $e) {
            self::handleError('获取作者邮箱失败', $e);
            if ($echo) {
                echo '';
            } else {
                return '';
            }
        }
    }

    // 获取作者简介
    public static function Bio($echo = true)
    {
        try {
            $bio = self::getArchive()->author->bio;
            if ($echo) {
                echo $bio;
            } else {
                return $bio;
            }
        } catch (Exception $e) {
            self::handleError('获取作者简介失败', $e);
            if ($echo) {
                echo '';
            } else {
                return '';
            }
        }
    }
}
