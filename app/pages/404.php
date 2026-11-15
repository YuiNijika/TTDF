<?php

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

use TTDF\Theme\Templates;
use TTDF\View\Components;

$root = \TTDF\Framework::root();

Components::render($root, 'AppHeader', ['archive' => $this]);
$rendered = Templates::render($root, '404', $this, 'NotFound');
if (!$rendered):
?>
<h1>404</h1>
<p>页面不存在。</p>
<?php endif; ?>
<?php Components::render($root, 'AppFooter', ['archive' => $this]); ?>
