<?php

declare(strict_types=1);

use TTDF\Support\Escaper;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$archive = $viewData['archive'] ?? null;
if (!is_object($archive)) {
    return;
}

$showCategory = ($viewData['showCategory'] ?? true) === true;
$showComments = ($viewData['showComments'] ?? true) === true;
$categories = $showCategory && isset($archive->categories) && is_array($archive->categories)
    ? $archive->categories
    : [];
?>
<ul class="post-meta">
    <li>
        <time datetime="<?php $archive->date('c'); ?>"><?php $archive->date('Y-m-d'); ?></time>
    </li>
    <?php if ($categories !== []): ?>
        <li>
            <?php $archive->category(', '); ?>
        </li>
    <?php endif; ?>
    <?php if ($showComments): ?>
        <li>
            <a href="<?php echo Escaper::url((string) $archive->permalink); ?>#comments">
                <?php $archive->commentsNum('暂无评论', '1 条评论', '%d 条评论'); ?>
            </a>
        </li>
    <?php endif; ?>
</ul>
