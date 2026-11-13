<?php

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

use TTDF\Support\Escaper;

$archive = $viewData['archive'] ?? null;
if (!is_object($archive)) {
    return;
}
?>
<article>
    <h1><?php echo Escaper::html((string) $archive->title); ?></h1>
    <time datetime="<?php echo date('c', (int) $archive->created); ?>">
        <?php echo date('Y-m-d', (int) $archive->created); ?>
    </time>
    <div class="post-content"><?php $archive->content(); ?></div>
    <?php if ($archive->tags): ?>
        <p class="tags">标签：<?php $archive->tags(', ', true, 'none'); ?></p>
    <?php endif; ?>
</article>

<nav class="post-near">
    <p>上一篇：<?php $archive->thePrev('%s', '没有了'); ?></p>
    <p>下一篇：<?php $archive->theNext('%s', '没有了'); ?></p>
</nav>

<?php $archive->need('comments.php'); ?>
