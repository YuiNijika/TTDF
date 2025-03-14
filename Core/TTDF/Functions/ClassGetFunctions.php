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

    /**
     * 获取加载时间
     *  @param bool|null $echo 当设置为 true 时，会直接输出；
     *                        当设置为 false 时，则返回结果值。
     */
    public static function TimerStop(?bool $echo = true)
    {
        try {
            if ($echo) echo TTDF_TimerStop();
            ob_start();  // 开启输出缓冲
            echo TTDF_TimerStop();
            $content = ob_get_clean();  // 获取缓冲区内容并清除缓冲区
            return $content;
        } catch (Exception $e) {
            return self::handleError('获取加载时间失败', $e);
        }
    }
}