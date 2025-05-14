<?php 
/**
 * 欢迎使用Typecho主题模板开发框架！
 * @author 鼠子(Tomoriゞ)
 * @link https://github.com/ShuShuicu/Typecho-Theme-Development-Framework
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

if (version_compare(PHP_VERSION, '7.4', '<')) {
    die('您的PHP版本低于7.4, 请升级PHP版本!');
}

define('__TYPECHO_GRAVATAR_PREFIX__', $TTDF_Avatar ?? '');
// 设置框架版本
define('__FRAMEWORK_VER__', '2.2.1'); 
// 设置 REST API 状态
define('__TTDF_RESTAPI__', $TTDF_RESTAPI ?? false);
define('__TTDF_RESTAPI_ROUTE__', $TTDF_RESTAPI_ROUTE ?? 'API');

// 加载核心文件
require_once 'TTDF/Class.php';
require_once 'TTDF/Api.php';
require_once 'TTDF/TyAjax.php';
require_once 'TTDF/Functions.php';
require_once 'TTDF/Options.php';
require_once 'Setup.php';

if ($TTDF_Fields == true) {
    require_once 'Fields.php';
}