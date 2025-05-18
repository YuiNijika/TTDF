<?php 
/**
 * 欢迎使用Typecho主题模板开发框架！
 * @author 鼠子(Tomoriゞ)
 * @link https://github.com/ShuShuicu/TTDF
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// 是否开启自定义字段
define('__TTDF_FIELDS__', false); 
// 配置默认头像源
define('__TYPECHO_GRAVATAR_PREFIX__', 'https://cravatar.cn/avatar/');
// 设置 REST API 状态
// *主题注册设置项为 TTDF_RESTAPI_Switch * 则代理启用
define('__TTDF_RESTAPI__', false);
// 设置 REST API 路由
define('__TTDF_RESTAPI_ROUTE__', 'API');

// 加载核心文件
require_once 'TTDF/Main.php';