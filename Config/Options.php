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
function themeConfig($form)
{
?>
    <!-- 自定义CSS样式 -->
    <style>
        body {
            font-weight:500;
            background: url(<?php echo GetTheme::AssetsUrl() ?>/images/background.webp)
            no-repeat 0 0;
            background-size: cover;
            background-attachment: fixed;
        }
        .clearfix, .row {
            background-color: #ffffff96;
            border-radius: 8px;
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
    // 副标题
    $subTitle = new Typecho_Widget_Helper_Form_Element_Text(
        'subTitle',
        NULL,
        NULL,
        _t('副标题'),
        _t('输入一段描述，将会显示在网站首页 title 后方，留空不显示。')
    );
    $form->addInput($subTitle);
    // favicon
    $faviconUrl = new Typecho_Widget_Helper_Form_Element_Text(
        'faviconUrl',
        NULL,
        '' . THEME_URL . '/Assets/images/favicon.svg',
        _t('网站图标'),
        _t('请填入网站图标，没有则显示主题默认图标。')
    );
    $form->addInput($faviconUrl);
}
