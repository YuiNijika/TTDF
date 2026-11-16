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

$rendered = Templates::render($root, 'post', $this, 'content/Post');
if (!$rendered):
?>
<article class="container article">
    <header>
        <h1 class="article__title"><?php $this->title(); ?></h1>
        <?php Components::render($root, 'content/PostMeta', ['archive' => $this]); ?>
    </header>

    <div class="article__body prose"><?php $this->content(); ?></div>

    <p class="post-tags">标签：<?php $this->tags(', ', true, '无'); ?></p>

    <nav class="post-nav" aria-label="相邻文章">
        <div>上一篇：<?php $this->thePrev('%s', '没有了'); ?></div>
        <div>下一篇：<?php $this->theNext('%s', '没有了'); ?></div>
    </nav>

    <?php Components::render($root, 'content/Comments', ['archive' => $this]); ?>
</article>
<?php endif; ?>

<?php Components::render($root, 'layout/AppFooter', ['archive' => $this]); ?>
