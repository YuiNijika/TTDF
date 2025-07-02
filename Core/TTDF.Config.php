<?php
/**
 * 欢迎使用Typecho主题模板开发框架！
 * @author 鼠子(Tomoriゞ)
 * @link https://github.com/ShuShuicu/TTDF
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
// 定义 TTDF 配置
global $defineConfig;
$defineConfig = [
    'Fields' => false, // 是否启用自定义字段
    'TyAjax' => false, // 是否启用TyAjax模块
    'CompressHtml' => true, // 是否启用HTML压缩
    'GravatarPrefix' => 'https://cravatar.cn/avatar/', // Gravatar头像源
    'RestApi' => false, // 是否启用RestApi (如果主题设置项注册为 `TTDF_RESTAPI_Switch` 则替代该设置
    'RestApiRoute' => 'ty-json', // RestApi路由
];

// 加载核心文件
require_once __DIR__ . '/TTDF/Main.php';
