<?php

/**
 * TTDF Function
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 创建表单元素
 */
function TTDF_FormElement($type, $name, $value, $label, $description, $options = [])
{
    // 确保 _t() 的参数不为 null
    $label = $label ?? '';
    $description = $description ?? '';

    $class = '\\Typecho\\Widget\\Helper\\Form\\Element\\' . $type;
    if ($type === 'Radio' || $type === 'Select' || $type === 'Checkbox') {
        // Radio、Select、Checkbox 类型需要额外的 options 参数
        return new $class($name, $options, $value, _t($label), _t($description));
    } else {
        return new $class($name, null, $value, _t($label), _t($description));
    }
}


if (TTDF_CONFIG['FIELDS_ENABLED']) {
    /**
     * 添加字段
     */
    function themeFields($layout)
    {
        $fieldElements = require_once __DIR__ . '/../../app/Fields.php';
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
}
