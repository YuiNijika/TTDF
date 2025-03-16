<?php 
/**
 * 欢迎使用Typecho主题模板开发框架！
 * @package TTDF_v2
 * @author 鼠子(Tomoriゞ)
 * @version 1.0.1
 * @link https://github.com/ShuShuicu/Typecho-Theme-Development-Framework
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
Get::Template('AppHeader');

TTDF::HelloWorld();

Get::Template('AppFooter');