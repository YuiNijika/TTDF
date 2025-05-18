<?php 
/**
 * 欢迎使用Typecho主题模板开发框架！
 * @package TTDF v2.3
 * @author 鼠子(Tomoriゞ)
 * @version 1.0.0
 * @link https://github.com/ShuShuicu/TTDF
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
// 面向过程调用
get_template('AppHeader');

// 输出 TTDF 默认内容，开发前删除即可
TTDF::HelloWorld();

// 面向对象调用
Get::Template('AppFooter');