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

$rendered = Templates::render($root, 'index', $this, 'home/WelCome');
if (!$rendered):
?>
<section class="container section">
    <div class="section__head">
        <h1 class="section__title"><?php $this->options->title(); ?></h1>
    </div>

    <?php if ($this->have()): ?>
        <div class="post-list">
            <?php while ($this->next()): ?>
                <?php Components::render($root, 'content/PostCard', ['archive' => $this]); ?>
            <?php endwhile; ?>
        </div>

        <nav class="pagination" aria-label="分页"><?php $this->pageNav('上一页', '下一页'); ?></nav>
    <?php else: ?>
        <p class="empty">还没有文章。</p>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php Components::render($root, 'layout/AppFooter', ['archive' => $this]); ?>
