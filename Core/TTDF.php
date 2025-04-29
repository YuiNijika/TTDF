<?php 
/**
 * 欢迎使用Typecho主题模板开发框架！
 * @author 鼠子(Tomoriゞ)
 * @link https://github.com/ShuShuicu/Typecho-Theme-Development-Framework
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
// 配置默认Cravatar
$TTDF_Cravatar = 'https://cravatar.cn/avatar/';
// 定义常量
define('__TYPECHO_GRAVATAR_PREFIX__', $TTDF_Cravatar);

// 设置框架版本
define('__FRAMEWORK_VER__', '2.1.3');

// 设置 REST API 状态
define('__TTDF_RESTAPI__', false); // true为开启，false为关闭
// 设置 REST API 路由
define('__TTDF_RESTAPI_ROUTE__', 'API');

require_once 'TTDF/Class.php';
require_once 'TTDF/Api.php';
require_once 'TTDF/Functions.php';
require_once 'Fields.php';
require_once 'Options.php';