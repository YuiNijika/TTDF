<?php

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

use TTDF\Support\Escaper;
use TTDF\Theme\Templates;
use TTDF\View\Components;

$root = \TTDF\Framework::root();

Components::render($root, 'AppHeader', ['archive' => $this]);
$rendered = Templates::render($root, 'page', $this, 'Page');
if (!$rendered):
?>
<article>
    <h1><?php echo Escaper::html((string) $this->title); ?></h1>
    <div class="page-content"><?php $this->content(); ?></div>
</article>

<?php $this->need('comments.php'); ?>
<?php endif; ?>

<?php Components::render($root, 'AppFooter', ['archive' => $this]); ?>
