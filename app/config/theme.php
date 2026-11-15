<?php

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

return [
    'General' => [
        'title' => '基础设置',
        'fields' => [
            [
                'type' => 'Text',
                'name' => 'SubTitle',
                'value' => '',
                'label' => '副标题',
                'description' => '显示在站点标题下方，留空则不显示。',
            ],
            [
                'type' => 'Textarea',
                'name' => 'FooterText',
                'value' => '',
                'label' => '页脚文本',
                'description' => '支持纯文本，输出时会自动转义。',
            ],
        ],
    ],
    'Templates' => [
        'title' => '页面模板',
        'fields' => [
            [
                'type' => 'TemplateMap',
                'name' => 'Templates',
                'value' => [
                    'post' => 'Post',
                    'page' => 'Page',
                    'index' => 'Archive',
                    'archive' => 'Archive',
                    'category' => 'Archive',
                    'tag' => 'Archive',
                    'search' => 'Archive',
                    'author' => 'Archive',
                    'date' => 'Archive',
                    '404' => 'NotFound',
                ],
                'components' => ['Post', 'Page', 'Archive', 'WelCome', 'NotFound'],
                'label' => '模板映射',
                'description' => '为 Typecho 模板入口选择 app/components 下可独立渲染的页面组件；仅在 components 白名单内的组件会显示并生效。留空时使用入口模板回退内容，404 槽位应选择 NotFound。',
            ],
        ],
    ],
    'Runtime' => [
        'title' => '运行配置',
        'fields' => [
            [
                'type' => 'Switch',
                'name' => 'Debug_Switch',
                'value' => false,
                'label' => '调试模式',
                'description' => '开启后记录详细错误堆栈并显示错误详情；生产环境请关闭。',
            ],
            [
                'type' => 'Switch',
                'name' => 'RESTAPI_Switch',
                'value' => false,
                'label' => '公开 REST API',
                'description' => '关闭后仅保留需要管理员登录与 nonce 的后台接口。',
            ],
            [
                'type' => 'Textarea',
                'name' => 'CORS_Origins',
                'value' => '',
                'label' => 'CORS 白名单',
                'description' => '每行一个完整 Origin，例如 https://example.com。',
            ],
            [
                'type' => 'Switch',
                'name' => 'REST_Token_Switch',
                'value' => false,
                'label' => '启用公开 REST Token',
                'description' => '开启后，/ttdf/v1 公共只读接口必须携带 Authorization: Bearer <token>；Token 值来自下方环境变量。后台管理接口不受影响。',
            ],
            [
                'type' => 'Text',
                'name' => 'REST_Token_Env',
                'value' => 'TTDF_REST_TOKEN',
                'label' => '公共 REST Token 环境变量名',
                'description' => '开关开启时才生效。这里填环境变量名，不是 Token 本身；例如 TTDF_REST_TOKEN，需在 PHP-FPM 或 Web 服务器环境中设置同名值（至少 32 字符）。开关开启但变量未配置时，公共 REST 返回 500。',
            ],
            [
                'type' => 'Text',
                'name' => 'Assets_CDN',
                'value' => '',
                'label' => '静态资源 CDN',
                'description' => '留空使用主题本地资源；仅支持 http(s) 地址，非法地址会自动回退。',
            ],
        ],
    ],
];
