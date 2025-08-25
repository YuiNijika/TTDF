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
                'content' => '<div class="alert success">感谢使用<a href="https://github.com/YuiNijika/TTDF">TTDF</a>进行开发</div>'
            ],
            [
                // 'Text' => '文本框',
                'type' => 'Text',
                'name' => 'SubTitle',
                'value' => '',
                'label' => '副标题',
                'description' => '这是一个文本框，用于设置网站副标题，如果为空则不显示。'
            ],
            [
                // 'Textarea' => '文本域',
                'type' => 'Textarea',
                'name' => 'this_textarea',
                'value' => '',
                'label' => '文本域',
                'description' => '这是一个文本域~'
            ],
            [
                // 'AddList' => '动态列表',
                'type' => 'AddList',
                'name' => 'this_addlist',
                'value' => '项目1,项目2,项目3',
                'label' => '动态列表测试',
                'description' => '这是一个AddList组件，点击+1按钮可以添加新的输入框，数据以逗号分隔存储。'
            ]
        ]
    ],
    'select-elements' => [
        'title' => '选择设置',
        'fields' => [
            [
                // 'Radio' => '单选框',
                'type' => 'Radio',
                'name' => 'this_radio',
                'value' => 'option1',
                'label' => '单选框',
                'description' => '这是一个单选框~',
                'layout' => 'vertical', // horizontal: 横排, vertical: 竖排
                'options' => [
                    'option1' => '选项一',
                    'option2' => '选项二',
                    'option3' => '选项三'
                ]
            ],
            [
                // 'Select' => '下拉框',
                'type' => 'Select',
                'name' => 'this_select',
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
                'name' => 'this_checkbox',
                'value' => ['option1', 'option3'],
                'label' => '多选框',
                'description' => '这是一个多选框~',
                'layout' => 'horizontal', // horizontal: 横排, vertical: 竖排
                'options' => [
                    'option1' => '选项一',
                    'option2' => '选项二',
                    'option3' => '选项三'
                ]
            ],
            [
                // 'DialogSelect' => '对话框选择',
                'type' => 'DialogSelect',
                'name' => 'dialog_select_single',
                'value' => 'theme1',
                'label' => '主题选择',
                'description' => '点击按钮打开对话框选择主题，支持单选模式。',
                'title' => '选择主题',
                'multiple' => false,
                'options' => [
                    'theme1' => '默认主题',
                    'theme2' => '深色主题',
                    'theme3' => '简约主题',
                    'theme4' => '彩色主题'
                ]
            ],
            [
                // 'DialogSelect' => '对话框选择（多选）',
                'type' => 'DialogSelect',
                'name' => 'dialog_select_multiple',
                'value' => 'feature1,feature3',
                'label' => '功能选择（多选）',
                'description' => '点击按钮打开对话框选择功能，支持多选模式。',
                'title' => '选择功能模块',
                'multiple' => true,
                'options' => [
                    'feature1' => '评论系统',
                    'feature2' => '搜索功能',
                    'feature3' => '社交分享',
                    'feature4' => '统计分析',
                    'feature5' => '邮件通知'
                ]
            ],
            [
                // 'ColorPicker' => '颜色选择器',
                'type' => 'ColorPicker',
                'name' => 'theme_color',
                'value' => '#3498db',
                'label' => '主题颜色',
                'description' => '选择网站的主题颜色，支持十六进制颜色值输入。'
            ],
            [
                // 'ColorPicker' => '颜色选择器（背景色）',
                'type' => 'ColorPicker',
                'name' => 'background_color',
                'value' => '#ffffff',
                'label' => '背景颜色',
                'description' => '选择网站的背景颜色，可以通过颜色选择器或直接输入十六进制值。'
            ]
        ]
    ],
    'TTDF-Options' => [
        'title' => '其他设置',
        'fields' => [
            [
                'type' => 'Html',
                'content' => '<div class="alert warning">如果关闭将无法使用RestAPI</div>'
            ],
            [
                'type' => 'Select',
                'name' => 'RESTAPI_Switch',
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
                'content' => '
                    <div class="alert info">信息提示</div>
                    <div class="alert success">成功提示</div>
                    <div class="alert warning">警告提示</div>
                    <div class="alert error">错误提示</div>
                '
            ],
        ]
    ],
];
