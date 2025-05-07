<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
// 定义所有设置项
$config = [
    '基础设置' => [
        'title' => '基础设置',
        'fields' => [
            [
                'type' => 'Text',
                'name' => 'SubTitle',
                'value' => null,
                'label' => '副标题',
                'description' => '这是一个文本框，用于设置网站副标题，如果为空则不显示。'
            ],
            [
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
                'description' => 'TTDF框架内置的 REST API<br/>使用教程可参见 <a href="https://github.com/Typecho-Framework/Typecho-Theme-Development-Framework#rest-api" target="_blank">*这里*</a>',
                'options' => [
                    'true' => '开启',
                    'false' => '关闭'
                ]
            ],
        ]
    ],
];

return $config;