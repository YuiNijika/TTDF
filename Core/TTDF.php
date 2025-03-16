<?php 
/**
 * 欢迎使用Typecho主题模板开发框架！
 * @author 鼠子(Tomoriゞ)
 * @link https://github.com/ShuShuicu/Typecho-Theme-Development-Framework
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
// 配置默认Cravatar
$avatarCdn = 'https://cravatar.cn/avatar/';
// 定义常量
define('__TYPECHO_GRAVATAR_PREFIX__', $avatarCdn);

// 设置框架版本
define('__FRAMEWORK_VER__', '2.1.1');

require_once 'TTDF/Class.php';
require_once 'TTDF/Functions.php';
require_once 'TTDF/Json.php';
require_once 'Fields.php';
require_once 'Options.php';