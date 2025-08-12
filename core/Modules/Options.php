<?php

/**
 * Options Functions
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

function themeConfig($form)
{
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

        /* TTDF 主题样式 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, sans-serif;
        }

        .TTDF-container {
            margin: 20px auto;
            background: white;
            border-radius: 3px;
            box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
            max-width: 1200px;
        }

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

        .TTDF-title small {
            font-size: 16px;
            color: #646970;
            font-weight: normal;
        }

        .TTDF-actions {
            display: flex;
            gap: 10px;
        }

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

        .TTDF-body {
            display: flex;
            min-height: 500px;
        }

        .TTDF-nav {
            width: 200px;
            border-right: 1px solid #dcdcde;
            background: #f6f7f7;
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

        .TTDF-content {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
        }

        .TTDF-tab-panel {
            display: none;
        }

        .TTDF-tab-panel.active {
            display: block;
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
        });
    </script>
<?php
    // 初始化HTML结构
    $form->addItem(new EchoHtml('
    <div class="TTDF-container">
        <div class="TTDF-header">
            <h1 class="TTDF-title">' . GetTheme::Name(false) . '<small> · ' . GetTheme::Ver(false) . '</small></h1>
            <div class="TTDF-actions">
                <button class="TTDF-save" type="submit">保存设置</button>
            </div>
        </div>
        
        <div class="TTDF-body">
            <nav class="TTDF-nav">'));

    // 生成Tab导航按钮（默认激活第一个）
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

    // 生成Tab内容（默认显示第一个）
    $first_tab = true;
    foreach ($tabs as $tab_id => $tab) {
        $show = $first_tab ? 'active' : '';
        $form->addItem(new EchoHtml('<div id="' . $tab_id . '" class="TTDF-tab-panel ' . $show . '">'));

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
    </div>'));
}
