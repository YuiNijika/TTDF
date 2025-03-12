<?php
/**
 * GetJsonData Class
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class GetJsonData
{
    use ErrorHandler;

    private function __construct() {}
    private function __clone() {}
    public function __wakeup() {}

    private static function validateData($data, $field)
    {
        if (!is_array($data)) {
            self::handleError("JsonData: {$field}数据格式无效", new Exception());
            return false;
        }
        return true;
    }

    // 输出JSON数据
    public static function Tomori()
    {
        try {
            if (function_exists('outputJsonData')) {
                outputJsonData();
            }
        } catch (Exception $e) {
            self::handleError('输出JSON数据失败', $e);
        }
    }

    // 获取标题
    public static function JsonTitle($data)
    {
        if (!self::validateData($data, 'title')) {
            return '无效的数据格式';
        }
        return isset($data['title'])
            ? htmlspecialchars($data['title'], ENT_QUOTES, 'UTF-8')
            : '暂无标题';
    }

    // 获取内容
    public static function JsonContent($data)
    {
        if (!self::validateData($data, 'content')) {
            return '无效的数据格式';
        }
        return isset($data['content'])
            ? htmlspecialchars($data['content'], ENT_QUOTES, 'UTF-8')
            : '暂无内容';
    }

    // 获取日期
    public static function JsonDate($data)
    {
        if (!self::validateData($data, 'date')) {
            return '无效的数据格式';
        }
        return isset($data['date'])
            ? htmlspecialchars($data['date'], ENT_QUOTES, 'UTF-8')
            : '暂无日期';
    }

    // 获取链接
    public static function JsonUrl($data)
    {
        if (!self::validateData($data, 'url')) {
            return '无效的数据格式';
        }
        return isset($data['url'])
            ? htmlspecialchars($data['url'], ENT_QUOTES, 'UTF-8')
            : '暂无链接';
    }
}