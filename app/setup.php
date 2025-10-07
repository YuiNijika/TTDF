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
                'options' => [
                    'option1' => '选项一',
                    'option2' => '选项二',
                    'option3' => '选项三'
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
                // 'DatePicker' => '日期选择器',
                'type' => 'DatePicker',
                'name' => 'demo_date',
                'value' => '',
                'label' => '日期选择',
                'description' => '选择一个日期，格式为 YYYY-MM-DD',
                'format' => 'YYYY-MM-DD',
                'placeholder' => '请选择日期'
            ],
            [
                // 'TimePicker' => '时间选择器',
                'type' => 'TimePicker',
                'name' => 'demo_time',
                'value' => '',
                'label' => '时间选择',
                'description' => '选择一个时间，格式为 HH:mm:ss',
                'format' => 'HH:mm:ss',
                'placeholder' => '请选择时间'
            ]
        ]
    ],
    'advanced-components' => [
        'title' => '高级组件',
        'fields' => [
            [
                'type' => 'Html',
                'content' => '<div class="alert info">高级组件演示，展示更多实用的字段类型</div>'
            ],
            [
                // 'Number' => '数字输入框',
                'type' => 'Number',
                'name' => 'demo_number',
                'value' => 100,
                'label' => '数字输入',
                'description' => '数字输入框，支持设置最小值、最大值和步长',
                'min' => 0,
                'max' => 1000,
                'step' => 10,
                'placeholder' => '请输入数字'
            ],
            [
                // 'Switch' => '开关切换',
                'type' => 'Switch',
                'name' => 'demo_switch',
                'value' => true,
                'label' => '开关切换',
                'description' => '开关组件，用于切换布尔值状态',
                'active_text' => '开启',
                'inactive_text' => '关闭'
            ],
            [
                // 'Slider' => '滑块',
                'type' => 'Slider',
                'name' => 'demo_slider',
                'value' => 50,
                'label' => '滑块控制',
                'description' => '滑块组件，用于在指定范围内选择数值',
                'min' => 0,
                'max' => 100,
                'step' => 5,
                'show_stops' => true
            ],
            [
                // 'Code' => '代码编辑器',
                'type' => 'Code',
                'name' => 'demo_code',
                'value' => '// JavaScript代码示例\nfunction hello() {\n    console.log("Hello World!");\n}',
                'label' => '代码编辑',
                'description' => '代码编辑器，支持语法高亮和代码格式化',
                'language' => 'javascript',
                'theme' => 'vs-dark'
            ],
            [
                // 'Tags' => '标签输入',
                'type' => 'Tags',
                'name' => 'demo_tags',
                'value' => 'Vue,JavaScript,CSS',
                'label' => '标签输入',
                'description' => '标签输入组件，支持动态添加和删除标签',
                'placeholder' => '输入标签后按回车',
                'max_tags' => 10
            ],
            [
                // 'Cascader' => '级联选择器',
                'type' => 'Cascader',
                'name' => 'demo_cascader',
                'value' => 'frontend,vue,vue3',
                'label' => '级联选择',
                'description' => '级联选择器，支持多级分类选择',
                'placeholder' => '请选择分类',
                'options' => [
                    [
                        'value' => 'frontend',
                        'label' => '前端开发',
                        'children' => [
                            [
                                'value' => 'vue',
                                'label' => 'Vue.js',
                                'children' => [
                                    ['value' => 'vue2', 'label' => 'Vue 2.x'],
                                    ['value' => 'vue3', 'label' => 'Vue 3.x']
                                ]
                            ],
                            [
                                'value' => 'react',
                                'label' => 'React',
                                'children' => [
                                    ['value' => 'react16', 'label' => 'React 16'],
                                    ['value' => 'react17', 'label' => 'React 17'],
                                    ['value' => 'react18', 'label' => 'React 18']
                                ]
                            ]
                        ]
                    ],
                    [
                        'value' => 'backend',
                        'label' => '后端开发',
                        'children' => [
                            [
                                'value' => 'nodejs',
                                'label' => 'Node.js',
                                'children' => [
                                    ['value' => 'express', 'label' => 'Express'],
                                    ['value' => 'koa', 'label' => 'Koa']
                                ]
                            ],
                            [
                                'value' => 'php',
                                'label' => 'PHP',
                                'children' => [
                                    ['value' => 'laravel', 'label' => 'Laravel'],
                                    ['value' => 'symfony', 'label' => 'Symfony']
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            [
                // 'Transfer' => '穿梭框',
                'type' => 'Transfer',
                'name' => 'demo_transfer',
                'value' => 'item1,item3',
                'label' => '穿梭框',
                'description' => '穿梭框组件，用于在两个列表之间移动选项',
                'titles' => ['可选项', '已选项'],
                'button_texts' => ['移除', '添加'],
                'data' => [
                    ['key' => 'item1', 'label' => '选项 1', 'disabled' => false],
                    ['key' => 'item2', 'label' => '选项 2', 'disabled' => false],
                    ['key' => 'item3', 'label' => '选项 3', 'disabled' => false],
                    ['key' => 'item4', 'label' => '选项 4', 'disabled' => false],
                    ['key' => 'item5', 'label' => '选项 5', 'disabled' => false]
                ]
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
