<?php

/**
 * 主题核心文件
 * Theme core file
 * @link https://github.com/YuiNijika/TTDF
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
// 引入框架配置文件
require_once __DIR__ . '/Core/TTDF.Config.php';

/**
 * 主题自定义代码
 * theme custom code
 */
// 输出 WelCome
function WelCome()
{
    TTDF::Modules('WelCome');
}
