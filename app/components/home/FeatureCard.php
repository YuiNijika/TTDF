<?php

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

use TTDF\Support\Escaper;

$tag = trim((string) ($viewData['tag'] ?? ''));
$title = trim((string) ($viewData['title'] ?? ''));
$text = trim((string) ($viewData['text'] ?? ''));

if ($title === '' && $text === '') {
    return;
}
?>
<article class="card">
    <?php if ($tag !== ''): ?>
        <p class="card__tag"><?php echo Escaper::html($tag); ?></p>
    <?php endif; ?>
    <?php if ($title !== ''): ?>
        <h3 class="card__title"><?php echo Escaper::html($title); ?></h3>
    <?php endif; ?>
    <?php if ($text !== ''): ?>
        <p class="card__text"><?php echo Escaper::html($text); ?></p>
    <?php endif; ?>
</article>
