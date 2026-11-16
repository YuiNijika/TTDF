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

$rendered = Templates::render($root, 'page', $this, 'content/Page');
if (!$rendered):
?>
<article class="container article">
    <header>
        <h1 class="article__title"><?php $this->title(); ?></h1>
    </header>

    <div class="article__body prose"><?php $this->content(); ?></div>

    <?php Components::render($root, 'content/Comments', ['archive' => $this]); ?>
</article>
<?php endif; ?>

<?php Components::render($root, 'layout/AppFooter', ['archive' => $this]); ?>
