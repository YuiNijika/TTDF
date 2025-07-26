<?php

/**
 * 注册路由
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class TTDF_AddRoute
{
    /**
     * 路由配置
     */
    private static $routeConfig = [
        'TTDF_AUTH_ROUTE' => [
            'url' => '/%path alphaslash 0%',
            'widget' => 'Widget_Archive',
            'action' => 'render'
        ],
        'TTDF_API_HOME' => [
            'url' => '/' . __TTDF_RESTAPI_ROUTE__,
            'widget' => 'Widget_Archive',
            'action' => 'render'
        ],
        'TTDF_API_PAGE' => [
            'url' => '/' . __TTDF_RESTAPI_ROUTE__ . '/%path alphaslash 0%',
            'widget' => 'Widget_Archive',
            'action' => 'render'
        ]
    ];
    
    /**
     * 注册所有路由
     */
    public static function registerRoutes()
    {
        foreach (self::$routeConfig as $name => $config) {
            Utils\Helper::addRoute(
                $name,
                $config['url'],
                $config['widget'],
                $config['action']
            );
        }
    }
    
    /**
     * 注销所有路由
     */
    public static function unregisterRoutes()
    {
        foreach (self::$routeConfig as $name => $config) {
            Utils\Helper::removeRoute($name);
        }
    }
    
    /**
     * 获取路由配置
     */
    public static function getRouteConfig()
    {
        return self::$routeConfig;
    }
}

// 执行路由注册
TTDF_AddRoute::registerRoutes();