<?php 
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * REST API 配置
 * @var bool $TTDF_RESTAPI 是否开启
 * @var string $TTDF_RESTAPI_ROUTE 路由配置
 * @example 主题注册设置项 TTDF_RESTAPI_Switch 值为 true 时，开启 REST API 功能
 */
$TTDF_RESTAPI = false; 
$TTDF_RESTAPI_ROUTE = 'API'; 

$TTDF_Fields = false; // 是否开启自定义字段

// 配置Avatar源
$TTDF_Avatar = 'https://cravatar.cn/avatar/'; 

// 引入框架配置文件
require_once 'Core/TTDF.php';

/**
 * 注册load_code钩子
 * @example 可通过load_code钩子，加载自定义的function文件，实现自定义功能
 */
TTDF_Hook::do_action('load_code');

/**
 * 自定义function代码
 * @example 下方写入主题的自定义代码
 */
