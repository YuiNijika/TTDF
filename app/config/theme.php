<?php

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

return [
    'General' => [
        'title' => '基础设置',
        'tabs' => [
            'general' => [
                'title' => '一般设置',
                'description' => '站点标识、副标题与页脚文案。',
                'fields' => [
                    [
                        'type' => 'Text',
                        'name' => 'Logo',
                        'value' => '',
                        'label' => '站点 Logo',
                        'description' => '从媒体库选择图片；留空时显示站点标题。值保存为图片地址。',
                        'media' => true,
                        'aliases' => ['logoUrl'],
                    ],
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
            'colors' => [
                'title' => '颜色设置',
                'description' => '浅色与深色模式下的主题色和页面底色。',
                'fields' => [
                    [
                        'type' => 'Select',
                        'name' => 'Color_Mode',
                        'value' => 'auto',
                        'label' => '默认配色',
                        'description' => '访客首次访问时使用的配色；页头右上角的切换按钮仍可临时覆盖，选择会保存在访客浏览器里。',
                        'options' => [
                            'auto' => '跟随系统',
                            'light' => '浅色',
                            'dark' => '深色',
                        ],
                    ],
                    [
                        'type' => 'ColorPicker',
                        'name' => 'Color_Accent',
                        'value' => '#0f766e',
                        'label' => '浅色模式主色',
                        'description' => '按钮、链接与强调元素；留空使用主题默认色。',
                        'predefine' => ['#0f766e', '#1d4ed8', '#7c3aed', '#be123c', '#b45309', '#15803d'],
                    ],
                    [
                        'type' => 'ColorPicker',
                        'name' => 'Color_Accent_Dark',
                        'value' => '#5eead4',
                        'label' => '深色模式主色',
                        'description' => '深色模式下的按钮、链接与强调元素；留空使用主题默认色。',
                        'predefine' => ['#5eead4', '#60a5fa', '#c4b5fd', '#fb7185', '#fbbf24', '#86efac'],
                    ],
                    [
                        'type' => 'ColorPicker',
                        'name' => 'Color_Background',
                        'value' => '#fafaf8',
                        'label' => '浅色模式背景',
                        'description' => '页面底色；留空使用主题默认色。',
                    ],
                    [
                        'type' => 'ColorPicker',
                        'name' => 'Color_Background_Dark',
                        'value' => '#0f1115',
                        'label' => '深色模式背景',
                        'description' => '深色模式下的页面底色；留空使用主题默认色。',
                    ],
                ],
            ],
        ],
    ],
    'Layout' => [
        'title' => '布局与区块',
        'tabs' => [
            'templates' => [
                'title' => '模板映射',
                'description' => '为 Typecho 各页面类型指定 app/components 组件。',
                'fields' => [
                    [
                        'type' => 'TemplateMap',
                        'name' => 'Templates',
                        'value' => [
                            'post' => 'content/Post',
                            'page' => 'content/Page',
                            'index' => 'home/WelCome',
                            'archive' => 'content/Archive',
                            'category' => 'content/Archive',
                            'tag' => 'content/Archive',
                            'search' => 'content/Archive',
                            'author' => 'content/Archive',
                            'date' => 'content/Archive',
                            '404' => 'content/NotFound',
                        ],
                        'components' => [
                            'content/Post',
                            'content/Page',
                            'content/Archive',
                            'home/WelCome',
                            'content/NotFound',
                        ],
                        'label' => '模板映射',
                        'description' => '为 Typecho 模板入口选择 app/components 下可独立渲染的页面组件；支持 content/Post 这类子目录名称，仅在 components 白名单内的组件会显示并生效。留空时使用入口模板回退内容，404 槽位应选择 NotFound。',
                    ],
                ],
            ],
        ],
    ],
    'Runtime' => [
        'title' => '运行配置',
        'tabs' => [
            'general' => [
                'title' => '一般设置',
                'description' => '调试与静态资源。',
                'fields' => [
                    [
                        'type' => 'Switch',
                        'name' => 'Debug_Switch',
                        'value' => false,
                        'label' => '调试模式',
                        'description' => '开启后记录详细错误堆栈并显示错误详情；生产环境请关闭。',
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
            'rest' => [
                'title' => 'REST API',
                'description' => '公开接口、跨域、Token 与 v2 写接口。',
                'fields' => [
                    [
                        'type' => 'Switch',
                        'name' => 'RESTAPI_Switch',
                        'value' => false,
                        'label' => '只读 REST API（v1）',
                        'description' => '控制 /ttdf/v1；关闭后仅保留需要管理员登录与 nonce 的 v1 后台接口，不影响独立的 v2 开关。',
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
                        'type' => 'Switch',
                        'name' => 'RESTV2_Switch',
                        'value' => false,
                        'label' => '完整 REST API（v2）',
                        'description' => '开启后 /ttdf/v2 提供登录、评论与内容管理接口，可用 API 完整驱动站点；默认关闭，关闭时该前缀直接返回 404。',
                    ],
                    [
                        'type' => 'Number',
                        'name' => 'RESTV2_Token_TTL',
                        'value' => 604800,
                        'label' => 'v2 Token 有效期（秒）',
                        'description' => '登录接口签发的 Token 有效时长，最少 300 秒、最多 2592000 秒（30 天）；留 0 使用默认 7 天。',
                        'min' => 0,
                        'max' => 2592000,
                        'step' => 3600,
                    ],
                ],
            ],
            'cache' => \TTDF\Theme\CacheSettings::section(),
        ],
    ],
];
