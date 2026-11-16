<?php

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

use TTDF\Support\Escaper;
use TTDF\View\Components;

$items = $viewData['items'] ?? [];
$title = trim((string) ($viewData['title'] ?? ''));
$note = trim((string) ($viewData['note'] ?? ''));

if (!is_array($items) || $items === []) {
    return;
}

$root = \TTDF\Framework::root();
?>
<section class="container section">
    <?php if ($title !== ''): ?>
        <div class="section__head">
            <h2 class="section__title"><?php echo Escaper::html($title); ?></h2>
            <?php if ($note !== ''): ?>
                <p class="section__note"><?php echo Escaper::html($note); ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="grid">
        <?php foreach ($items as $item): ?>
            <?php if (is_array($item)): ?>
                <?php Components::render($root, 'home/FeatureCard', $item); ?>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</section>
