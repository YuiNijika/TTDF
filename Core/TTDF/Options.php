<?php

/**
 * Options Functions
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

function themeConfig($form)
{
?>
    <style text="text/css">
        /* Typecho CSS */
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

        .typecho-page-main .typecho-option textarea {
            height: 150px;
        }

        /** 移除typecho的css样式 */
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
        }

        @media (min-width: 768px) {
            .col-tb-8 {
                width: unset;
            }
        }

        .col-mb-12 {
            width: unset;
        }

        /* TTDF Options CSS */
        #TTDF_Options .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 30px;
            background-color: #2c3e50;
            color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        #TTDF_Options .header h1 {
            font-size: 24px;
            font-weight: 500;
            margin: 0;
        }

        #TTDF_Options .save-btn {
            padding: 8px 20px;
            background-color: #27ae60;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        #TTDF_Options .save-btn:hover {
            background-color: #2ecc71;
            transform: translateY(-1px);
        }

        #TTDF_Options .tab-container {
            display: flex;
            width: 100%;
            border-radius: 0px 8px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            background-color: white;
        }

        #TTDF_Options .tab-buttons {
            display: flex;
            flex-direction: column;
            width: 220px;
            background-color: #34495e;
        }

        #TTDF_Options .tab-button {
            padding: 15px 25px;
            text-align: left;
            background-color: inherit;
            border: none;
            outline: none;
            cursor: pointer;
            color: #ecf0f1;
            font-size: 15px;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            position: relative;
        }

        #TTDF_Options .tab-button:hover {
            background-color: #3d566e;
            color: white;
        }

        #TTDF_Options .tab-button.active {
            background-color: #2c3e50;
            color: white;
            border-left: 4px solid #27ae60;
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
        }

        #TTDF_Options .tab-content {
            display: none;
            animation: fadeEffect 0.3s;
        }

        #TTDF_Options .tab-content.active {
            display: block;
        }

        #TTDF_Options .tab-content h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        #TTDF_Options .tab-content p {
            line-height: 1.6;
            color: #555;
        }

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
    </style>
    <script text="text/javascript">
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

            // 更新 URL hash
            window.location.hash = tabId;
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
                }
            }
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

    // 定义常量
    define("THEME_URL", GetTheme::Url(false));
    define("THEME_NAME", GetTheme::Name(false));

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
    // 关闭所有HTML标签
    $form->addItem(new EchoHtml('</div></div></div>'));
}
