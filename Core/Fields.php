<?php
/**
 * Fields Functions
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

function themeFields($layout)
{
    // 定义字段配置
    $fieldElements = [
        [
            // Text
            'type' => 'Text',
            'name' => 'TTDF_Fields_Text',
            'value' => null, // 默认值为 null
            'label' => '文本框',
            'description' => '这是一个文本框~'
        ],
        [
            // Textarea
            'type' => 'Textarea',
            'name' => 'TTDF_Fields_Textarea',
            'value' => null, // 默认值为 null
            'label' => '文本域',
            'description' => '这是一个文本域~',
            'attributes' => [
                'style' => 'width: 100%;height: 100px;'
            ]
        ],
        [
            // Radio
            'type' => 'Radio',
            'name' => 'TTDF_Fields_Radio',
            'value' => null, // 默认值为 null
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
            'value' => null, // 默认值为 null
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

    // 循环添加字段
    foreach ($fieldElements as $field) {
        $element = TTDF_FormElement(
            $field['type'],
            $field['name'],
            $field['value'] ?? null,
            $field['label'] ?? '',
            $field['description'] ?? '',
            $field['options'] ?? []
        );

        // 设置字段属性（如 style）
        if (isset($field['attributes'])) {
            foreach ($field['attributes'] as $attr => $value) {
                $element->input->setAttribute($attr, $value);
            }
        }

        $layout->addItem($element);
    }
}