<?php 
/**
 * 主题配置文件
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 加载资源文件
 * @var array $load_dir_name 资源文件目录名称
 * @var array $load_head_css 加载head标签css
 * @var array $load_head_js 加载head标签js
 * @var array $load_foot_js 加载body标签js
 * @example $load_switch = 启用框架加载资源文件
 */
define('__LOAD_SWITCH__', true); // 是否开启加载资源文件
$load_dir_name = 'Assets'; // 资源文件目录名称
$load_head_css = [
    'main.css',
    '_ttdf/message.css',
];
$load_head_js = [
    '_ttdf/jquery.min.js',
];
$load_foot_js = [
    'main.js',
    '_ttdf/ajax.js',
    '_ttdf/message.min.js',
];

define('__TTDF_FIELDS__', false); // 是否开启自定义字段
$TTDF_Avatar = 'https://cravatar.cn/avatar/'; // 配置Avatar源

/**
 * 路由配置
 * @var bool $TTDF_ROUTE 是否开启路由功能
 * @var bool $TTDF_RESTAPI 是否开启 `REST API`
 * @var string $TTDF_RESTAPI_ROUTE `REST API`的路由配置
 * @example 主题注册设置项 TTDF_RESTAPI_Switch 值为 true 时，开启 REST API 功能
 */
$TTDF_ROUTE = false;
$TTDF_RESTAPI = false;
$TTDF_RESTAPI_ROUTE = 'API'; 

// 引入框架配置文件
require_once 'Core/TTDF.php';

/**
 * 自定义function代码
 * @example 下方写入主题的自定义代码
 */
