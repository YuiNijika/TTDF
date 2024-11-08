<?php 
/**
 * 欢迎使用Typecho主题模板开发框架！
 * 使用说明 / 开发文档请查看README.md；
 * 如有任何不懂的问题欢迎联系作者<a href="https://space.bilibili.com/435502585"> · B站 · </a>提供帮助。
 * @package Develop
 * @author 鼠子Tomoriゞ
 * @version 1.0.0
 * @link https://blog.miomoe.cn/
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
// 引入header
$this->need('header.php');
?>

<!-- 调用默认内容 开发前请删除 Get::HelloWorld(); -->
<?php Get::HelloWorld(); ?>

<?php while ($this->next()): ?>
    <h1><?php Get::Title(); ?></h1>
    <p><?php Get::Date(); ?></p>
    <p><?php Get::Category(); ?></p>
    <p><?php Get::Tags(); ?></p>
    <p><?php Get::Excerpt(); ?></p>
    <p><?php Get::Permalink(); ?></p>
    <p><?php Get::Content(); ?></p>
    <p><?php Get::CommentsNum(); ?></p>
    <p><?php Get::PostsNum(); ?></p>
    <p><?php Get::PagesNum(); ?></p>
    <p><?php Get::CurrentPage(); ?></p>
    <p><?php Get::ArchiveTitle(); ?></p>
    <p><?php Get::Author(); ?></p>
    <p><?php Get::AuthorPermalink(); ?></p>
<?php endwhile; ?>

<?php 
// 引入footer
$this->need('footer.php'); 
?>