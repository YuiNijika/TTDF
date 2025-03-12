<?php
/**
 * GetFunctions Class
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class GetFunctions
{
    use ErrorHandler;

    private function __construct() {}
    private function __clone() {}
    public function __wakeup() {}

    // 获取加载时间
    public static function TimerStop()
    {
        try {
            echo timer_stop();
        } catch (Exception $e) {
            self::handleError('获取加载时间失败', $e);
        }
    }

    // 获取文章字数
    public static function ArtCount($cid)
    {
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
    public static function WordCount($content, $echo = true)
    {
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