<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class UserInfo
{
    use ErrorHandler, SingletonWidget;

    private function __construct() {}
    private function __clone() {}
    public function __wakeup() {}

    /**
     * 获取当前用户或作者对象
     * @param bool $author 是否获取作者(而非当前登录用户)
     * @return object
     */
    private static function getUserObject($author = false)
    {
        try {
            return $author ? self::getArchive()->author : self::getArchive()->user;
        } catch (Exception $e) {
            self::handleError('获取用户对象失败', $e);
            return null;
        }
    }

    // ==================== 基本信息 ====================

    /**
     * 获取用户名
     * @param bool $echo 是否直接输出
     * @param bool $author 是否获取作者(而非当前登录用户)
     * @return string
     */
    public static function Name($echo = true, $author = false)
    {
        try {
            $name = self::getUserObject($author)->screenName;
            return self::output($name, $echo);
        } catch (Exception $e) {
            self::handleError('获取用户名失败', $e);
            return self::output('', $echo);
        }
    }

    /**
     * 获取用户显示名称
     * @param bool $echo 是否直接输出
     * @param bool $author 是否获取作者(而非当前登录用户)
     * @return string
     */
    public static function DisplayName($echo = true, $author = false)
    {
        try {
            $name = self::getUserObject($author)->name;
            return self::output($name ?: self::Name(false, $author), $echo);
        } catch (Exception $e) {
            self::handleError('获取用户显示名称失败', $e);
            return self::output('', $echo);
        }
    }

    // ==================== 头像相关 ====================

    /**
     * 获取用户头像
     * @param int $size 头像尺寸
     * @param bool $echo 是否直接输出
     * @param bool $author 是否获取作者(而非当前登录用户)
     * @return string
     */
    public static function Avatar($size = 128, $echo = true, $author = false)
    {
        try {
            $avatar = self::getUserObject($author)->gravatar($size);
            return self::output($avatar, $echo);
        } catch (Exception $e) {
            self::handleError('获取用户头像失败', $e);
            return self::output('', $echo);
        }
    }

    /**
     * 获取用户头像URL
     * @param int $size 头像尺寸
     * @param string $default 默认头像类型
     * @param string $rating 头像分级
     * @param bool $echo 是否直接输出
     * @param bool $author 是否获取作者(而非当前登录用户)
     * @return string
     */
    public static function AvatarURL($size = 128, $default = 'mm', $rating = 'X', $echo = true, $author = false)
    {
        try {
            $email = self::getUserObject($author)->mail;
            $isSecure = self::getArchive()->request->isSecure();
            $avatarUrl = \Typecho\Common::gravatarUrl($email, $size, $rating, $default, $isSecure);
            return self::output($avatarUrl, $echo);
        } catch (Exception $e) {
            self::handleError('获取用户头像URL失败', $e);
            return self::output('', $echo);
        }
    }

    // ==================== 联系信息 ====================

    /**
     * 获取用户邮箱
     * @param bool $echo 是否直接输出
     * @param bool $author 是否获取作者(而非当前登录用户)
     * @return string
     */
    public static function Email($echo = true, $author = false)
    {
        try {
            $email = self::getUserObject($author)->mail;
            return self::output($email, $echo);
        } catch (Exception $e) {
            self::handleError('获取用户邮箱失败', $e);
            return self::output('', $echo);
        }
    }

    /**
     * 获取用户网站
     * @param bool $echo 是否直接输出
     * @param bool $author 是否获取作者(而非当前登录用户)
     * @return string
     */
    public static function WebSite($echo = true, $author = false)
    {
        try {
            $url = self::getUserObject($author)->url;
            return self::output($url, $echo);
        } catch (Exception $e) {
            self::handleError('获取用户网站失败', $e);
            return self::output('', $echo);
        }
    }

    // ==================== 用户资料 ====================

    /**
     * 获取用户简介
     * @param bool $echo 是否直接输出
     * @param bool $author 是否获取作者(而非当前登录用户)
     * @return string
     */
    public static function Bio($echo = true, $author = false)
    {
        try {
            $bio = self::getUserObject($author)->bio;
            return self::output($bio, $echo);
        } catch (Exception $e) {
            self::handleError('获取用户简介失败', $e);
            return self::output('', $echo);
        }
    }

    /**
     * 获取用户角色/用户组
     * @param bool $echo 是否直接输出
     * @param bool $author 是否获取作者(而非当前登录用户)
     * @return string
     */
    public static function Group($echo = true, $author = false)
    {
        try {
            $group = self::getUserObject($author)->group;
            return self::output($group, $echo);
        } catch (Exception $e) {
            self::handleError('获取用户角色失败', $e);
            return self::output('', $echo);
        }
    }

    // ==================== 时间信息 ====================

    /**
     * 获取用户注册时间
     * @param string $format 时间格式
     * @param bool $echo 是否直接输出
     * @param bool $author 是否获取作者(而非当前登录用户)
     * @return string
     */
    public static function Registered($format = 'Y-m-d H:i:s', $echo = true, $author = false)
    {
        try {
            $time = self::getUserObject($author)->registered;
            $formatted = date($format, $time);
            return self::output($formatted, $echo);
        } catch (Exception $e) {
            self::handleError('获取用户注册时间失败', $e);
            return self::output('', $echo);
        }
    }

    /**
     * 获取用户最后登录时间
     * @param string $format 时间格式
     * @param bool $echo 是否直接输出
     * @param bool $author 是否获取作者(而非当前登录用户)
     * @return string
     */
    public static function LastLogin($format = 'Y-m-d H:i:s', $echo = true, $author = false)
    {
        try {
            $time = self::getUserObject($author)->logged;
            $formatted = date($format, $time);
            return self::output($formatted, $echo);
        } catch (Exception $e) {
            self::handleError('获取用户最后登录时间失败', $e);
            return self::output('', $echo);
        }
    }

    // ==================== 统计信息 ====================

    /**
     * 获取用户文章数量
     * @param bool $echo 是否直接输出
     * @param bool $author 是否获取作者(而非当前登录用户)
     * @return int
     */
    public static function PostCount($echo = true, $author = false)
    {
        try {
            $count = self::getUserObject($author)->postsNum;
            return self::output($count, $echo);
        } catch (Exception $e) {
            self::handleError('获取用户文章数量失败', $e);
            return self::output(0, $echo);
        }
    }

    /**
     * 获取用户页面数量
     * @param bool $echo 是否直接输出
     * @param bool $author 是否获取作者(而非当前登录用户)
     * @return int
     */
    public static function PageCount($echo = true, $author = false)
    {
        try {
            $count = self::getUserObject($author)->pagesNum;
            return self::output($count, $echo);
        } catch (Exception $e) {
            self::handleError('获取用户页面数量失败', $e);
            return self::output(0, $echo);
        }
    }

    // ==================== 链接相关 ====================

    /**
     * 获取作者链接
     * @param bool $echo 是否直接输出
     * @return string
     */
    public static function Permalink($echo = true)
    {
        try {
            $permalink = self::getUserObject(true)->permalink;
            return self::output($permalink, $echo);
        } catch (Exception $e) {
            self::handleError('获取作者链接失败', $e);
            return self::output('', $echo);
        }
    }

    // ==================== 评论相关方法 ====================

    /**
     * 获取用户评论数量
     * @param bool $echo 是否直接输出
     * @param bool $author 是否获取作者(而非当前登录用户)
     * @return int
     */
    public static function CommentCount($echo = true, $author = false)
    {
        try {
            $count = self::getUserObject($author)->commentsNum;
            return self::output($count, $echo);
        } catch (Exception $e) {
            self::handleError('获取用户评论数量失败', $e);
            return self::output(0, $echo);
        }
    }

    /**
     * 获取用户最新评论
     * @param int $limit 获取数量
     * @param bool $author 是否获取作者(而非当前登录用户)
     * @return Typecho_Db_Query
     */
    public static function RecentComments($limit = 5, $author = false)
    {
        try {
            $userId = self::getUserObject($author)->uid;
            $db = Typecho_Db::get();
            $select = $db->select()->from('table.comments')
                ->where('authorId = ?', $userId)
                ->where('status = ?', 'approved')
                ->order('created', Typecho_Db::SORT_DESC)
                ->limit($limit);

            return $db->fetchAll($select);
        } catch (Exception $e) {
            self::handleError('获取用户最新评论失败', $e);
            return array();
        }
    }

    /**
     * 获取用户评论过的文章数量
     * @param bool $echo 是否直接输出
     * @param bool $author 是否获取作者(而非当前登录用户)
     * @return int
     */
    public static function CommentedPostCount($echo = true, $author = false)
    {
        try {
            $userId = self::getUserObject($author)->uid;
            $db = Typecho_Db::get();
            $select = $db->select('DISTINCT cid')
                ->from('table.comments')
                ->where('authorId = ?', $userId)
                ->where('status = ?', 'approved');

            $count = count($db->fetchAll($select));
            return self::output($count, $echo);
        } catch (Exception $e) {
            self::handleError('获取用户评论过的文章数量失败', $e);
            return self::output(0, $echo);
        }
    }

    /**
     * 获取用户评论的HTML输出
     * @param int $limit 获取数量
     * @param bool $author 是否获取作者(而非当前登录用户)
     * @return string
     */
    public static function CommentsHTML($limit = 5, $author = false)
    {
        try {
            $comments = self::RecentComments($limit, $author);
            $html = '';

            foreach ($comments as $comment) {
                $html .= '<div class="user-comment">';
                $html .= '<p class="comment-content">' . htmlspecialchars($comment['text']) . '</p>';
                $html .= '<p class="comment-meta">';
                $html .= '发布于: ' . date('Y-m-d H:i', $comment['created']);
                $html .= ' | 文章: <a href="' . $comment['permalink'] . '">' . htmlspecialchars($comment['title']) . '</a>';
                $html .= '</p></div>';
            }

            return $html;
        } catch (Exception $e) {
            self::handleError('生成用户评论HTML失败', $e);
            return '';
        }
    }

    // ==================== 辅助方法 ====================

    /**
     * 根据参数决定输出或返回
     * @param mixed $content 要输出的内容
     * @param bool $echo 是否直接输出
     * @return mixed
     */
    private static function output($content, $echo)
    {
        if ($echo) {
            echo $content;
            return null;
        }
        return $content;
    }
}