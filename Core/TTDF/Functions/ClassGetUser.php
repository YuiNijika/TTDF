<?php
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
            $user = self::getArchive()->user->screenName;
            if ($echo) {
                echo $user;
            }
        } catch (Exception $e) {
            if ($echo) {
                echo '';
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

    // 获取用户邮箱
    public static function Email($echo = true)
    {
        try {
            $email = self::getArchive()->user->mail;
            if ($echo) {
                echo $email;
            } else {
                return $email;
            }
        } catch (Exception $e) {
            self::handleError('获取用户邮箱失败', $e);
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
            $bio = self::getArchive()->user->bio;
            if ($echo) {
                echo $bio;
            } else {
                return $bio;
            }
        } catch (Exception $e) {
            self::handleError('获取用户简介失败', $e);
            if ($echo) {
                echo '';
            } else {
                return '';
            }
        }
    }

    // 获取用户角色
    public static function Group($echo = true)
    {
        try {
            $role = self::getArchive()->user->group;
            if ($echo) {
                echo $role;
            } else {
                return $role;
            }
        } catch (Exception $e) {
            self::handleError('获取用户角色失败', $e);
            if ($echo) {
                echo '';
            } else {
                return '';
            }
        }
    }

    // 获取用户注册时间
    public static function Registered($echo = true)
    {
        try {
            $registered = self::getArchive()->user->registered;
            if ($echo) {
                echo $registered;
            } else {
                return $registered;
            }
        } catch (Exception $e) {
            self::handleError('获取用户注册时间失败', $e);
            if ($echo) {
                echo '';
            } else {
                return '';
            }
        }
    }

    // 获取用户最后登录时间
    public static function LastLogin($echo = true)
    {
        try {
            $lastLogin = self::getArchive()->user->logged;
            if ($echo) {
                echo $lastLogin;
            } else {
                return $lastLogin;
            }
        } catch (Exception $e) {
            self::handleError('获取用户最后登录时间失败', $e);
            if ($echo) {
                echo '';
            } else {
                return '';
            }
        }
    }

    // 获取用户文章数量
    public static function PostCount($echo = true)
    {
        try {
            $postCount = self::getArchive()->user->postsNum;
            if ($echo) {
                echo $postCount;
            } else {
                return $postCount;
            }
        } catch (Exception $e) {
            self::handleError('获取用户文章数量失败', $e);
            if ($echo) {
                echo '';
            } else {
                return '';
            }
        }
    }
}
