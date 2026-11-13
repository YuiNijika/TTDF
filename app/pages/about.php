<?php

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

use TTDF\Support\Escaper;
use TTDF\View\Components;

Components::render(dirname(__DIR__, 2), 'AppHeader', [
    'title' => 'About',
    'canonical' => $route['path'] ?? 'about',
]);
?>
<h1>About</h1>
<p><?php echo Escaper::html((string) ($route['path'] ?? 'about')); ?></p>
<?php Components::render(dirname(__DIR__, 2), 'AppFooter'); ?>
