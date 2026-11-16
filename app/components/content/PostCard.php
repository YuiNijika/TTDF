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

$title = (string) ($archive->title ?? '');
$permalink = (string) ($archive->permalink ?? '');
$excerpt = trim(strip_tags((string) ($archive->excerpt ?? '')));
$excerpt = trim((string) preg_replace('/\s+/u', ' ', $excerpt));
if ($excerpt !== '') {
    $excerpt = \Typecho\Common::subStr($excerpt, 0, 120, '…');
}

$root = \TTDF\Framework::root();
?>
<article class="post-item">
    <h2 class="post-item__title">
        <a href="<?php echo Escaper::url($permalink); ?>"><?php echo Escaper::html($title); ?></a>
    </h2>

    <?php Components::render($root, 'content/PostMeta', ['archive' => $archive]); ?>

    <?php if ($excerpt !== ''): ?>
        <p class="post-item__excerpt"><?php echo Escaper::html($excerpt); ?></p>
    <?php endif; ?>
</article>
