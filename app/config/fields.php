<?php

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

return [
    [
        'type' => 'Text',
        'name' => 'TTDF_Fields_Text',
        'value' => '',
        'label' => '文本框',
        'description' => '示例字段，可替换为业务字段。',
    ],
    [
        'type' => 'Textarea',
        'name' => 'TTDF_Fields_Textarea',
        'value' => '',
        'label' => '文本域',
        'description' => '示例字段，可替换为业务字段。',
        'attributes' => [
            'style' => 'height: 100px;',
        ],
    ],
    [
        'type' => 'Select',
        'name' => 'TTDF_Fields_Select',
        'value' => '',
        'label' => '下拉框',
        'description' => '示例字段，可替换为业务字段。',
        'options' => [
            'option1' => '选项一',
            'option2' => '选项二',
        ],
    ],
];
