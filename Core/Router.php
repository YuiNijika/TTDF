<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
if ($TTDF_ROUTE) {
    class TTDF_Router
    {
        // 路由列表
        private static $allowedRoutes = [
            'test' => 'handleTest', // 路由 => 处理方法
        ];

        /**
         * 初始化路由检测
         */
        public static function init()
        {
            $requestPath = self::getRequestPath();

            // 如果匹配到自定义路由，则处理并终止执行
            if (isset(self::$allowedRoutes[$requestPath])) {
                self::handleRoute($requestPath);
            }
            // 否则，Typecho 继续正常流程
        }

        /**
         * 获取当前请求路径
         */
        private static function getRequestPath()
        {
            $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
            $path = trim(parse_url($requestUri, PHP_URL_PATH), '/');
            return $path;
        }

        /**
         * 处理匹配到的路由
         */
        private static function handleRoute($route)
        {
            $response = Typecho_Response::getInstance();
            $response->setStatus(200);

            // 输出页头
            Get::Template('AppHeader');

            // 调用对应的处理方法
            $method = self::$allowedRoutes[$route];
            if (method_exists(__CLASS__, $method)) {
                self::$method();
            } else {
                $response->setStatus(404);
                echo "404 Route Handler Not Found";
            }

            // 输出页脚
            Get::Template('AppFooter');
            exit;
        }

        /**
         * 处理 /test 路由
         */
        private static function handleTest()
        {
            echo '成功注册测试路由！';
        }
    }

    // 初始化路由检测
    TTDF_Router::init();
}
