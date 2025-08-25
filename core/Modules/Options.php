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
        // 对于复选框和DialogSelect，需要特殊处理比较
        if ($type === 'Checkbox' || $type === 'DialogSelect') {
            // 将Setup.php中的数组默认值转换为逗号分隔字符串进行比较
            $setupDefault = is_array($value) ? implode(',', $value) : $value;
            $dbValueForCompare = $dbValue;

            // 标准化比较：去除空格并排序
            $setupNormalized = $setupDefault;
            $dbNormalized = $dbValueForCompare;

            if (!empty($setupNormalized)) {
                $setupArray = explode(',', $setupNormalized);
                $setupArray = array_map('trim', $setupArray);
                sort($setupArray);
                $setupNormalized = implode(',', $setupArray);
            }

            if (!empty($dbNormalized)) {
                $dbArray = explode(',', $dbNormalized);
                $dbArray = array_map('trim', $dbArray);
                sort($dbArray);
                $dbNormalized = implode(',', $dbArray);
            }

            // 如果标准化后的值不同，说明用户修改过，使用数据库值
            if ($dbNormalized !== $setupNormalized) {
                $savedValue = $dbValue;
            }
        } else {
            $setupDefault = $value;
            $dbValueForCompare = $dbValue;

            // 如果数据库值与Setup.php默认值不同，说明用户修改过，使用数据库值
            if ($dbValueForCompare !== $setupDefault) {
                $savedValue = $dbValue;
            }
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
            // 获取layout配置，默认为vertical
            $layout = isset($options['_layout']) ? $options['_layout'] : 'vertical';
            if (isset($options['_layout'])) {
                unset($options['_layout']); // 移除特殊标记
            }

            $html = '<div class="form-group">';
            if ($escapedLabel) {
                $html .= '<label class="form-label">' . $escapedLabel . '</label>';
            }
            // 直接输出 description，不进行转义以支持 HTML
            if ($rawDescription) {
                $html .= '<p class="description">' . $rawDescription . '</p>';
            }
            $html .= '<div class="radio-group ' . ($layout === 'horizontal' ? 'horizontal-layout' : 'vertical-layout') . '">';
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
            $html = '<div class="form-group">';
            if ($label) {
                $html .= '<label>' . $label . '</label>';
            }
            if (isset($field['options'])) {
                // 获取layout配置，默认为vertical
                $layout = isset($field['layout']) ? $field['layout'] : 'vertical';
                $layoutClass = ($layout === 'horizontal') ? 'horizontal-layout' : 'vertical-layout';

                $html .= '<div class="checkbox-group ' . $layoutClass . '">';
                // 修复：正确处理从数据库获取的值
                $selectedValues = [];
                if (is_string($value)) {
                    // 如果是字符串，则按逗号分割成数组
                    $selectedValues = explode(',', $value);
                } elseif (is_array($value)) {
                    // 如果已经是数组，则直接使用
                    $selectedValues = $value;
                }

                foreach ($field['options'] as $optValue => $optLabel) {
                    // 修复：使用 in_array 检查选项是否被选中
                    $checked = in_array((string)$optValue, array_map('strval', $selectedValues)) ? ' checked' : '';
                    $html .= '<label class="checkbox-label"><input type="checkbox" name="' . $field['name'] . '[]" value="' . htmlspecialchars($optValue) . '"' . $checked . ' /> ' . $optLabel . '</label>';
                }
                $html .= '</div>';
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

        case 'DialogSelect':
            // 处理DialogSelect的多值情况
            $selectedValues = [];
            $displayText = '';

            if (is_string($savedValue)) {
                $selectedValues = !empty($savedValue) ? explode(',', $savedValue) : [];
            } elseif (is_array($savedValue)) {
                $selectedValues = $savedValue;
            }

            // 检查是否为多选模式
            $isMultiple = isset($options['_multiple']) && $options['_multiple'] === true;
            if ($isMultiple) {
                unset($options['_multiple']); // 移除特殊标记
            }

            // 获取layout配置，默认为vertical
            $layout = isset($options['_layout']) ? $options['_layout'] : 'vertical';
            if (isset($options['_layout'])) {
                unset($options['_layout']); // 移除特殊标记
            }

            // 检查是否需要动态获取选项数据
            if (isset($options['_dynamic']) && $options['_dynamic'] === true) {
                unset($options['_dynamic']); // 移除特殊标记

                // 动态获取选项数据的逻辑
                // 这里可以根据需要从数据库或其他数据源获取选项
                // 例如：从数据库获取主题列表、插件列表等
                $dynamicOptions = [];

                // 示例：根据字段名获取不同的动态数据
                switch ($name) {
                    case 'dialog_select_single':
                        // 从数据库获取主题列表
                        $themes = DB::getTtdf('available_themes');
                        if ($themes) {
                            $themeList = json_decode($themes, true);
                            if (is_array($themeList)) {
                                $dynamicOptions = $themeList;
                            }
                        }
                        break;
                    case 'dialog_select_multiple':
                        // 从数据库获取功能模块列表
                        $features = DB::getTtdf('available_features');
                        if ($features) {
                            $featureList = json_decode($features, true);
                            if (is_array($featureList)) {
                                $dynamicOptions = $featureList;
                            }
                        }
                        break;
                    default:
                        // 通用动态选项获取逻辑
                        $dynamicData = DB::getTtdf($name . '_options');
                        if ($dynamicData) {
                            $dynamicList = json_decode($dynamicData, true);
                            if (is_array($dynamicList)) {
                                $dynamicOptions = $dynamicList;
                            }
                        }
                        break;
                }

                // 如果获取到动态选项，则使用动态选项，否则使用默认选项
                if (!empty($dynamicOptions)) {
                    $options = $dynamicOptions;
                }
            }

            // 生成显示文本
            if (!empty($selectedValues) && !empty($options)) {
                $displayLabels = [];
                foreach ($selectedValues as $selectedValue) {
                    // 支持新的数组格式
                    if (is_array($options) && !empty($options)) {
                        // 检查是否为新的数组格式 [['value' => 'x', 'label' => 'y'], ...]
                        if (isset($options[0]) && is_array($options[0]) && isset($options[0]['value'])) {
                            foreach ($options as $option) {
                                if ($option['value'] === $selectedValue) {
                                    $displayLabels[] = $option['label'];
                                    break;
                                }
                            }
                        } else {
                            // 兼容旧的关联数组格式
                            if (isset($options[$selectedValue])) {
                                $displayLabels[] = $options[$selectedValue];
                            }
                        }
                    }
                }
                $displayText = implode(', ', $displayLabels);
            }

            $html = '<div class="form-group">';
            if ($escapedLabel) {
                $html .= '<label class="form-label">' . $escapedLabel . '</label>';
            }
            if ($rawDescription) {
                $html .= '<p class="description">' . $rawDescription . '</p>';
            }

            $html .= '<div class="dialog-select-container ' . ($layout === 'horizontal' ? 'horizontal-layout' : 'vertical-layout') . '" data-name="' . $name . '" data-multiple="' . ($isMultiple ? 'true' : 'false') . '" data-layout="' . $layout . '">';
            $html .= '<div class="dialog-select-input-group">';
            $html .= '<input type="text" class="form-control dialog-select-display" value="' . htmlspecialchars($displayText, ENT_QUOTES, 'UTF-8') . '" readonly placeholder="请选择..." />';
            $html .= '<button type="button" class="btn btn-primary dialog-select-trigger">选择</button>';
            $html .= '</div>';

            // 隐藏的输入框存储实际值
            $hiddenValue = is_array($selectedValues) ? implode(',', $selectedValues) : $savedValue;
            $html .= '<input type="hidden" name="' . $name . '" class="dialog-select-hidden" value="' . htmlspecialchars($hiddenValue ?? '', ENT_QUOTES, 'UTF-8') . '" />';

            // 生成选项数据（用于JavaScript）
            $optionsData = [
                'options' => [],
                'multiple' => $isMultiple,
                'title' => $escapedLabel ?: '选择选项',
                'selectedValues' => $selectedValues // 传递当前选中的值给前端
            ];

            // 转换选项格式为JavaScript期望的格式
            if (is_array($options) && !empty($options)) {
                // 检查是否为新的数组格式 [['value' => 'x', 'label' => 'y'], ...]
                if (isset($options[0]) && is_array($options[0]) && isset($options[0]['value'])) {
                    // 新的数组格式，直接使用
                    $optionsData['options'] = $options;
                } else {
                    // 兼容旧的关联数组格式
                    foreach ($options as $value => $label) {
                        $optionsData['options'][] = [
                            'value' => $value,
                            'label' => $label
                        ];
                    }
                }
            }

            $html .= '<script type="application/json" class="dialog-select-options" style="display: none;">' . json_encode($optionsData, JSON_UNESCAPED_UNICODE) . '</script>';
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
    $value = ($dbValue !== null && $dbValue !== '') ? $dbValue : ($field['value'] ?? '');

    // 处理标签和描述
    $label = $field['label'] ?? '';
    // 不再对 description 进行转义，允许其中的 HTML 标签正常渲染
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
            // 直接输出 description，不进行转义以支持 HTML
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
            // 直接输出 description，不进行转义以支持 HTML
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
            // 直接输出 description，不进行转义以支持 HTML
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
                // 获取layout配置，默认为vertical
                $layout = isset($field['layout']) ? $field['layout'] : 'vertical';
                $layoutClass = ($layout === 'horizontal') ? 'horizontal-layout' : 'vertical-layout';

                $html .= '<div class="radio-group ' . $layoutClass . '">';
                foreach ($field['options'] as $optValue => $optLabel) {
                    $checked = ($value == $optValue) ? ' checked' : '';
                    $html .= '<label class="radio-label"><input type="radio" name="' . $field['name'] . '" value="' . htmlspecialchars($optValue) . '"' . $checked . ' /> ' . $optLabel . '</label>';
                }
                $html .= '</div>';
            }
            // 直接输出 description，不进行转义以支持 HTML
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
                // 获取layout配置，默认为vertical
                $layout = isset($field['layout']) ? $field['layout'] : 'vertical';
                $layoutClass = ($layout === 'horizontal') ? 'horizontal-layout' : 'vertical-layout';

                $html .= '<div class="checkbox-group ' . $layoutClass . '">';
                $selectedValues = is_string($value) ? explode(',', $value) : (array)$value;
                foreach ($field['options'] as $optValue => $optLabel) {
                    $checked = in_array($optValue, $selectedValues) ? ' checked' : '';
                    $html .= '<label class="checkbox-label"><input type="checkbox" name="' . $field['name'] . '" value="' . htmlspecialchars($optValue) . '"' . $checked . ' /> ' . $optLabel . '</label>';
                }
                $html .= '</div>';
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

        case 'DialogSelect':
            // 处理DialogSelect类型
            $isMultiple = isset($field['multiple']) && $field['multiple'];
            $selectedValue = $value;
            
            // 生成显示文本
            $displayText = '';
            if (!empty($selectedValue)) {
                if ($isMultiple) {
                    // 多选模式
                    $selectedValues = is_string($selectedValue) ? explode(',', $selectedValue) : (array)$selectedValue;
                    $displayLabels = [];
                    
                    if (isset($field['options']) && is_array($field['options'])) {
                        // 检查是否为新的数组格式
                        if (isset($field['options'][0]) && is_array($field['options'][0]) && isset($field['options'][0]['value'])) {
                            // 新的数组格式 [['value' => 'x', 'label' => 'y'], ...]
                            foreach ($field['options'] as $option) {
                                if (in_array($option['value'], $selectedValues)) {
                                    $displayLabels[] = $option['label'];
                                }
                            }
                        } else {
                            // 旧的关联数组格式 ['value' => 'label', ...]
                            foreach ($selectedValues as $val) {
                                if (isset($field['options'][$val])) {
                                    $displayLabels[] = $field['options'][$val];
                                }
                            }
                        }
                    }
                    $displayText = implode(', ', $displayLabels);
                } else {
                    // 单选模式
                    if (isset($field['options']) && is_array($field['options'])) {
                        // 检查是否为新的数组格式
                        if (isset($field['options'][0]) && is_array($field['options'][0]) && isset($field['options'][0]['value'])) {
                            // 新的数组格式
                            foreach ($field['options'] as $option) {
                                if ($option['value'] == $selectedValue) {
                                    $displayText = $option['label'];
                                    break;
                                }
                            }
                        } else {
                            // 旧的关联数组格式
                            $displayText = isset($field['options'][$selectedValue]) ? $field['options'][$selectedValue] : '';
                        }
                    }
                }
            }
            
            // 处理选项数据
            $optionsData = [];
            if (isset($field['options']) && is_array($field['options'])) {
                // 检查是否为新的数组格式
                if (isset($field['options'][0]) && is_array($field['options'][0]) && isset($field['options'][0]['value'])) {
                    // 新的数组格式，直接使用
                    $optionsData = $field['options'];
                } else {
                    // 旧的关联数组格式，转换为新格式
                    foreach ($field['options'] as $optValue => $optLabel) {
                        $optionsData[] = ['value' => $optValue, 'label' => $optLabel];
                    }
                }
            }
            
            $html = '<div class="form-group">';
            if ($label) {
                $html .= '<label>' . $label . '</label>';
            }
            if ($description) {
                $html .= '<p class="description">' . $description . '</p>';
            }
            
            $html .= '<div class="dialog-select-container">';
            $html .= '<div class="dialog-select-input-group">';
            $html .= '<input type="text" class="form-control dialog-select-display" value="' . htmlspecialchars($displayText, ENT_QUOTES, 'UTF-8') . '" readonly placeholder="点击选择..." />';
            $html .= '<button type="button" class="btn btn-secondary dialog-select-btn" data-field="' . $field['name'] . '" data-multiple="' . ($isMultiple ? 'true' : 'false') . '">选择</button>';
            $html .= '</div>';
            $html .= '<input type="hidden" name="' . $field['name'] . '" class="dialog-select-value" value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '" />';
            $html .= '</div>';
            $html .= '</div>';
            
            // 输出选项数据到页面
            $html .= '<script type="application/json" id="options-' . $field['name'] . '">' . json_encode($optionsData, JSON_UNESCAPED_UNICODE) . '</script>';
            break;

        case 'ColorPicker':
            // 处理ColorPicker类型
            $colorValue = !empty($value) ? $value : '#000000';

            $html = '<div class="form-group">';
            if ($label) {
                $html .= '<label for="' . $prefixedName . '">' . $label . '</label>';
            }
            if ($description) {
                $html .= '<p class="description">' . $description . '</p>';
            }

            $html .= '<div class="colorpicker-container">';
            $html .= '<div class="colorpicker-input-group">';
            $html .= '<input type="color" class="colorpicker-color" value="' . htmlspecialchars($colorValue) . '" />';
            $html .= '<input type="text" name="' . $field['name'] . '" id="' . $prefixedName . '" class="form-control colorpicker-text" value="' . htmlspecialchars($colorValue) . '" placeholder="#000000" maxlength="7" />';
            $html .= '<div class="colorpicker-preview" style="background-color: ' . htmlspecialchars($colorValue) . ';"></div>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';
            break;
    }

    return $html;
}

