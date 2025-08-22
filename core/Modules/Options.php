<?php

/**
 * Options Functions
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 辅助创建表单元素
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

/**
 * 创建自定义HTML表单元素
 */
function TTDF_CreateHtmlElement($type, $name, $value, $label, $description, $options = [])
{
    // 获取当前主题名并构建带前缀的字段名
    $themeName = Helper::options()->theme;
    $prefixedName = $themeName . '_' . $name;

    // 检查数据库中是否存在该记录（使用原始字段名，让DB::getTtdf统一处理前缀）
    $row = DB::getTtdf($name);
    $dbValue = $row;

    // 如果数据库中有值且不等于Setup.php中的默认值
    // 否则使用Setup.php中的当前默认值
    $savedValue = $value; // 默认使用Setup.php中的值

    if ($dbValue !== null) {
        // 对于复选框，需要特殊处理比较
        if ($type === 'Checkbox') {
            $setupDefault = is_array($value) ? implode(',', $value) : $value;
            $dbValueForCompare = $dbValue;
        } else {
            $setupDefault = $value;
            $dbValueForCompare = $dbValue;
        }

        // 如果数据库值与Setup.php默认值不同，说明用户修改过，使用数据库值
        if ($dbValueForCompare !== $setupDefault) {
            $savedValue = $dbValue;
        }
    }

    // 生成HTML元素
    $html = '';
    $escapedLabel = htmlspecialchars($label ?? '', ENT_QUOTES, 'UTF-8');
    // 不再对 description 进行转义，允许其中的 HTML 标签正常渲染
    $rawDescription = $description ?? '';

    switch ($type) {
        case 'Text':
        case 'Password':
            $inputType = strtolower($type);
            $escapedValue = htmlspecialchars($savedValue ?? '', ENT_QUOTES, 'UTF-8');
            $html = '<div class="form-group">';
            $html .= '<label for="' . $prefixedName . '">' . $escapedLabel . '</label>';
            // 直接输出 description，不进行转义以支持 HTML
            if ($rawDescription) {
                $html .= '<p class="description">' . $rawDescription . '</p>';
            }
            $html .= '<input type="' . $inputType . '" id="' . $prefixedName . '" name="' . $name . '" value="' . $escapedValue . '" class="form-control" />';
            $html .= '</div>';
            break;

        case 'Textarea':
            $escapedValue = htmlspecialchars($savedValue ?? '', ENT_QUOTES, 'UTF-8');
            $html = '<div class="form-group">';
            $html .= '<label for="' . $prefixedName . '">' . $escapedLabel . '</label>';
            // 直接输出 description，不进行转义以支持 HTML
            if ($rawDescription) {
                $html .= '<p class="description">' . $rawDescription . '</p>';
            }
            $html .= '<textarea id="' . $prefixedName . '" name="' . $name . '" class="form-control" rows="5">' . $escapedValue . '</textarea>';
            $html .= '</div>';
            break;

        case 'Select':
            $html = '<div class="form-group">';
            if ($escapedLabel) {
                $html .= '<label for="' . $prefixedName . '">' . $escapedLabel . '</label>';
            }
            // 直接输出 description，不进行转义以支持 HTML
            if ($rawDescription) {
                $html .= '<p class="description">' . $rawDescription . '</p>';
            }
            $html .= '<select id="' . $prefixedName . '" name="' . $name . '" class="form-control">';
            foreach ($options as $optionValue => $optionLabel) {
                $selected = ($savedValue == $optionValue) ? ' selected' : '';
                $html .= '<option value="' . htmlspecialchars($optionValue, ENT_QUOTES, 'UTF-8') . '"' . $selected . '>' . htmlspecialchars($optionLabel, ENT_QUOTES, 'UTF-8') . '</option>';
            }
            $html .= '</select>';
            $html .= '</div>';
            break;

        case 'Radio':
            $html = '<div class="form-group">';
            if ($escapedLabel) {
                $html .= '<label class="form-label">' . $escapedLabel . '</label>';
            }
            // 直接输出 description，不进行转义以支持 HTML
            if ($rawDescription) {
                $html .= '<p class="description">' . $rawDescription . '</p>';
            }
            $html .= '<div class="radio-group">';
            foreach ($options as $optionValue => $optionLabel) {
                $checked = ($savedValue == $optionValue) ? ' checked' : '';
                $html .= '<label class="radio-item">';
                $html .= '<input type="radio" id="' . $prefixedName . '_' . $optionValue . '" name="' . $name . '" value="' . htmlspecialchars($optionValue, ENT_QUOTES, 'UTF-8') . '"' . $checked . ' />';
                $html .= '<span>' . htmlspecialchars($optionLabel, ENT_QUOTES, 'UTF-8') . '</span>';
                $html .= '</label>';
            }
            $html .= '</div>';
            $html .= '</div>';
            break;

        case 'Checkbox':
            // 处理复选框的多值情况
            $selectedValues = [];
            if (is_string($savedValue)) {
                $selectedValues = explode(',', $savedValue);
            } elseif (is_array($savedValue)) {
                $selectedValues = $savedValue;
            }

            $html = '<div class="form-group">';
            if ($escapedLabel) {
                $html .= '<label class="form-label">' . $escapedLabel . '</label>';
            }
            // 直接输出 description，不进行转义以支持 HTML
            if ($rawDescription) {
                $html .= '<p class="description">' . $rawDescription . '</p>';
            }
            $html .= '<div class="checkbox-group">';
            foreach ($options as $optionValue => $optionLabel) {
                $checked = in_array($optionValue, $selectedValues) ? ' checked' : '';
                $html .= '<label class="checkbox-item">';
                $html .= '<input type="checkbox" id="' . $prefixedName . '_' . $optionValue . '" name="' . $name . '[]" value="' . htmlspecialchars($optionValue, ENT_QUOTES, 'UTF-8') . '"' . $checked . ' />';
                $html .= '<span>' . htmlspecialchars($optionLabel, ENT_QUOTES, 'UTF-8') . '</span>';
                $html .= '</label>';
            }
            $html .= '</div>';
            $html .= '</div>';
            break;

        case 'AddList':
            // 处理逗号分隔的值
            $listValues = [];
            if (is_string($savedValue) && !empty($savedValue)) {
                $listValues = array_filter(explode(',', $savedValue), function ($item) {
                    return trim($item) !== '';
                });
            }

            $html = '<div class="form-group">';
            if ($escapedLabel) {
                $html .= '<label class="form-label">' . $escapedLabel . '</label>';
            }
            if ($rawDescription) {
                $html .= '<p class="description">' . $rawDescription . '</p>';
            }
            $html .= '<div class="addlist-container" data-name="' . $name . '">';
            $html .= '<div class="addlist-items">';

            // 如果有保存的值，显示为输入框
            foreach ($listValues as $index => $listValue) {
                $escapedValue = htmlspecialchars(trim($listValue), ENT_QUOTES, 'UTF-8');
                $html .= '<div class="addlist-item">';
                $html .= '<input type="text" class="form-control addlist-input" value="' . $escapedValue . '" placeholder="请输入内容" />';
                $html .= '<button type="button" class="btn btn-danger addlist-remove">删除</button>';
                $html .= '</div>';
            }

            $html .= '</div>';
            $html .= '<button type="button" class="btn btn-primary addlist-add">+1</button>';
            $html .= '<input type="hidden" name="' . $name . '" class="addlist-hidden" value="' . htmlspecialchars($savedValue ?? '', ENT_QUOTES, 'UTF-8') . '" />';
            $html .= '</div>';
            $html .= '</div>';
            break;

        case 'Html':
            $html = $savedValue ?? '';
            break;

        default:
            $html = '<div class="form-group"><p>不支持的字段类型: ' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '</p></div>';
            break;
    }

    return $html;
}

