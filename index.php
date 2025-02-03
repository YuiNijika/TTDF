<?php 
/**
 * 欢迎使用Typecho主题模板开发框架！
 * 使用说明 / 开发文档请查看README.md；
 * 如有任何不懂的问题欢迎联系作者<a href="https://space.bilibili.com/435502585"> · B站 · </a>提供帮助。
 * @package Develop
 * @author 鼠子(Tomoriゞ)、Sualiu
 * @version 1.0.0
 * @link https://blog.miomoe.cn/
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
// 引入header
Get::Need('header.php');
?>

<!-- 调用默认内容 开发前请删除 Get::HelloWorld(); -->
<?php Get::HelloWorld(); ?>

<?php 
// 引入footer
Get::Need('footer.php');
?>