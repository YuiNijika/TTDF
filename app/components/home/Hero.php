<?php

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

use TTDF\Support\Escaper;

$eyebrow = trim((string) ($viewData['eyebrow'] ?? ''));
$title = trim((string) ($viewData['title'] ?? ''));
$lead = trim((string) ($viewData['lead'] ?? ''));
$primaryLabel = trim((string) ($viewData['primaryLabel'] ?? ''));
$primaryUrl = trim((string) ($viewData['primaryUrl'] ?? ''));
$secondaryLabel = trim((string) ($viewData['secondaryLabel'] ?? ''));
$secondaryUrl = trim((string) ($viewData['secondaryUrl'] ?? ''));
?>
<section class="container hero">
    <?php if ($eyebrow !== ''): ?>
        <p class="hero__badge"><?php echo Escaper::html($eyebrow); ?></p>
    <?php endif; ?>

    <?php if ($title !== ''): ?>
        <h1 class="hero__title"><?php echo Escaper::html($title); ?></h1>
    <?php endif; ?>

    <?php if ($lead !== ''): ?>
        <p class="hero__lead"><?php echo Escaper::html($lead); ?></p>
    <?php endif; ?>

    <?php if ($primaryUrl !== '' || $secondaryUrl !== ''): ?>
        <div class="hero__actions">
            <?php if ($primaryUrl !== ''): ?>
                <a class="btn btn--primary" href="<?php echo Escaper::url($primaryUrl); ?>" rel="noopener">
                    <?php echo Escaper::html($primaryLabel !== '' ? $primaryLabel : '开始阅读'); ?>
                </a>
            <?php endif; ?>
            <?php if ($secondaryUrl !== ''): ?>
                <a class="btn btn--ghost" href="<?php echo Escaper::url($secondaryUrl); ?>" rel="noopener">
                    <?php echo Escaper::html($secondaryLabel !== '' ? $secondaryLabel : '了解更多'); ?>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