if (TTDF_CONFIG['FIELDS_ENABLED']) {
    /**
     * 添加字段
     */
    function themeFields($layout)
    {
        $fieldElements = require __DIR__ . '/../../app/Fields.php';
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

// 辅助类用于输出HTML
class EchoHtml extends Typecho_Widget_Helper_Layout
{
    public function __construct($html)
    {
        $this->html($html);
        $this->start();
        $this->end();
    }
    public function start() {}
    public function end() {}
}

// 创建自定义HTML表单元素生成函数
function TTDF_CreateFormElement($field)
{
    // 获取当前主题名
    $themeName = Helper::options()->theme;

    // 构建带主题前缀的字段名（仅用于id属性）
    $prefixedName = $themeName . '_' . $field['name'];

    // 从数据库获取值
    $dbValue = DB::getTtdf($field['name']);

    // 确定最终值：优先使用数据库值，否则使用默认值
    $value = ($dbValue !== null && $dbValue !== $field['value']) ? $dbValue : ($field['value'] ?? '');

    // 处理标签和描述
    $label = $field['label'] ?? '';
    // 修复：不再对 description 进行转义，允许其中的 HTML 标签正常渲染
    $description = $field['description'] ?? '';

    $html = '';

    switch ($field['type']) {
        case 'Text':
        case 'Password':
            $type = strtolower($field['type']);
            $html = '<div class="form-group">';
            if ($label) {
                $html .= '<label for="' . $prefixedName . '">' . $label . '</label>';
            }
            $html .= '<input type="' . $type . '" name="' . $field['name'] . '" id="' . $prefixedName . '" value="' . htmlspecialchars($value) . '" class="form-control" />';
            // 修复：直接输出 description，不进行转义以支持 HTML
            if ($description) {
                $html .= '<p class="description">' . $description . '</p>';
            }
            $html .= '</div>';
            break;

        case 'Textarea':
            $html = '<div class="form-group">';
            if ($label) {
                $html .= '<label for="' . $prefixedName . '">' . $label . '</label>';
            }
            $html .= '<textarea name="' . $field['name'] . '" id="' . $prefixedName . '" class="form-control" rows="5">' . htmlspecialchars($value) . '</textarea>';
            // 修复：直接输出 description，不进行转义以支持 HTML
            if ($description) {
                $html .= '<p class="description">' . $description . '</p>';
            }
            $html .= '</div>';
            break;

        case 'Select':
            $html = '<div class="form-group">';
            if ($label) {
                $html .= '<label for="' . $prefixedName . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</label>';
            }
            // 修复：直接输出 description，不进行转义以支持 HTML
            if ($description) {
                $html .= '<p class="description">' . $description . '</p>';
            }
            $html .= '<select name="' . $field['name'] . '" id="' . $prefixedName . '" class="form-control">';
            if (isset($field['options'])) {
                foreach ($field['options'] as $optValue => $optLabel) {
                    $selected = ($value == $optValue) ? ' selected' : '';
                    $html .= '<option value="' . htmlspecialchars($optValue, ENT_QUOTES, 'UTF-8') . '"' . $selected . '>' . htmlspecialchars($optLabel, ENT_QUOTES, 'UTF-8') . '</option>';
                }
            }
            $html .= '</select>';
            $html .= '</div>';
            break;

        case 'Radio':
            $html = '<div class="form-group">';
            if ($label) {
                $html .= '<label>' . $label . '</label>';
            }
            if (isset($field['options'])) {
                foreach ($field['options'] as $optValue => $optLabel) {
                    $checked = ($value == $optValue) ? ' checked' : '';
                    $html .= '<label class="radio-label"><input type="radio" name="' . $field['name'] . '" value="' . htmlspecialchars($optValue) . '"' . $checked . ' /> ' . $optLabel . '</label>';
                }
            }
            // 修复：直接输出 description，不进行转义以支持 HTML
            if ($description) {
                $html .= '<p class="description">' . $description . '</p>';
            }
            $html .= '</div>';
            break;

        case 'Checkbox':
            $html = '<div class="form-group">';
            if ($label) {
                $html .= '<label>' . $label . '</label>';
            }
            if (isset($field['options'])) {
                $selectedValues = is_string($value) ? explode(',', $value) : (array)$value;
                foreach ($field['options'] as $optValue => $optLabel) {
                    $checked = in_array($optValue, $selectedValues) ? ' checked' : '';
                    $html .= '<label class="checkbox-label"><input type="checkbox" name="' . $field['name'] . '[]" value="' . htmlspecialchars($optValue) . '"' . $checked . ' /> ' . $optLabel . '</label>';
                }
            }
            // 直接输出 description，不进行转义以支持 HTML
            if ($description) {
                $html .= '<p class="description">' . $description . '</p>';
            }
            $html .= '</div>';
            break;

        case 'AddList':
            // 处理逗号分隔的值
            $listValues = [];
            if (is_string($value) && !empty($value)) {
                $listValues = array_filter(explode(',', $value), function ($item) {
                    return trim($item) !== '';
                });
            }

            $html = '<div class="form-group">';
            if ($label) {
                $html .= '<label class="form-label">' . $label . '</label>';
            }
            if ($description) {
                $html .= '<p class="description">' . $description . '</p>';
            }
            $html .= '<div class="addlist-container" data-name="' . $field['name'] . '">';
            $html .= '<div class="addlist-items">';

            // 如果有保存的值，显示为输入框
            foreach ($listValues as $index => $listValue) {
                $escapedValue = htmlspecialchars(trim($listValue), ENT_QUOTES, 'UTF-8');
                $html .= '<div class="addlist-item">';
                $html .= '<input type="text" class="form-control addlist-input" value="' . $escapedValue . '" placeholder="请输入内容" />';
                $html .= '<button type="button" class="btn btn-danger addlist-remove">删除</button>';
                $html .= '</div>';
            }

            $html .= '</div>';
            $html .= '<button type="button" class="btn btn-primary addlist-add">+1</button>';
            $html .= '<input type="hidden" name="' . $field['name'] . '" class="addlist-hidden" value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '" />';
            $html .= '</div>';
            $html .= '</div>';
            break;
    }

    return $html;
}

function themeConfig($form)
{
    // 处理表单提交
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ttdf_ajax_save'])) {
        // 禁用所有可能的重定向和额外输出
        ob_clean();
        header('Content-Type: application/json');

        try {
            // 获取当前主题名
            $themeName = Helper::options()->theme;

            // 获取所有设置项
            $tabs = require __DIR__ . '/../../app/Setup.php';

            // 遍历所有设置项并保存
            foreach ($tabs as $tab) {
                if (isset($tab['fields'])) {
                    foreach ($tab['fields'] as $field) {
                        if (isset($field['name']) && $field['type'] !== 'Html') {
                            // 直接从$_POST中获取原始字段名的值
                            $value = $_POST[$field['name']] ?? null;

                            // 处理复选框的多值情况
                            if (is_array($value)) {
                                $value = implode(',', $value);
                            }

                            // 保存到数据库（DB::setTtdf内部会自动添加主题前缀）
                            DB::setTtdf($field['name'], $value);
                        }
                    }
                }
            }

            echo json_encode(['success' => true, 'message' => '设置已保存!']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => '保存失败: ' . $e->getMessage()]);
        }

        // 确保脚本终止，不执行后续代码
        exit;
    }

    // 如果不是AJAX请求，输出HTML界面
?>
    <style type="text/css">
        /* Typecho CSS 重置部分 */
        .typecho-foot {
            display: none;
        }

        .typecho-head-nav .operate a {
            background-color: #202328;
        }

        .typecho-option-tabs li {
            float: left;
            background-color: #fffbcc;
        }

        .typecho-page-main .typecho-option textarea {
            height: 150px;
        }

        .typecho-option-submit li {
            display: none;
        }

        .row [class*="col-"] {
            float: unset;
            min-height: unset;
            padding-right: unset;
            padding-left: unset;
        }

        @media (min-width: 768px) {
            .col-tb-offset-2 {
                margin-left: unset;
            }

            .col-tb-8 {
                flex: unset;
                max-width: unset;
            }
        }

        .col-mb-12 {
            width: unset;
        }

        /* CSF 风格的现代化样式 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, sans-serif;
        }

        .TTDF-container {
            max-width: 1200px;
            margin: 20px auto;
            background: #ffffff;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            border: 1px solid #ddd;
        }

        .TTDF-header {
            background: #4f46e5;
            color: white;
            padding: 20px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .TTDF-title {
            font-size: 24px;
            font-weight: 600;
            margin: 0;
        }

        .TTDF-title small {
            font-size: 16px;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 400;
            margin-left: 8px;
        }

        .TTDF-actions {
            display: flex;
            gap: 12px;
        }

        .TTDF-save {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.2s;
            font-weight: 500;
            font-size: 14px;
        }

        .TTDF-save:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .TTDF-body {
            display: flex;
            min-height: 520px;
            background: #fafbfc;
        }

        .TTDF-nav {
            width: 240px;
            background: #f8f9fa;
            border-right: 1px solid #ddd;
            overflow-y: auto;
            max-height: 520px;
        }

        .TTDF-nav-item {
            display: block;
            padding: 12px 20px;
            color: #555;
            text-decoration: none;
            transition: all 0.2s;
            border-left: 3px solid transparent;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            width: 100%;
            text-align: left;
            background: transparent;
        }

        .TTDF-nav-item:hover {
            color: #4f46e5;
            background: #f0f0f0;
            border-left-color: #4f46e5;
        }

        .TTDF-nav-item.active {
            background: #e8f0fe;
            color: #1a73e8;
            font-weight: 600;
            border-left-color: #4f46e5;
        }

        .TTDF-content {
            flex: 1;
            padding: 14px;
            overflow-y: auto;
            max-height: 520px;
        }

        .TTDF-content-card {
            border-radius: 4px;
            padding: 14px;
            border: 1px solid #ddd;
        }

        .TTDF-tab-panel {
            display: none;
            animation: fadeIn 0.3s ease-in-out;
        }

        .TTDF-tab-panel.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* 现代化字体系统 */
        * {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }

        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            font-weight: 700;
            letter-spacing: -0.5px;
            line-height: 1.2;
        }

        /* 响应式设计 */
        @media (max-width: 768px) {
            .TTDF-container {
                margin: 10px;
            }

            .TTDF-body {
                flex-direction: column;
            }

            .TTDF-nav {
                width: 100%;
                display: flex;
                overflow-x: auto;
                padding: 0;
                background: #f8f9fa;
                border-right: none;
                border-bottom: 1px solid #ddd;
            }

            .TTDF-nav-item {
                white-space: nowrap;
                padding: 10px 16px;
                min-width: 100px;
                text-align: center;
                font-size: 13px;
                border-left: none;
                border-bottom: 3px solid transparent;
            }

            .TTDF-nav-item:hover,
            .TTDF-nav-item.active {
                border-bottom-color: #4f46e5;
            }

            .TTDF-header {
                padding: 16px;
            }

            .TTDF-title {
                font-size: 20px;
            }

            .TTDF-content {
                padding: 16px;
            }

            .form-control {
                font-size: 16px;
                /* 防止iOS缩放 */
            }

            .radio-group,
            .checkbox-group {
                gap: 8px;
            }

            .radio-item,
            .checkbox-item {
                min-width: 80px;
                font-size: 13px;
            }
        }

        /** 一些组件 */
        /** Alert */
        .alert {
            position: relative;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            line-height: 1.5;
            margin: 0.5rem 0;
            border-width: 1px;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }

        .alert::before {
            content: "";
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            width: 1rem;
            height: 1rem;
            background-size: contain;
            background-repeat: no-repeat;
        }

        .alert.info {
            background-color: #ebf5ff;
            border-color: #d1e7ff;
            color: #1c64f2;
        }

        .alert.info::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%231c64f2'%3E%3Cpath fill-rule='evenodd' d='M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z' clip-rule='evenodd'/%3E%3C/svg%3E");
        }

        .alert.success {
            background-color: #f0fdf4;
            border-color: #dcfce7;
            color: #16a34a;
        }

        .alert.success::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%2316a34a'%3E%3Cpath fill-rule='evenodd' d='M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z' clip-rule='evenodd'/%3E%3C/svg%3E");
        }

        .alert.warning {
            background-color: #fefce8;
            border-color: #fef08a;
            color: #d97706;
        }

        .alert.warning::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%23d97706'%3E%3Cpath fill-rule='evenodd' d='M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z' clip-rule='evenodd'/%3E%3C/svg%3E");
        }

        .alert.error {
            background-color: #fef2f2;
            border-color: #fee2e2;
            color: #dc2626;
        }

        .alert.error::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%23dc2626'%3E%3Cpath fill-rule='evenodd' d='M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z' clip-rule='evenodd'/%3E%3C/svg%3E");
        }

        /* 消息提示样式 */
        .ttdf-message {
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* 加载遮罩 */
        .ttdf-loading {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 9999;
            display: none;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(8px);
        }

        .ttdf-loading-spinner {
            width: 48px;
            height: 48px;
            border: 3px solid rgba(255, 255, 255, 0.2);
            border-top: 3px solid #4f46e5;
            border-radius: 50%;
            animation: modernSpin 1s cubic-bezier(0.4, 0, 0.2, 1) infinite;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }

        @keyframes modernSpin {
            0% {
                transform: rotate(0deg) scale(1);
            }

            50% {
                transform: rotate(180deg) scale(1.1);
            }

            100% {
                transform: rotate(360deg) scale(1);
            }
        }

        /* 表单样式 */
        .form-group {
            margin-bottom: 10px;
        }

        .form-group label {
            display: block;
            margin-bottom: 12px;
            font-weight: 600;
            color: #1f2937;
            font-size: 15px;
            letter-spacing: -0.2px;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            line-height: 1.5;
            color: #333;
            background-color: #ffffff;
            transition: border-color 0.2s;
        }

        /* AddList 组件样式 */
        .addlist-container {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 16px;
            background-color: #f9fafb;
        }

        .addlist-items {
            margin-bottom: 12px;
        }

        .addlist-item {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            padding: 8px;
            background-color: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
        }

        .addlist-input {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.2s;
        }

        .addlist-input:focus {
            outline: none;
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .addlist-remove {
            padding: 6px 12px;
            background-color: #ef4444;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            transition: background-color 0.2s;
            white-space: nowrap;
        }

        .addlist-remove:hover {
            background-color: #dc2626;
        }

        .addlist-add {
            padding: 8px 16px;
            background-color: #4f46e5;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .addlist-add:hover {
            background-color: #4338ca;
        }

        .addlist-hidden {
            display: none;
            box-sizing: border-box;
        }

        .form-control:focus {
            border-color: #4f46e5;
            outline: 0;
        }

        .form-control[readonly] {
            background-color: #f9fafb;
            border-color: #d1d5db;
            color: #6b7280;
            cursor: not-allowed;
        }

        select.form-control {
            cursor: pointer;
            background-image: url('data:image/svg+xml;charset=US-ASCII,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 4 5"><path fill="%23666" d="M2 0L0 2h4zm0 5L0 3h4z"/></svg>');
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 12px;
            padding-right: 40px;
            height: 45px;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 120px;
            font-family: inherit;
        }

        .description {
            margin-top: 8px;
            font-size: 13px;
            color: #6b7280;
            line-height: 1.5;
            font-style: italic;
        }

        /* 简化的单选框和复选框样式 */
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
            font-size: 14px;
        }

        .radio-group,
        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 8px;
        }

        .radio-item,
        .checkbox-item {
            display: flex;
            align-items: center;
            cursor: pointer;
            font-size: 14px;
            color: #374151;
            padding: 8px 12px;
            border-radius: 4px;
            transition: all 0.2s;
            background: #f8f9fa;
            border: 1px solid #ddd;
            white-space: nowrap;
            min-width: 100px;
        }

        .radio-item:hover,
        .checkbox-item:hover {
            background: #f0f4ff;
            border-color: #4f46e5;
        }

        .radio-item input[type="radio"],
        .checkbox-item input[type="checkbox"] {
            margin-right: 6px;
            margin-top: 0;
            width: 16px;
            height: 16px;
            accent-color: #4f46e5;
            cursor: pointer;
        }

        .radio-item input[type="radio"]:checked~span,
        .checkbox-item input[type="checkbox"]:checked~span {
            color: #4f46e5;
            font-weight: 500;
        }

        .radio-item input[type="radio"]:checked,
        .checkbox-item input[type="checkbox"]:checked {
            background: #4f46e5;
        }

        /* 兼容旧样式 - 修改这部分以实现横排显示 */
        .radio-label,
        .checkbox-label {
            display: inline-flex;
            align-items: center;
            margin-bottom: 0;
            margin-right: 16px;
            cursor: pointer;
            font-size: 14px;
            color: #374151;
            padding: 8px 12px;
            border-radius: 4px;
            transition: all 0.2s;
            background: #f8f9fa;
            border: 1px solid #ddd;
            white-space: nowrap;
        }

        .radio-label:hover,
        .checkbox-label:hover {
            background: #f0f4ff;
            border-color: #4f46e5;
        }

        .radio-label input[type="radio"],
        .checkbox-label input[type="checkbox"] {
            margin-right: 6px;
            margin-top: 0;
            width: 16px;
            height: 16px;
            accent-color: #4f46e5;
            cursor: pointer;
        }

        .radio-label input[type="radio"]:checked+span,
        .checkbox-label input[type="checkbox"]:checked+span {
            color: #4f46e5;
            font-weight: 500;
        }

        /* 响应式下的单选框和复选框样式 */
        @media (max-width: 768px) {

            .radio-label,
            .checkbox-label {
                display: inline-flex;
                align-items: center;
                margin-bottom: 0;
                margin-right: 8px;
                cursor: pointer;
                font-size: 13px;
                color: #374151;
                padding: 6px 10px;
                border-radius: 4px;
                transition: all 0.2s;
                background: #f8f9fa;
                border: 1px solid #ddd;
                white-space: nowrap;
            }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 获取所有Tab导航项
            const tabItems = document.querySelectorAll('.TTDF-nav-item');

            // 为每个Tab项添加点击事件
            tabItems.forEach(item => {
                item.addEventListener('click', function() {
                    const tabId = this.getAttribute('data-tab');

                    // 移除所有Tab项的活动状态
                    tabItems.forEach(tab => {
                        tab.classList.remove('active');
                    });

                    // 为当前点击的Tab项添加活动状态
                    this.classList.add('active');

                    // 隐藏所有内容面板
                    document.querySelectorAll('.TTDF-tab-panel').forEach(panel => {
                        panel.classList.remove('active');
                    });

                    // 显示当前Tab对应的内容面板
                    document.getElementById(tabId).classList.add('active');
                });
            });

            // 无刷新保存设置
            const saveButton = document.querySelector('.TTDF-save');
            if (saveButton) {
                saveButton.addEventListener('click', function(e) {
                    e.preventDefault();

                    // 显示加载遮罩
                    const loading = document.createElement('div');
                    loading.className = 'ttdf-loading';
                    loading.innerHTML = '<div class="ttdf-loading-spinner"></div>';
                    document.body.appendChild(loading);
                    loading.style.display = 'flex';

                    // 收集表单数据
                    const form = document.querySelector('form');
                    const formData = new FormData(form);

                    // 转换为普通对象
                    const data = {};
                    for (let [key, value] of formData.entries()) {
                        // 处理复选框的多值情况
                        if (data[key]) {
                            if (Array.isArray(data[key])) {
                                data[key].push(value);
                            } else {
                                data[key] = [data[key], value];
                            }
                        } else {
                            data[key] = value;
                        }
                    }

                    // 发送AJAX请求到新的API端点
                    fetch('<?php echo Typecho_Common::url(__TTDF_RESTAPI_ROUTE__ . '/ttdf/options', Helper::options()->siteUrl); ?>', {
                            method: 'POST',
                            body: JSON.stringify(data),
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            // 确保不跟随重定向
                            redirect: 'error'
                        })
                        .then(response => {
                            // 检查响应是否为JSON
                            const contentType = response.headers.get('content-type');
                            if (contentType && contentType.indexOf('application/json') !== -1) {
                                return response.json();
                            } else {
                                throw new Error('服务器返回了非JSON响应');
                            }
                        })
                        .then(data => {
                            // 隐藏加载遮罩
                            document.body.removeChild(loading);

                            // 显示消息到TTDF-content区域内
                            let messageDiv = document.querySelector('.ttdf-message');
                            if (!messageDiv) {
                                messageDiv = document.createElement('div');
                                messageDiv.className = 'ttdf-message';
                            }

                            // 设置消息样式和内容
                            messageDiv.className = 'alert success ttdf-message';
                            messageDiv.innerHTML = '<span>设置已保存!</span>';

                            // 将消息插入到TTDF-content的顶部
                            const contentArea = document.querySelector('.TTDF-content');
                            contentArea.insertBefore(messageDiv, contentArea.firstChild);

                            // 3秒后自动隐藏消息
                            setTimeout(() => {
                                if (messageDiv.parentNode) {
                                    messageDiv.parentNode.removeChild(messageDiv);
                                }
                            }, 3000);
                        })
                        .catch(error => {
                            // 隐藏加载遮罩
                            document.body.removeChild(loading);

                            // 显示错误消息到TTDF-content区域内
                            let messageDiv = document.querySelector('.ttdf-message');
                            if (!messageDiv) {
                                messageDiv = document.createElement('div');
                                messageDiv.className = 'ttdf-message';
                            }

                            // 设置错误消息样式和内容
                            messageDiv.className = 'alert error ttdf-message';
                            messageDiv.innerHTML = '<span>保存失败: ' + error.message + '</span>';

                            // 将消息插入到TTDF-content的顶部
                            const contentArea = document.querySelector('.TTDF-content');
                            contentArea.insertBefore(messageDiv, contentArea.firstChild);

                            // 3秒后自动隐藏消息
                            setTimeout(() => {
                                if (messageDiv.parentNode) {
                                    messageDiv.parentNode.removeChild(messageDiv);
                                }
                            }, 3000);
                        });
                });
            }

            // AddList 功能
            function initAddListFunctionality() {
                // 为所有 AddList 容器添加事件监听
                document.querySelectorAll('.addlist-container').forEach(container => {
                    const addButton = container.querySelector('.addlist-add');
                    const itemsContainer = container.querySelector('.addlist-items');
                    const hiddenInput = container.querySelector('.addlist-hidden');

                    // 添加新项目
                    if (addButton) {
                        addButton.addEventListener('click', function(e) {
                            e.preventDefault();
                            addNewItem(itemsContainer, hiddenInput);
                        });
                    }

                    // 为现有的删除按钮添加事件监听
                    container.addEventListener('click', function(e) {
                        if (e.target.classList.contains('addlist-remove')) {
                            e.preventDefault();
                            removeItem(e.target.closest('.addlist-item'), hiddenInput);
                        }
                    });

                    // 为输入框添加变化监听
                    container.addEventListener('input', function(e) {
                        if (e.target.classList.contains('addlist-input')) {
                            updateHiddenValue(container, hiddenInput);
                        }
                    });
                });
            }

            function addNewItem(itemsContainer, hiddenInput) {
                const newItem = document.createElement('div');
                newItem.className = 'addlist-item';
                newItem.innerHTML = `
            <input type="text" class="form-control addlist-input" placeholder="请输入内容" />
            <button type="button" class="btn btn-danger addlist-remove">删除</button>
        `;
                itemsContainer.appendChild(newItem);

                // 聚焦到新添加的输入框
                newItem.querySelector('.addlist-input').focus();

                updateHiddenValue(itemsContainer.closest('.addlist-container'), hiddenInput);
            }

            function removeItem(item, hiddenInput) {
                const container = item.closest('.addlist-container');
                item.remove();
                updateHiddenValue(container, hiddenInput);
            }

            function updateHiddenValue(container, hiddenInput) {
                const inputs = container.querySelectorAll('.addlist-input');
                const values = [];
                inputs.forEach(input => {
                    const value = input.value.trim();
                    if (value) {
                        values.push(value);
                    }
                });
                hiddenInput.value = values.join(',');
            }

            // 初始化 AddList 功能
            initAddListFunctionality();
        });
    </script>

    <form method="post">
        <div class="TTDF-container">
            <div class="TTDF-header">
                <h1 class="TTDF-title"><?php echo GetTheme::Name(false); ?><small> · <?php echo GetTheme::Ver(false); ?></small></h1>
                <div class="TTDF-actions">
                    <button class="TTDF-save" type="submit">保存设置</button>
                </div>
            </div>

            <div class="TTDF-body">
                <nav class="TTDF-nav">
                    <?php
                    // 生成Tab导航按钮（默认激活第一个）
                    $tabs = require __DIR__ . '/../../app/Setup.php';
                    $first_tab = true;
                    foreach ($tabs as $tab_id => $tab) {
                        $active = $first_tab ? 'active' : '';
                        echo '<div class="TTDF-nav-item ' . $active . '" data-tab="' . $tab_id . '">' . $tab['title'] . '</div>';
                        $first_tab = false;
                    }
                    ?>
                </nav>
                <div class="TTDF-content">
                    <div class="TTDF-content-card">
                        <?php
                        // 生成Tab内容
                        $first_tab = true;
                        foreach ($tabs as $tab_id => $tab) {
                            $show = $first_tab ? 'active' : '';
                            echo '<div id="' . $tab_id . '" class="TTDF-tab-panel ' . $show . '">';

                            if (isset($tab['html'])) {
                                foreach ($tab['html'] as $html) {
                                    echo $html['content'];
                                }
                            } else {
                                foreach ($tab['fields'] as $field) {
                                    if ($field['type'] === 'Html') {
                                        echo $field['content'];
                                    } else {
                                        echo TTDF_CreateFormElement($field);
                                    }
                                }
                            }

                            echo '</div>';
                            $first_tab = false;
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
        <div style="text-align: center; margin-top: 20px;">
            © Framework By <a href="https://github.com/YuiNijika/TTDF" target="_blank" style="padding: 0px 3px;">TTDF</a> v<?php echo TTDF::Ver(false); ?>
        </div>
    </form>
<?php
}
