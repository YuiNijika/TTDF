<?php

/**
 * GetUser 方法
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class GetUser
{
    use ErrorHandler, SingletonWidget;

    private function __construct() {}
    private function __clone() {}
    public function __wakeup() {}

    // 获取用户名
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

    // 获取昵称
    public static function DisplayName($echo = true)
    {
        try {
            $name = self::getArchive()->author->name;
            if ($echo) {
                echo $name;
            } else {
                return $name;
            }
        } catch (Exception $e) {
            self::handleError('获取昵称失败', $e);
            if ($echo) {
                echo '';
            } else {
                return '';
            }
        }
    }

    // 获取用户头像
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

    // 获取用户头像URL
    public static function AvatarURL($size = 128, $default = 'mm', $rating = 'X', $echo = true)
    {
        try {
            $email = self::getArchive()->author->mail;
            $isSecure = self::getArchive()->request->isSecure();
            $avatarUrl = \Typecho\Common::gravatarUrl($email, $size, $rating, $default, $isSecure);

            if ($echo) {
                echo $avatarUrl;
            } else {
                return $avatarUrl;
            }
        } catch (Exception $e) {
            self::handleError('获取作者头像URL失败', $e);
            if ($echo) {
                echo '';
            } else {
                return '';
            }
        }
    }

    // 获取用户邮箱
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

    // 获取用户网站
    public static function WebSite($echo = true)
    {
        try {
            $url = self::getArchive()->author->url;
            if ($echo) {
                echo $url;
            } else {
                return $url;
            }
        } catch (Exception $e) {
            self::handleError('获取作者网站失败', $e);
            if ($echo) {
                echo '';
            } else {
                return '';
            }
        }
    }

    // 获取用户简介
    public static function Bio($echo = true)
    {
        try {
            $bio = self::getArchive()->author->userBio;
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

    // 获取用户组/角色
    public static function Role($echo = true)
    {
        try {
            $group = self::getArchive()->author->group;
            if ($echo) {
                echo $group;
            } else {
                return $group;
            }
        } catch (Exception $e) {
            self::handleError('获取作者组失败', $e);
            if ($echo) {
                echo '';
            } else {
                return '';
            }
        }
    }

    // 获取注册时间
    public static function Registered($format = 'Y-m-d H:i:s', $echo = true)
    {
        try {
            $time = self::getArchive()->author->created;
            $formatted = date($format, $time);
            if ($echo) {
                echo $formatted;
            } else {
                return $formatted;
            }
        } catch (Exception $e) {
            self::handleError('获取注册时间失败', $e);
            if ($echo) {
                echo '';
            } else {
                return '';
            }
        }
    }

    // 获取最后登录时间
    public static function LastLogin($format = 'Y-m-d H:i:s', $echo = true)
    {
        try {
            $time = self::getArchive()->author->logged;
            $formatted = date($format, $time);
            if ($echo) {
                echo $formatted;
            } else {
                return $formatted;
            }
        } catch (Exception $e) {
            self::handleError('获取最后登录时间失败', $e);
            if ($echo) {
                echo '';
            } else {
                return '';
            }
        }
    }

    // 获取文章数
    public static function PostCount($echo = true)
    {
        try {
            $count = self::getArchive()->author->postsNum;
            if ($echo) {
                echo $count;
            } else {
                return $count;
            }
        } catch (Exception $e) {
            self::handleError('获取文章数失败', $e);
            if ($echo) {
                echo '0';
            } else {
                return 0;
            }
        }
    }

    // 获取页面数量
    public static function PageCount($echo = true)
    {
        try {
            $db = \Typecho\Db::get();
            $count = $db->fetchObject($db->select(['COUNT(cid)' => 'num'])
                ->from('table.contents')
                ->where('type = ?', 'page')
                ->where('authorId = ?', self::getArchive()->author->uid)
                ->where('status = ?', 'publish'))->num;

            if ($echo) {
                echo $count;
            } else {
                return $count;
            }
        } catch (Exception $e) {
            self::handleError('获取页面数量失败', $e);
            if ($echo) {
                echo '0';
            } else {
                return 0;
            }
        }
    }

    // 获取作者链接
    public static function Permalink($echo = true)
    {
        try {
            $permalink = self::getArchive()->author->permalink;
            if ($echo) {
                echo $permalink;
            } else {
                return $permalink;
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
}
