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
    </header>

    <div class="article__body prose">
        <?php $archive->content(); ?>
    </div>

    <?php Components::render($root, 'content/Comments', ['archive' => $archive]); ?>
</article>
