<?php

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

use TTDF\Compat\Typecho;
use TTDF\Framework;
use TTDF\Hooks\Hooks;
use TTDF\Support\Escaper;
use TTDF\Theme\Options;

$archive = $viewData['archive'] ?? null;
$options = Typecho::options();
$siteTitle = (string) ($options?->title ?? '');
$footerText = trim((string) Options::instance()->get('FooterText', ''));

Hooks::doAction('theme.before_footer', $archive);
?>
</main>
<?php Hooks::doAction('theme.after_main', $archive); ?>

<footer class="site-footer">
    <div class="container site-footer__inner">
        <p>
            &copy; <?php echo date('Y'); ?>
            <a href="<?php echo Escaper::url(Typecho::siteUrl()); ?>"><?php echo Escaper::html($siteTitle); ?></a>
            <?php if ($footerText !== ''): ?>
                <span aria-hidden="true">·</span> <?php echo Escaper::html($footerText); ?>
            <?php endif; ?>
        </p>
        <p>
            <a href="https://typecho.org" rel="noopener">Typecho</a>
            <span aria-hidden="true">·</span>
            <a href="https://blog.miomoe.cn/docs/ttdf/v5/index" rel="noopener">TTDF Next <?php echo Escaper::html(Framework::VERSION); ?></a>
        </p>
    </div>
</footer>

<?php Hooks::doAction('theme.footer', $archive); ?>
</body>
</html>
