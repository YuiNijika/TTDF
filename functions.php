<?php
/**
 * 主题核心文件
 * Theme core file
 * @link https://github.com/YuiNijika/TTDF
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * TTDF配置
 * TTDF Config
 */
define('TTDF_CONFIG', [
    'FIELDS_ENABLED' => false, // 是否启用自定义字段
    'TYAJAX_ENABLED' => false, // 是否启用TyAjax模块
    'COMPRESS_HTML' => true, // 是否启用HTML压缩
    'GRAVATAR_PREFIX' => 'https://cravatar.cn/avatar/', // Gravatar前缀
    'REST_API' => [
        'ENABLED' => true, // 是否启用REST API
        'ROUTE' => 'ty-json', // REST API路由
        'TOKEN' => [
            'ENABLED' => true, // 是否启用Token验证
            'VALUE' => '1778273540' // Token值
        ],
        'OVERRIDE_SETTING' => 'TTDF_RESTAPI_Switch' // 主题设置项名称，用于覆盖REST API开关
    ]
]);
// 加载核心文件
require_once __DIR__ . '/Core/TTDF/Main.php';

/**
 * 主题自定义代码
 * theme custom code
 */
// 输出 WelCome
function WelCome() {
    TTDF::Modules('WelCome');
}
