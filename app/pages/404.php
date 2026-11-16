<?php

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

use TTDF\Framework;
use TTDF\Theme\Templates;
use TTDF\View\Components;

$root = Framework::root();

Components::render($root, 'layout/AppHeader', ['archive' => $this, 'title' => '页面没找到']);

$rendered = Templates::render($root, '404', $this, 'content/NotFound');
if (!$rendered):
?>
<section class="container not-found">
    <p class="not-found__code">404</p>
    <p class="not-found__text">这个地址没有内容，可能已经被删除，或者链接写错了。</p>
</section>
<?php endif; ?>

<?php Components::render($root, 'layout/AppFooter', ['archive' => $this]); ?>
