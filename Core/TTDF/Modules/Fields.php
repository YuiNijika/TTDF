<?php
/**
 * Fields Functions
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

function themeFields($layout)
{
    $fieldElements = require_once __DIR__ . '/../../Fields.php';
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

        // 设置字段属性
        if (isset($field['attributes'])) {
            foreach ($field['attributes'] as $attr => $value) {
                $element->input->setAttribute($attr, $value);
            }
        }

        $layout->addItem($element);
    }
}