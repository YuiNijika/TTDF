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
?>
<?php Hooks::doAction('theme.before_footer'); ?>
</main>
<footer>
    <p>&copy; <?php echo date('Y'); ?> <?php echo Escaper::html($footer); ?></p>
</footer>
</body>
</html>
