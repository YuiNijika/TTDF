<?php 
/**
 * 欢迎使用Typecho主题模板开发框架！
 * 使用说明 / 开发文档请查看README.md；
 * 如有任何不懂的问题欢迎联系作者<a href="https://space.bilibili.com/435502585"> · B站 · </a>提供帮助。
 * @author 鼠子Tomoriゞ
 * @version 1.1.4
 * @link https://blog.miomoe.cn/
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
// Get功能
require_once 'Get.php';
// 主题设置功能
require_once 'Options.php';
// 引入框架功能
require_once 'Functions.php';
// 引入Json功能
require_once 'Json.php';

// 设置主题目录
define("THEME_URL", str_replace(Helper::options()->rootUrl, '', Helper::options()->themeUrl));
// 设置主题目录名称
define("THEME_NAME", str_replace("/usr/themes/", "", THEME_URL));

// 设置错误日志路径
ini_set('error_log', __TYPECHO_ROOT_DIR__ . THEME_URL . '/Config/error.log');
// 设置错误日志文件权限
ini_set('error_log_mode', 0644);

// 配置默认Cravatar
$avatarCdn = 'https://cravatar.cn/avatar/';
// 定义常量
define('__TYPECHO_GRAVATAR_PREFIX__', $avatarCdn);
