<?php

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

use TTDF\Support\Escaper;

$title = trim((string) ($viewData['title'] ?? ''));
$meta = trim((string) ($viewData['meta'] ?? ''));
$code = (string) ($viewData['code'] ?? '');

if (trim($code) === '') {
    return;
}
?>
<div class="code-block">
    <div class="code-block__head">
        <span><?php echo Escaper::html($title !== '' ? $title : '示例代码'); ?></span>
        <?php if ($meta !== ''): ?>
            <span><?php echo Escaper::html($meta); ?></span>
        <?php endif; ?>
    </div>
    <pre><code><?php echo Escaper::html($code); ?></code></pre>
</div>
