<?php 
/**
 * 欢迎使用Typecho主题模板开发框架！
 * @author 鼠子(Tomoriゞ)
 * @link https://github.com/ShuShuicu/TTDF
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

$TTDFConfig = [
    'Fields' => false, // 是否开启自定义字段
    'TyAjax' => false, // 是否启用TyAjax扩展
    'GravatarPrefix' => 'https://cravatar.cn/avatar/', // 配置默认头像源
    'RestApi' => false, // 设置 REST API 状态
    'RestApiRoute' => 'ty-json', // 设置 REST API 路由
];

// 配置默认头像源
define('__TYPECHO_GRAVATAR_PREFIX__', $TTDFConfig['GravatarPrefix'] ?? 'https://cravatar.cn/avatar/');
// 主题注册设置项为 * TTDF_RESTAPI_Switch * 则代理启用REST API
define('__TTDF_RESTAPI__', $TTDFConfig['RestApi'] ?? false);
define('__TTDF_RESTAPI_ROUTE__', $TTDFConfig['RestApiRoute'] ?? 'ty-json');

// 加载核心文件
require_once 'TTDF/Main.php';