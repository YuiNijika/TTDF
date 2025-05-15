<?php 
/**
 * 欢迎使用Typecho主题模板开发框架！
 * @package TTDF_v2
 * @author 鼠子(Tomoriゞ)
 * @version 1.0.0
 * @link https://github.com/ShuShuicu/Typecho-Theme-Development-Framework
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
Get::Template('AppHeader');

TTDF::HelloWorld();

echo <<<HTML
<br>
<button class="ty-ajax-submit" form-action="framework">获取框架版本</button>
<button class="ty-ajax-submit" form-action="ty_web_agent">获取浏览器信息</button>
HTML;

Get::Template('AppFooter');