<?php

/**
 * Options Functions
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

function themeConfig($form)
{
?>
    <style text="text/css">
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

        /* TTDF */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, sans-serif;
        }

        /* 主容器 */
        .TTDF-container {
            margin: 20px auto;
            background: white;
            border-radius: 3px;
            box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
        }

        /* 顶部标题栏 */
        .TTDF-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            border-bottom: 1px solid #dcdcde;
        }

        .TTDF-title {
            font-size: 20px;
            font-weight: 600;
            color: #1d2327;
        }

        .TTDF-actions {
            display: flex;
            gap: 10px;
        }

        /* 保存按钮 */
        .TTDF-save {
            background-color: #2271b1;
            color: white;
            border: none;
            border-radius: 3px;
            padding: 8px 16px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .TTDF-save:hover {
            background-color: #135e96;
        }

        /* 主体内容区域 */
        .TTDF-body {
            display: flex;
            min-height: 500px;
        }

        /* 垂直选项卡导航 */
        .TTDF-nav {
            width: 200px;
            border-right: 1px solid #dcdcde;
            background: #f6f7f7;
            max-height: 520px;
            overflow-y: auto;
        }

        .TTDF-nav-item {
            display: block;
            width: 100%;
            padding: 12px 15px;
            text-align: left;
            background: transparent;
            color: #1d2327;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            border-left: 3px solid transparent;
            border: none;
        }

        .TTDF-nav-item:hover {
            background-color: #f0f0f1;
            color: #2271b1;
        }

        .TTDF-nav-item.active {
            background-color: white;
            border-left-color: #2271b1;
            color: #2271b7;
            font-weight: 500;
        }

        /* 选项卡内容区域 */
        .TTDF-content {
            flex: 1;
            padding: 20px;
            max-height: 520px;
            overflow-y: auto;
        }

        .TTDF-tab-panel {
            display: none;
        }

        .TTDF-tab-panel.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        /* 表单样式 */
        .TTDF-fieldset {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #dcdcde;
        }

        .TTDF-fieldset:last-child {
            border-bottom: none;
        }

        .TTDF-field-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #1d2327;
        }

        .TTDF-field {
            margin-bottom: 15px;
        }

        .TTDF-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .TTDF-input {
            width: 100%;
            max-width: 500px;
            padding: 8px;
            border: 1px solid #8c8f94;
            border-radius: 3px;
            font-size: 14px;
        }

        .TTDF-input:focus {
            border-color: #2271b1;
            box-shadow: 0 0 0 1px #2271b1;
            outline: none;
        }

        .TTDF-description {
            font-size: 13px;
            color: #646970;
            margin-top: 5px;
        }

        /* 自定义滚动条 */
        .TTDF-nav::-webkit-scrollbar,
        .TTDF-content::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        .TTDF-nav::-webkit-scrollbar-track,
        .TTDF-content::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .TTDF-nav::-webkit-scrollbar-thumb,
        .TTDF-content::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }

        .TTDF-nav::-webkit-scrollbar-thumb:hover,
        .TTDF-content::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        /* 响应式设计 */
        @media (max-width: 782px) {
            .TTDF-body {
                flex-direction: column;
            }

            .TTDF-nav {
                width: 100%;
                max-height: 200px;
                border-right: none;
                border-bottom: 1px solid #dcdcde;
                display: flex;
                overflow-x: auto;
                overflow-y: hidden;
            }

            .TTDF-nav-item {
                text-align: center;
                white-space: nowrap;
                border-left: none;
                border-bottom: 3px solid transparent;
            }

            .TTDF-nav-item.active {
                border-left: none;
                border-bottom-color: #2271b1;
            }

            .TTDF-content {
                max-height: none;
                overflow-y: visible;
            }
        }

        /* 动画效果 */
        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }
    </style>
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            const tabButtons = document.querySelectorAll('.TTDF-nav-item');
            const tabPanels = document.querySelectorAll('.TTDF-tab-panel');
            const saveButton = document.querySelector('.TTDF-save');

            // 初始化
            function initTabsFromHash() {
                const hash = window.location.hash.substring(1);
                if (hash) {
                    const targetTab = document.getElementById(hash);
                    if (targetTab) {
                        // 找到对应的按钮
                        const correspondingBtn = document.querySelector(`.TTDF-nav-item[data-tab="${hash}"]`);
                        if (correspondingBtn) {
                            switchTab(correspondingBtn);
                        }
                    }
                }
            }

            // 切换标签页
            function switchTab(clickedBtn) {
                // 移除所有活动状态
                tabButtons.forEach(btn => btn.classList.remove('active'));
                tabPanels.forEach(panel => panel.classList.remove('active'));

                // 添加活动状态到点击的按钮
                clickedBtn.classList.add('active');

                // 显示对应的面板
                const tabId = clickedBtn.getAttribute('data-tab');
                document.getElementById(tabId).classList.add('active');

                // 更新URL哈希
                window.location.hash = tabId;
            }

            // 添加点击事件
            tabButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    switchTab(this);
                });
            });

            // 初始化标签页
            initTabsFromHash();

            // 监听哈希变化
            window.addEventListener('hashchange', initTabsFromHash);
        });
    </script>
<?php
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

    // 初始化HTML结构
    $form->addItem(new EchoHtml('
    <div id="TTDF_Options">
        <div class="TTDF-container">
            <div class="TTDF-header">
                <h1 class="TTDF-title">' . GetTheme::Name(false) . '<small> · ' . GetTheme::Ver(false) . '</small></h1>
                <div class="TTDF-actions">
                    <button class="TTDF-save" type="submit">保存设置</button>
                </div>
            </div>
            
            <div class="TTDF-body">
                <nav class="TTDF-nav">'));

    // 生成Tab导航按钮
    $tabs = require __DIR__ . '/../../app/Setup.php';
    $first_tab = true;
    foreach ($tabs as $tab_id => $tab) {
        $active = $first_tab ? 'active' : '';
        $form->addItem(new EchoHtml('
            <div class="TTDF-nav-item ' . $active . '" data-tab="' . $tab_id . '">
                ' . $tab['title'] . '
            </div>'));
        $first_tab = false;
    }

    $form->addItem(new EchoHtml('
                </nav>
                <div class="TTDF-content">'));

    // 生成Tab内容
    $first_tab = true;
    foreach ($tabs as $tab_id => $tab) {
        $active = $first_tab ? 'active' : '';
        $form->addItem(new EchoHtml('<div id="' . $tab_id . '" class="TTDF-tab-panel ' . $active . '">'));

        if (isset($tab['html'])) {
            foreach ($tab['html'] as $html) {
                $form->addItem(new EchoHtml($html['content']));
            }
        } else {
            foreach ($tab['fields'] as $field) {
                if ($field['type'] === 'Html') {
                    $form->addItem(new EchoHtml($field['content']));
                } else {
                    $form->addInput(TTDF_FormElement(
                        $field['type'],
                        $field['name'],
                        $field['value'] ?? null,
                        $field['label'] ?? '',
                        $field['description'] ?? '',
                        $field['options'] ?? []
                    ));
                }
            }
        }

        $form->addItem(new EchoHtml('</div>'));
        $first_tab = false;
    }

    // 关闭所有HTML标签
    $form->addItem(new EchoHtml('
                </div>
            </div>
        </div>
        <div style="text-align: center; margin-top: 20px;">
            © Framework By <a href="https://github.com/YuiNijika/TTDF" target="_blank" style="padding: 0px 3px;">TTDF</a> v' . TTDF::Ver(false) . '
        </div>
    </div>'));
}
