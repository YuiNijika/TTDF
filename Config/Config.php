<?php 
/**
 * 欢迎使用Typecho主题模板开发框架！
 * 使用说明 / 开发文档请查看README.md；
 * 如有任何不懂的问题欢迎联系作者<a href="https://space.bilibili.com/435502585"> · B站 · </a>提供帮助。
 * @author 鼠子Tomoriゞ
 * @version 1.1.2
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
// 配置默认Cravatar
$avatarCdn = 'https://cravatar.cn/avatar/';
// 定义常量
define('__TYPECHO_GRAVATAR_PREFIX__', $avatarCdn);