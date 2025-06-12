<?php

/**
 * 欢迎使用Typecho主题模板开发框架！
 * @author 鼠子(Tomoriゞ)
 * @link https://github.com/ShuShuicu/TTDF
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
// 定义 TTDF 配置
global $defineTTDFConfig;
$defineTTDFConfig = [
    'Fields' => false, // 是否启用自定义字段
    'TyAjax' => false, // 是否启用TyAjax模块
    'CompressHtml' => true, // 是否启用HTML压缩
    'GravatarPrefix' => 'https://cravatar.cn/avatar/', // Gravatar头像源
    'RestApi' => false, // 是否启用RestApi
    'RestApiRoute' => 'ty-json', // RestApi路由
];

// 加载核心文件
require_once 'TTDF/Main.php';
