<?php
/**
 * Options Functions
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * THEME_URL
 * 用于后台设置获取主题目录
 */
define("THEME_URL", GetTheme::Url(false));
define("THEME_NAME", GetTheme::Name(false));

/**
 * 辅助函数：创建表单元素
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

function themeConfig($form)
{
?>
    <style>
        body {
            background: url(<?php GetTheme::Url() ?>/screenshot.webp) no-repeat 0 0;
            background-size: cover;
            background-attachment: fixed;
        }

        .main {
            background-color: #ffffffde;
            padding: 10px
        }

        .typecho-foot {
            padding: 1em 0 3em;
            background-color: #ffffffde;
        }

        .typecho-head-nav .operate a {
            background-color: #202328;
        }

        .typecho-option-tabs li {
            float: left;
            background-color: #fffbcc;
        }
    </style>
<?php
    // 定义表单元素配置
    $formElements = [
        [
            // Text
            'type' => 'Text',
            'name' => 'TTDF_Text',
            'value' => null,
            'label' => '文本框',
            'description' => '这是一个文本框~'
        ],
        [
            // Textarea
            'type' => 'Textarea',
            'name' => 'TTDF_Textarea',
            'value' => null,
            'label' => '文本域',
            'description' => '这是一个文本域~'
        ],
        [
            // Radio
            'type' => 'Radio',
            'name' => 'TTDF_Radio',
            'value' => 'option1', // 默认选中的值
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
            'name' => 'TTDF_Select',
            'value' => 'option2', // 默认选中的值
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
            'name' => 'TTDF_Checkbox',
            'value' => ['option1', 'option3'], // 默认选中的值（数组）
            'label' => '多选框',
            'description' => '这是一个多选框~',
            'options' => [
                'option1' => '选项一',
                'option2' => '选项二',
                'option3' => '选项三'
            ]
        ]
    ];
    // 循环添加表单元素
    foreach ($formElements as $TTDF) {
        $form->addInput(TTDF_FormElement(
            $TTDF['type'],
            $TTDF['name'],
            $TTDF['value'] ?? null,
            $TTDF['label'] ?? '',
            $TTDF['description'] ?? '',
            $TTDF['options'] ?? []
        ));
    }
}