function themeConfig($form)
{
    // 处理AJAX保存请求
    if (isset($_POST['action']) && $_POST['action'] === 'save_settings') {
        $response = array('success' => false, 'message' => '');
        
        try {
            // 获取所有POST数据
            $settings = $_POST;
            unset($settings['action']); // 移除action字段
            
            // 保存设置到数据库
            foreach ($settings as $key => $value) {
                if (is_array($value)) {
                    $value = implode(',', $value);
                }
                // 保存到数据库（DB::setTtdf内部会自动添加主题前缀）
                DB::setTtdf($key, $value);
            }
            
            $response['success'] = true;
            $response['message'] = '设置保存成功！';
        } catch (Exception $e) {
            $response['message'] = '保存失败：' . $e->getMessage();
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
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

    // 获取配置数据
    $tabs = require __DIR__ . '/../../app/Setup.php';
    
    // 处理配置数据，获取当前保存的值
    $configData = [];
    foreach ($tabs as $tab_id => $tab) {
        $configData[$tab_id] = [
            'title' => $tab['title'],
            'fields' => []
        ];
        
        if (isset($tab['html'])) {
            $configData[$tab_id]['html'] = $tab['html'];
        } elseif (isset($tab['fields'])) {
            foreach ($tab['fields'] as $field) {
                $fieldData = $field;
                
                // 获取保存的值
                if (isset($field['name']) && $field['type'] !== 'Html') {
                    $row = DB::getTtdf($field['name']);
                    $dbValue = $row;
                    
                    if ($dbValue !== null) {
                        // 对于复选框和DialogSelect，需要特殊处理比较
                        if ($field['type'] === 'Checkbox' || $field['type'] === 'DialogSelect') {
                            $setupDefault = is_array($field['value']) ? implode(',', $field['value']) : $field['value'];
                            $dbValueForCompare = $dbValue;
                            
                            // 标准化比较
                            $setupNormalized = $setupDefault;
                            $dbNormalized = $dbValueForCompare;
                            
                            if (!empty($setupNormalized)) {
                                $setupArray = explode(',', $setupNormalized);
                                $setupArray = array_map('trim', $setupArray);
                                sort($setupArray);
                                $setupNormalized = implode(',', $setupArray);
                            }
                            
                            if (!empty($dbNormalized)) {
                                $dbArray = explode(',', $dbNormalized);
                                $dbArray = array_map('trim', $dbArray);
                                sort($dbArray);
                                $dbNormalized = implode(',', $dbArray);
                            }
                            
                            if ($dbNormalized !== $setupNormalized) {
                                $fieldData['value'] = $dbValue;
                            }
                        } else {
                            if ($dbValue !== $field['value']) {
                                $fieldData['value'] = $dbValue;
                            }
                        }
                    }
                }
                
                $configData[$tab_id]['fields'][] = $fieldData;
            }
        }
    }

    // 获取当前保存的值
    $savedValues = array();
    foreach ($configData as $tabId => $tab) {
        if (isset($tab['fields'])) {
            foreach ($tab['fields'] as $field) {
                if (isset($field['name'])) {
                    $savedValues[$field['name']] = isset($field['value']) ? $field['value'] : '';
                }
            }
        }
    }
    
    // 合并配置数据和保存的值
    $fullConfig = array(
        'config' => $configData,
        'savedValues' => $savedValues
    );
    
    // 如果不是AJAX请求，输出HTML界面
?>
    <link rel="stylesheet" href="<?php get_theme_file_url('core/Static/Options.css', true) ?>">
    <script src="<?php get_theme_file_url('core/Static/vue.global.min.js', true) ?>"></script>
    
    <!-- Vue应用容器 -->
    <div id="options-app"></div>
    
    <!-- 配置数据 -->
    <script>
        window.TTDFConfig = {
            themeName: '<?php GetTheme::Name(true); ?>',
            themeVersion: '<?php GetTheme::Ver(true); ?>',
            ttdfVersion: '<?php TTDF::Ver(true); ?>',
            apiUrl: '<?php echo Typecho_Common::url(__TTDF_RESTAPI_ROUTE__ . '/ttdf/options', Helper::options()->siteUrl); ?>',
            tabs: <?php echo json_encode($configData, JSON_UNESCAPED_UNICODE); ?>,
            fullConfig: <?php echo json_encode($fullConfig, JSON_UNESCAPED_UNICODE); ?>
        };
    </script>
    
    <script src="<?php get_theme_file_url('core/Static/Options.js', true) ?>"></script>
<?php
}
