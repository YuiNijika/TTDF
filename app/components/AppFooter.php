<?php

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

use TTDF\Compat\Typecho;
use TTDF\Hooks\Hooks;
use TTDF\Support\Escaper;
use TTDF\Theme\Options;

$options = Typecho::options();
$footer = (string) Options::instance()->get('FooterText', (string) ($options?->title ?? ''));
$archive = $viewData['archive'] ?? null;
?>
<?php Hooks::doAction('theme.before_footer', $archive); ?>
</main>
<?php Hooks::doAction('theme.after_main', $archive); ?>
<footer>
    <p>&copy; <?php echo date('Y'); ?> <?php echo Escaper::html($footer); ?></p>
</footer>
<?php Hooks::doAction('theme.footer', $archive); ?>
</body>
</html>
