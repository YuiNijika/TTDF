<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
TTDF_Hook::add_action('TTDF_Options_Code', function ($form) {
    // 定义所有设置项
    $tabs = [
        'text-elements' => [
            'title' => '文本设置',
            'fields' => [
                [
                    'type' => 'Text',
                    'name' => 'TTDF_Text',
                    'label' => '文本框',
                    'description' => '这是一个文本框~'
                ],
                [
                    'type' => 'Textarea',
                    'name' => 'TTDF_Textarea',
                    'label' => '文本域',
                    'description' => '这是一个文本域~'
                ]
            ]
        ],
        'select-elements' => [
            'title' => '选择设置',
            'fields' => [
                [
                    'type' => 'Radio',
                    'name' => 'TTDF_Radio',
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
                    'type' => 'Select',
                    'name' => 'TTDF_Select',
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
                    'type' => 'Checkbox',
                    'name' => 'TTDF_Checkbox',
                    'value' => ['option1', 'option3'],
                    'label' => '多选框',
                    'description' => '这是一个多选框~',
                    'options' => [
                        'option1' => '选项一',
                        'option2' => '选项二',
                        'option3' => '选项三'
                    ]
                ]
            ]
        ],
    ];

    // 生成Tab按钮
    $first_tab = true;
    foreach ($tabs as $tab_id => $tab) {
        $active = $first_tab ? 'active' : '';
        $form->addItem(new EchoHtml('
            <div class="tab-button ' . $active . '" onclick="openTab(event, \'' . $tab_id . '\')" 
                    data-tab="' . $tab_id . '">
                ' . $tab['title'] . '
            </div>'));
        $first_tab = false;
    }

    // 关闭Tab按钮区域，开始内容区域
    $form->addItem(new EchoHtml('</div><div class="tab-contents">'));

    // 生成Tab内容
    $first_tab = true;
    foreach ($tabs as $tab_id => $tab) {
        $active = $first_tab ? 'active' : '';
        $form->addItem(new EchoHtml('<div id="' . $tab_id . '" class="tab-content ' . $active . '">'));

        foreach ($tab['fields'] as $field) {
            $form->addInput(TTDF_FormElement(
                $field['type'],
                $field['name'],
                $field['value'] ?? null,
                $field['label'] ?? '',
                $field['description'] ?? '',
                $field['options'] ?? []
            ));
        }

        $form->addItem(new EchoHtml('</div>'));
        $first_tab = false;
    }
});
