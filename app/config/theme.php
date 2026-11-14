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
                'label' => '模板映射',
                'description' => '为 Typecho 模板入口选择 app/components 下的组件；留空时使用模板内置回退内容，归档类槽位会先回退到 archive 槽位。',
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
                'type' => 'Text',
                'name' => 'REST_Token_Env',
                'value' => 'TTDF_REST_TOKEN',
                'label' => 'Token 环境变量名',
                'description' => '留空表示不启用 Token；Token 至少 32 字符。',
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
