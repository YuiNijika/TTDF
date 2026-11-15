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
?>
<article>
    <h1><?php echo Escaper::html((string) $archive->title); ?></h1>
    <div class="page-content"><?php $archive->content(); ?></div>
</article>

<?php Components::render(\TTDF\Framework::root(), 'Comments', ['archive' => $archive]); ?>
