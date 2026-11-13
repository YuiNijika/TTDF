<?php

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

use TTDF\View\Components;
use TTDF\Theme\Templates;

Components::render(__DIR__, 'AppHeader', ['archive' => $this]);
$rendered = Templates::render(__DIR__, '404', $this, 'NotFound');
if (!$rendered):
?>
<h1>404</h1>
<p>页面不存在。</p>
<?php endif; ?>
<?php Components::render(__DIR__, 'AppFooter', ['archive' => $this]); ?>
