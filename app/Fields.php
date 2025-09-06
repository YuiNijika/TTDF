<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
// 定义字段配置
return [
    [
        // Text
        'type' => 'Text',
        'name' => 'TTDF_Fields_Text',
        'value' => '', // 默认值为空字符串
        'label' => '文本框',
        'description' => '这是一个文本框~',
    ],
    [
        // Textarea
        'type' => 'Textarea',
        'name' => 'TTDF_Fields_Textarea',
        'value' => '', // 默认值为空字符串
        'label' => '文本域',
        'description' => '这是一个文本域~',
        // 设置字段属性
        'attributes' => [
            'style' => 'height: 100px;'
        ]
    ],
    [
        // Radio
        'type' => 'Radio',
        'name' => 'TTDF_Fields_Radio',
        'value' => '', // 默认值为空字符串
        'label' => '单选框',
        'description' => '这是一个单选框~',
        'options' => [
            'option1' => '选项一',
            'option2' => '选项二',
            'option3' => '选项三'
        ]
    ],
    [
        // Select
        'type' => 'Select',
        'name' => 'TTDF_Fields_Select',
        'value' => '', // 默认值为空字符串
        'label' => '下拉框',
        'description' => '这是一个下拉框~',
        'options' => [
            'option1' => '选项一',
            'option2' => '选项二',
            'option3' => '选项三'
        ]
    ],
    [
        // Checkbox
        'type' => 'Checkbox',
        'name' => 'TTDF_Fields_Checkbox',
        'value' => [], // 默认值为空数组
        'label' => '多选框',
        'description' => '这是一个多选框~',
        'options' => [
            'option1' => '选项一',
            'option2' => '选项二',
            'option3' => '选项三'
        ]
    ]
];
