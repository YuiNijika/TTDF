<?php 
/**
 * 欢迎使用Typecho主题模板开发框架！
 * @package TTDF
 * @author 鼠子(Tomoriゞ)
 * @version 1.0.0
 * @link https://github.com/YuiNijika/TTDF
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
// 面向过程调用
get_components('AppHeader');

// 开发前删除即可
WelCome();

// 面向对象调用
Get::Components('AppFooter');