<?php

/**
 * Options Functions
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

function themeConfig($form)
{
?>
    </div>

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
                width: unset;
            }
        }

        .col-mb-12 {
            width: unset;
        }

        /* TTDF Options 蓝色主题 */
        #TTDF_Options {
            --primary-color: #3498db;
            --primary-dark: #2980b9;
            --primary-light: #5dade2;
            --secondary-color: #2c3e50;
            --secondary-light: #34495e;
            --text-color: #333;
            --text-light: #ecf0f1;
            --border-radius: 6px;
            --transition-speed: 0.25s;
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }

        #TTDF_Options .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 30px;
            background: linear-gradient(135deg, var(--secondary-color), var(--secondary-light));
            color: var(--text-light);
            box-shadow: var(--shadow);
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            position: relative;
        }

        #TTDF_Options .header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-light));
        }

        #TTDF_Options .header h1 {
            font-size: 24px;
            font-weight: 500;
            margin: 0;
            letter-spacing: 0.5px;
        }

        #TTDF_Options .header h1 small {
            font-size: 14px;
            opacity: 0.8;
            margin-left: 8px;
            font-weight: 400;
        }

        #TTDF_Options .save-btn {
            padding: 10px 24px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all var(--transition-speed) ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        #TTDF_Options .save-btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        #TTDF_Options .tab-container {
            display: flex;
            width: 100%;
            border-radius: 0 0 var(--border-radius) var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            background-color: white;
        }

        #TTDF_Options .tab-buttons {
            display: flex;
            flex-direction: column;
            width: 240px;
            max-height: 520px;
            overflow-y: auto;
            overflow:auto;overflow-x: hidden;
            background-color: var(--secondary-light);
        }

        #TTDF_Options .tab-button {
            padding: 16px 25px;
            text-align: left;
            background-color: inherit;
            border: none;
            outline: none;
            cursor: pointer;
            color: var(--text-light);
            font-size: 15px;
            font-weight: 500;
            transition: all var(--transition-speed) ease;
            border-left: 4px solid transparent;
            position: relative;
        }

        #TTDF_Options .tab-button:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }

        #TTDF_Options .tab-button.active {
            background-color: rgba(0, 0, 0, 0.2);
            color: white;
            border-left: 4px solid var(--primary-color);
        }

        #TTDF_Options .tab-button.active::after {
            content: '';
            position: absolute;
            right: -10px;
            top: 50%;
            transform: translateY(-50%);
            width: 0;
            height: 0;
            border-top: 10px solid transparent;
            border-bottom: 10px solid transparent;
            border-right: 10px solid white;
        }

        #TTDF_Options .tab-contents {
            flex: 1;
            padding: 10px 20px;
            background-color: white;
            max-height: 500px;
            /* 设置固定高度 */
            overflow-y: auto;
            /* 启用垂直滚动 */
        }

        #TTDF_Options .tab-content {
            display: none;
            animation: fadeEffect 0.3s;
        }

        #TTDF_Options .tab-content.active {
            display: block;
        }

        #TTDF_Options .tab-content h2 {
            color: var(--secondary-color);
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid #eee;
            font-weight: 500;
        }

        #TTDF_Options .tab-content p {
            line-height: 1.7;
            color: var(--text-color);
            margin-bottom: 15px;
        }

        /* 表单元素样式 */
        #TTDF_Options .typecho-label {
            display: block;
            margin: 20px 0 8px;
            font-weight: 500;
            color: var(--secondary-color);
            font-size: 15px;
        }

        #TTDF_Options .typecho-input,
        #TTDF_Options .typecho-textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 14px;
            transition: border-color var(--transition-speed);
        }

        #TTDF_Options .typecho-input:focus,
        #TTDF_Options .typecho-textarea:focus {
            border-color: var(--primary-light);
            outline: none;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        #TTDF_Options .typecho-textarea {
            min-height: 120px;
            resize: vertical;
        }

        #TTDF_Options .typecho-radio,
        #TTDF_Options .typecho-checkbox {
            margin-right: 10px;
            vertical-align: middle;
        }

        #TTDF_Options .typecho-option {
            margin: 8px 0;
            display: block;
        }

        #TTDF_Options .typecho-description {
            font-size: 13px;
            color: #777;
            margin-top: 6px;
            line-height: 1.5;
        }

        /* 新增底部版权区块样式 */
        #TTDF_Options .footer {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 25px;
        }

        /* 调整主容器圆角 */
        #TTDF_Options .tab-container {
            border-radius: 0;
        }

        /* 动画效果 */
        @keyframes fadeEffect {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* 响应式设计 */
        @media screen and (max-width: 1024px) {
            #TTDF_Options .tab-container {
                margin-top: 10px;
                flex-direction: column;
                border-radius: var(--border-radius);
            }

            #TTDF_Options .tab-buttons {
                width: 100%;
                flex-direction: row;
                overflow-x: auto;
                white-space: nowrap;
                border-right: none;
                border-bottom: 1px solid #ddd;
                padding: 5px;
            }

            #TTDF_Options .tab-button {
                padding: 12px 20px;
                border-left: none;
                border-bottom: 4px solid transparent;
                display: inline-block;
                width: auto;
            }

            #TTDF_Options .tab-button.active {
                border-left: none;
                border-bottom: 4px solid var(--primary-color);
            }

            #TTDF_Options .tab-button.active::after {
                display: none;
            }

            #TTDF_Options .tab-contents {
                padding: 20px;
            }

            #TTDF_Options .header {
                padding: 15px;
                flex-direction: column;
                align-items: flex-start;
            }

            #TTDF_Options .header h1 {
                font-size: 20px;
                margin-bottom: 10px;
            }

            #TTDF_Options .save-btn {
                align-self: flex-end;
                margin-top: -40px;
            }
        }

        @media screen and (max-width: 480px) {
            #TTDF_Options .tab-button {
                padding: 10px 16px;
                font-size: 14px;
            }

            #TTDF_Options .tab-contents {
                padding: 15px;
            }

            #TTDF_Options .typecho-input,
            #TTDF_Options .typecho-textarea {
                padding: 8px 12px;
            }

            #TTDF_Options .header {
                padding: 12px 15px;
            }

            #TTDF_Options .save-btn {
                padding: 8px 16px;
                font-size: 13px;
            }
        }
    </style>
    <script type="text/javascript">
        // 打开指定标签页
        function openTab(evt, tabId) {
            // 隐藏所有内容
            var tabContents = document.getElementsByClassName("tab-content");
            for (var i = 0; i < tabContents.length; i++) {
                tabContents[i].classList.remove("active");
            }

            // 移除所有按钮的 active 类
            var tabButtons = document.getElementsByClassName("tab-button");
            for (var i = 0; i < tabButtons.length; i++) {
                tabButtons[i].classList.remove("active");
            }

            // 显示当前标签页内容并激活按钮
            document.getElementById(tabId).classList.add("active");
            evt.currentTarget.classList.add("active");

            // 更新 URL hash 并滚动到顶部
            window.location.hash = tabId;
            window.scrollTo(0, 0);
        }

        // 页面加载时检查 hash 并打开对应标签页
        document.addEventListener('DOMContentLoaded', function() {
            // 获取当前 hash
            var hash = window.location.hash.substring(1);

            // 如果有 hash 且对应的标签页存在，则打开它
            if (hash) {
                var tabContent = document.getElementById(hash);
                if (tabContent) {
                    // 隐藏所有内容
                    var tabContents = document.getElementsByClassName("tab-content");
                    for (var i = 0; i < tabContents.length; i++) {
                        tabContents[i].classList.remove("active");
                    }

                    // 移除所有按钮的 active 类
                    var tabButtons = document.getElementsByClassName("tab-button");
                    for (var i = 0; i < tabButtons.length; i++) {
                        tabButtons[i].classList.remove("active");
                    }

                    // 显示对应内容并激活按钮
                    tabContent.classList.add("active");

                    // 找到对应的按钮并激活
                    var buttons = document.querySelectorAll('.tab-button[data-tab="' + hash + '"]');
                    if (buttons.length > 0) {
                        buttons[0].classList.add("active");
                    }

                    // 滚动到顶部
                    window.scrollTo(0, 0);
                }
            }
        });
    </script>
<?php
    TTDF_Hook::add_action('TTDF_Options_Code', function ($form) {
        $tabs = require __DIR__ . '/../Setup.php';
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

            if (isset($tab['html'])) {
                foreach ($tab['html'] as $html) {
                    $form->addItem(new EchoHtml($html['content']));
                }
            } else {
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
            }

            $form->addItem(new EchoHtml('</div>'));
            $first_tab = false;
        }
    });

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
        <div class="header">
            <h1>' . GetTheme::Name(false) . '<small> · ' . GetTheme::Ver(false) . '</small></h1>
            <button class="save-btn" type="submit">保存设置</button>
        </div>
        <div class="tab-container">
            <div class="tab-buttons">'));

    TTDF_Hook::do_action('TTDF_Options_Code', $form);

    // 关闭所有HTML标签并添加底部版权信息
    $form->addItem(new EchoHtml('
        </div>
    </div>
    <div class="footer">
        © Framework By<a href="https://github.com/Typecho-Framework/Typecho-Theme-Development-Framework" target="_blank" style="padding: 0px 3px;">TTDF</a>v' . TTDF::Ver(false) . '
    </div>
    </div>'));
}
