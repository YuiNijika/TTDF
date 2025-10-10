<?php

/**
 * 注册路由
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class TTDF_Route
{
    /**
     * 注册TTDF API路由
     */
    public static function registerRoutes()
    {
        self::registerHomeRoute();
        self::registerApiRoute();
    }

    /**
     * 注册API首页路由
     */
    private static function registerHomeRoute()
    {
        Utils\Helper::addRoute(
            'TTDF_API_HOME', // 路由名称
            '/' . __TTDF_RESTAPI_ROUTE__, // 路由路径
            'Widget_Archive', // 组件名称
            'render' // 组件动作方法
        );
    }

    /**
     * 注册API子路由
     */
    private static function registerApiRoute()
    {
        Utils\Helper::addRoute(
            'TTDF_API', // 路由名称
            '/' . __TTDF_RESTAPI_ROUTE__ . '/%path alphaslash 0%', // 路由路径
            'Widget_Archive', // 组件名称
            'render' // 组件动作方法
        );
    }

    /**
     * 注销路由
     */
    public static function unregisterRoutes()
    {
        Utils\Helper::removeRoute('TTDF_API_HOME');
        Utils\Helper::removeRoute('TTDF_API');
    }
}

// 注册路由
TTDF_Route::registerRoutes();

// 注销路由
// TTDF_Route::unregisterRoutes();