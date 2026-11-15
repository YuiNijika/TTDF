<?php

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

use TTDF\Support\Escaper;
use TTDF\Theme\Templates;
use TTDF\View\Components;

$root = \TTDF\Framework::root();

Components::render($root, 'AppHeader', ['archive' => $this]);
$slot = Templates::archiveSlot($this);
$rendered = Templates::render($root, $slot, $this, 'Archive');
if (!$rendered && $slot !== 'archive') {
    $rendered = Templates::render($root, 'archive', $this, 'Archive');
}
if (!$rendered):
?>
<?php if ($this->is('index')): ?>
    <h1><?php echo Escaper::html((string) $this->options->title); ?></h1>
<?php else: ?>
    <h1 class="archive-title">
        <?php $this->archiveTitle([
            'category' => '分类 %s 下的文章',
            'search' => '包含关键字 %s 的文章',
            'tag' => '标签 %s 下的文章',
            'author' => '%s 发布的文章',
        ], '', ''); ?>
    </h1>
<?php endif; ?>

<?php if ($this->have()): ?>
    <?php while ($this->next()): ?>
        <article>
            <h2>
                <a href="<?php echo Escaper::url((string) $this->permalink); ?>">
                    <?php echo Escaper::html((string) $this->title); ?>
                </a>
            </h2>
            <time datetime="<?php echo date('c', (int) $this->created); ?>">
                <?php echo date('Y-m-d', (int) $this->created); ?>
            </time>
            <div><?php $this->content(); ?></div>
        </article>
    <?php endwhile; ?>
<?php else: ?>
    <p>暂无内容。</p>
<?php endif; ?>

<nav class="pagination"><?php $this->pageNav(); ?></nav>
<?php endif; ?>

<?php Components::render($root, 'AppFooter', ['archive' => $this]); ?>
