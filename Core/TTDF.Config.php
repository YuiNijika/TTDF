<?php 
/**
 * 欢迎使用Typecho主题模板开发框架！
 * @author 鼠子(Tomoriゞ)
 * @link https://github.com/ShuShuicu/TTDF
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
// 定义 TTDF 配置
$defineTTDFConfig = [
    'Modules' => [ 
        'Fields' => false,
        'TyAjax' => false,
        'GravatarPrefix' => 'https://cravatar.cn/avatar/',
        'RestApi' => false,
        'RestApiRoute' => 'ty-json',
    ],
    'App' => [
        'Lang' => 'zh-CN', // 语言设置
    ],
];

// 加载核心文件
require_once 'TTDF/Main.php';