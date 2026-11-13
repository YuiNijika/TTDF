<?php

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

use TTDF\Support\Escaper;
use TTDF\Compat\Typecho;

$archive = $viewData['archive'] ?? null;
if (!is_object($archive)) {
    return;
}
?>
<?php if ($archive->is('index')): ?>
    <h1><?php echo Escaper::html((string) (Typecho::options()?->title ?? '')); ?></h1>
<?php else: ?>
    <h1 class="archive-title">
        <?php $archive->archiveTitle([
            'category' => '分类 %s 下的文章',
            'search' => '包含关键字 %s 的文章',
            'tag' => '标签 %s 下的文章',
            'author' => '%s 发布的文章',
        ], '', ''); ?>
    </h1>
<?php endif; ?>

<?php if ($archive->have()): ?>
    <?php while ($archive->next()): ?>
        <article>
            <h2>
                <a href="<?php echo Escaper::url((string) $archive->permalink); ?>">
                    <?php echo Escaper::html((string) $archive->title); ?>
                </a>
            </h2>
            <time datetime="<?php echo date('c', (int) $archive->created); ?>">
                <?php echo date('Y-m-d', (int) $archive->created); ?>
            </time>
            <div><?php $archive->content(); ?></div>
        </article>
    <?php endwhile; ?>
<?php else: ?>
    <p>暂无内容。</p>
<?php endif; ?>

<nav class="pagination"><?php $archive->pageNav(); ?></nav>
