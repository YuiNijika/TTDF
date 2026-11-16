<?php

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

use TTDF\Compat\Typecho;
use TTDF\Support\Escaper;
use TTDF\View\Components;

$archive = $viewData['archive'] ?? null;
if (!is_object($archive)) {
    return;
}

$root = \TTDF\Framework::root();
$siteTitle = (string) (Typecho::options()?->title ?? '');
?>
<section class="container section">
    <div class="section__head">
        <h1 class="section__title">
            <?php if ($archive->is('index')): ?>
                <?php echo Escaper::html($siteTitle); ?>
            <?php else: ?>
                <?php $archive->archiveTitle([
                    'category' => '分类 %s 下的文章',
                    'search' => '包含关键字 %s 的文章',
                    'tag' => '标签 %s 下的文章',
                    'author' => '%s 发布的文章',
                ], '', ''); ?>
            <?php endif; ?>
        </h1>
    </div>

    <?php if ($archive->have()): ?>
        <div class="post-list">
            <?php while ($archive->next()): ?>
                <?php Components::render($root, 'content/PostCard', ['archive' => $archive]); ?>
            <?php endwhile; ?>
        </div>

        <nav class="pagination" aria-label="分页">
            <?php $archive->pageNav('上一页', '下一页'); ?>
        </nav>
    <?php else: ?>
        <p class="empty">没有找到内容。</p>
    <?php endif; ?>
</section>
