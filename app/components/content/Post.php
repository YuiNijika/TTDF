<?php

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

use TTDF\Support\Escaper;
use TTDF\View\Components;

$archive = $viewData['archive'] ?? null;
if (!is_object($archive)) {
    return;
}

$root = \TTDF\Framework::root();
?>
<article class="container article">
    <header>
        <h1 class="article__title"><?php echo Escaper::html((string) $archive->title); ?></h1>
        <?php Components::render($root, 'content/PostMeta', ['archive' => $archive]); ?>
    </header>

    <div class="article__body prose">
        <?php $archive->content(); ?>
    </div>

    <p class="post-tags">标签：<?php $archive->tags(', ', true, '无'); ?></p>

    <nav class="post-nav" aria-label="相邻文章">
        <div>上一篇：<?php $archive->thePrev('%s', '没有了'); ?></div>
        <div>下一篇：<?php $archive->theNext('%s', '没有了'); ?></div>
    </nav>

    <?php Components::render($root, 'content/Comments', ['archive' => $archive]); ?>
</article>
