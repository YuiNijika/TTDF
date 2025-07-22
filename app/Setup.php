<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
// 定义所有设置项
return [
    '基础设置' => [
        'title' => '基础设置',
        'fields' => [
            [
                // 'Html' => '自定义HTML标签',
                'type' => 'Html',
                'content' => '<h3>感谢使用 TTDF 进行开发</h3>'
            ],
            [
                // 'Text' => '文本框',
                'type' => 'Text',
                'name' => 'SubTitle',
                'value' => null,
                'label' => '副标题',
                'description' => '这是一个文本框，用于设置网站副标题，如果为空则不显示。'
            ],
            [
                // 'Textarea' => '文本域',
                'type' => 'Textarea',
                'name' => 'TTDF_Textarea',
                'value' => null,
                'label' => '文本域',
                'description' => '这是一个文本域~'
            ]
        ]
    ],
    'select-elements' => [
        'title' => '选择设置',
        'fields' => [
            [
                // 'Radio' => '单选框',
                'type' => 'Radio',
                'name' => 'TTDF_Radio',
                'value' => 'option1',
                'label' => '单选框',
                'description' => '这是一个单选框~',
                'options' => [
                    'option1' => '选项一',
                    'option2' => '选项二',
                    'option3' => '选项三'
                ]
            ],
            [
                // 'Select' => '下拉框',
                'type' => 'Select',
                'name' => 'TTDF_Select',
                'value' => 'option2',
                'label' => '下拉框',
                'description' => '这是一个下拉框~',
                'options' => [
                    'option1' => '选项一',
                    'option2' => '选项二',
                    'option3' => '选项三'
                ]
            ],
            [
                // 'Checkbox' => '多选框',
                'type' => 'Checkbox',
                'name' => 'TTDF_Checkbox',
                'value' => ['option1', 'option3'],
                'label' => '多选框',
                'description' => '这是一个多选框~',
                'options' => [
                    'option1' => '选项一',
                    'option2' => '选项二',
                    'option3' => '选项三'
                ]
            ]
        ]
    ],
    'TTDF-Options' => [
        'title' => '其他设置',
        'fields' => [
            [
                'type' => 'Select',
                'name' => 'TTDF_RESTAPI_Switch',
                'value' => 'false',
                'label' => 'REST API',
                'description' => 'TTDF框架内置的 REST API<br/>使用教程可参见 <a href="https://github.com/YuiNijika/TTDF/blob/master/README_DOC.md#rest-api" target="_blank">*这里*</a>',
                'options' => [
                    'true' => '开启',
                    'false' => '关闭'
                ]
            ],
        ]
    ],
    'HTML-Demo' => [
        'title' => 'HTML示例',
        // 定义HTML TAB栏
        'html' => [
            [
                // 'Content' => '自定义输出HTML内容',
                'content' => '<h2>欢迎使用TTDF框架</h2>
            <p>Typecho Theme Development Framework</p>
            <blockquote style="border-left: 4px solid #ccc; padding-left: 20px; margin: 20px 0;">
                <p>一个 Typecho 主题开发框架，设计之初是写给自己用的。<br>
                <del>还算不上框架只能说让开发变得更简单些</del></p>
            </blockquote>'
            ],
        ]
    ],
];
