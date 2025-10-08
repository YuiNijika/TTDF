<?php
/**
 * TTDF 配置文件
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

return [
    // 应用配置
    'app' => [
        'debug' => true, // 调试模式
        'compress_html' => true, // HTML压缩
    ],

    // 插件配置
    'plugins' => [
        'fields' => [
            'enabled' => false, // 自定义字段插件
        ],
        'tyajax' => [
            'enabled' => false, // TyAjax模块
        ],
    ],

    // 模块配置
    'modules' => [
        'gravatar' => [
            'prefix' => 'https://cravatar.cn/avatar/', // Gravatar前缀
        ],
        'restapi' => [
            'enabled' => false, // 是否启用REST API
            'route' => 'ty-json', // REST API路由
            'override_setting' => 'RESTAPI_Switch', // 主题设置项名称
            'token' => [
                'enabled' => false, // 是否启用Token
                'value' => '1778273540', // Token值
            ],
            'limit' => [
                'get' => 'attachments', // 禁止GET请求类
                'post' => 'comments', // 禁止POST请求类
            ],
            'headers' => [
                'access_control_allow_origin' => '*', // 跨域配置
                'access_control_allow_methods' => 'GET,POST', // 允许的请求方法
            ],
        ],
    ],
];