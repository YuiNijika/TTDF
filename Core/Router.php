<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
if ($TTDF_ROUTE) {
    class TTDF_Router
    {
        private static $dbApi;

        public static function handleRequest()
        {
            try {
                // 获取请求路径和方法
                $requestUri = $_SERVER['REQUEST_URI'];
                $basePath = '/';

                // 使用 parse_url 提取路径部分，忽略查询参数
                $path = parse_url($requestUri, PHP_URL_PATH);

                // 标准化路径
                $path = trim($path, '/');

                $method = $_SERVER['REQUEST_METHOD'];

                // 根据路由分发请求
                switch ($path) {
                    case 'test1':
                        self::GetTest($response);
                        echo $response; // 输出响应
                        break;
                    default:
                        // 如果没有匹配路由则不处理
                        return;
                }
            } catch (Exception $e) {
                // 异常处理
                echo "500 Internal Server Error";
            }
            
        }

        private static function GetTest(&$response)
        {
            $response = "成功注册测试路由！";
        }
    }

    // 注册路由
    $requestUri = $_SERVER['REQUEST_URI'];
    $basePath = '/';
    if (strpos($requestUri, $basePath) === 0) {
        \Typecho\Response::getInstance()->setStatus(200); // 设置响应状态码
        Get::Template('AppHeader');
        TTDF_Router::handleRequest();
        Get::Template('AppFooter');
        exit;
    }
}
