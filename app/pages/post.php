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
$rendered = Templates::render($root, 'post', $this, 'Post');
if (!$rendered):
?>
<article>
    <h1><?php echo Escaper::html((string) $this->title); ?></h1>
    <time datetime="<?php echo date('c', (int) $this->created); ?>">
        <?php echo date('Y-m-d', (int) $this->created); ?>
    </time>
    <div class="post-content"><?php $this->content(); ?></div>
    <?php if ($this->tags): ?>
        <p class="tags">标签：<?php $this->tags(', ', true, 'none'); ?></p>
    <?php endif; ?>
</article>

<nav class="post-near">
    <p>上一篇：<?php $this->thePrev('%s', '没有了'); ?></p>
    <p>下一篇：<?php $this->theNext('%s', '没有了'); ?></p>
</nav>

<?php Components::render($root, 'Comments', ['archive' => $this]); ?>
<?php endif; ?>

<?php Components::render($root, 'AppFooter', ['archive' => $this]); ?>
