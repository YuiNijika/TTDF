<?php

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

use TTDF\Framework;
use TTDF\Theme\Templates;
use TTDF\View\Components;

$root = Framework::root();

Components::render($root, 'layout/AppHeader', ['archive' => $this]);

$slot = Templates::archiveSlot($this);
$rendered = Templates::render($root, $slot, $this, 'content/Archive');
if (!$rendered && $slot !== 'archive') {
    $rendered = Templates::render($root, 'archive', $this, 'content/Archive');
}

if (!$rendered):
?>
<section class="container section">
    <div class="section__head">
        <h1 class="section__title">
            <?php $this->archiveTitle([
                'category' => '分类 %s 下的文章',
                'search' => '包含关键字 %s 的文章',
                'tag' => '标签 %s 下的文章',
                'author' => '%s 发布的文章',
            ], '', ''); ?>
        </h1>
    </div>

    <?php if ($this->have()): ?>
        <div class="post-list">
            <?php while ($this->next()): ?>
                <?php Components::render($root, 'content/PostCard', ['archive' => $this]); ?>
            <?php endwhile; ?>
        </div>

        <nav class="pagination" aria-label="分页"><?php $this->pageNav('上一页', '下一页'); ?></nav>
    <?php else: ?>
        <p class="empty">没有找到内容。</p>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php Components::render($root, 'layout/AppFooter', ['archive' => $this]); ?>
