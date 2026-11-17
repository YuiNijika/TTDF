<?php

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

use TTDF\Compat\Typecho;
use TTDF\Hooks\Hooks;
use TTDF\Support\Escaper;
use TTDF\Theme\Assets;
use TTDF\Theme\Media;
use TTDF\Theme\Options;
use TTDF\Theme\Settings;
use TTDF\Theme\Vite;

$archive = $viewData['archive'] ?? null;
$options = Typecho::options();
$siteTitle = (string) ($options?->title ?? '');
$logoUrl = Media::url(Options::instance()->get('Logo', ''));

$themeValues = Settings::values();
$colorMode = (string) ($themeValues['Color_Mode'] ?? 'auto');
$lightColor = $themeValues['Color_Background'] ?? '';
$darkColor = $themeValues['Color_Background_Dark'] ?? '';
$lightThemeColor = is_string($lightColor) && $lightColor !== '' ? $lightColor : '#fbfbfa';
$darkThemeColor = is_string($darkColor) && $darkColor !== '' ? $darkColor : '#0f1115';

$pageTitle = trim((string) ($viewData['title'] ?? ''));
if ($pageTitle === '' && is_object($archive)) {
    if (is_callable([$archive, 'is']) && $archive->is('single')) {
        $pageTitle = trim((string) ($archive->title ?? ''));
    } elseif (is_callable([$archive, 'getArchiveTitle'])) {
        $pageTitle = trim((string) $archive->getArchiveTitle());
    }
}

$documentTitle = $pageTitle !== '' && $pageTitle !== $siteTitle
    ? $pageTitle . ' · ' . $siteTitle
    : $siteTitle;
?>
<!doctype html>
<html lang="zh-Hans">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo Escaper::html($documentTitle); ?></title>
    <meta name="theme-color" content="<?php echo Escaper::attr($lightThemeColor); ?>">
    <script>
        (function () {
            var root = document.documentElement;
            var preferred = <?php echo Escaper::js($colorMode); ?>;
            var light = <?php echo Escaper::js($lightThemeColor); ?>;
            var darkColor = <?php echo Escaper::js($darkThemeColor); ?>;
            var saved = null;
            try {
                saved = window.localStorage.getItem('ttdf-theme');
            } catch (error) {
                saved = null;
            }
            var dark = saved
                ? saved === 'dark'
                : (preferred === 'dark'
                    ? true
                    : (preferred === 'light' ? false : window.matchMedia('(prefers-color-scheme: dark)').matches));
            root.dataset.theme = dark ? 'dark' : 'light';
            var meta = document.querySelector('meta[name="theme-color"]');
            if (meta) {
                meta.setAttribute('content', dark ? darkColor : light);
            }
        })();
    </script>
    <?php Vite::printHead(); ?>
    <link rel="icon" href="<?php echo Escaper::url(Assets::url('img/favicon.svg')); ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?php echo Escaper::url(Assets::url('style.css')); ?>">
    <?php \TTDF\View\Components::render(\TTDF\Framework::root(), 'layout/ThemeColors'); ?>
    <?php Hooks::doAction('theme.head', $archive); ?>
</head>
<body>
<a class="skip-link" href="#main">跳到正文</a>

<header class="site-header">
    <div class="container site-header__inner">
        <a class="brand" href="<?php echo Escaper::url(Typecho::siteUrl()); ?>">
            <?php if ($logoUrl !== ''): ?>
                <img class="brand__logo" src="<?php echo Escaper::url($logoUrl); ?>" alt="" width="28" height="28">
            <?php endif; ?>
            <span class="brand__name"><?php echo Escaper::html($siteTitle); ?></span>
        </a>

        <nav class="site-nav" aria-label="主导航">
            <a href="<?php echo Escaper::url(Typecho::siteUrl()); ?>"<?php if (is_object($archive) && is_callable([$archive, 'is']) && $archive->is('index')): ?> aria-current="page"<?php endif; ?>>首页</a>
            <?php \Widget\Contents\Page\Rows::alloc()->to($pages); ?>
            <?php while ($pages->next()): ?>
                <a
                    href="<?php echo Escaper::url((string) $pages->permalink); ?>"
                    <?php if (is_object($archive) && is_callable([$archive, 'is']) && $archive->is('page', $pages->slug)): ?>aria-current="page"<?php endif; ?>
                ><?php echo Escaper::html((string) $pages->title); ?></a>
            <?php endwhile; ?>
        </nav>

        <div class="site-header__actions">
            <button class="theme-toggle" type="button" data-theme-toggle aria-label="切换到深色模式" aria-pressed="false" title="切换到深色模式">
                <svg class="theme-toggle__moon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 12.8A8.5 8.5 0 1 1 11.2 3a6.6 6.6 0 0 0 9.8 9.8Z"/>
                </svg>
                <svg class="theme-toggle__sun" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="4"/>
                    <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>
                </svg>
            </button>

            <form class="site-search" method="post" action="<?php echo Escaper::url(Typecho::siteUrl()); ?>" role="search">
                <label class="sr-only" for="site-search-input">搜索文章</label>
                <input type="search" id="site-search-input" name="s" placeholder="搜索文章" autocomplete="off">
                <button class="btn" type="submit">搜索</button>
            </form>
        </div>
    </div>
</header>

<script>
    (function () {
        var root = document.documentElement;
        var preferred = <?php echo Escaper::js($colorMode); ?>;
        var light = <?php echo Escaper::js($lightThemeColor); ?>;
        var darkColor = <?php echo Escaper::js($darkThemeColor); ?>;
        var button = document.querySelector('[data-theme-toggle]');
        if (!button) {
            return;
        }

        var media = window.matchMedia('(prefers-color-scheme: dark)');

        function stored() {
            try {
                return window.localStorage.getItem('ttdf-theme');
            } catch (error) {
                return null;
            }
        }

        function apply(theme) {
            var dark = theme === 'dark';
            root.dataset.theme = theme;
            button.setAttribute('aria-pressed', dark ? 'true' : 'false');
            button.setAttribute('aria-label', dark ? '切换到浅色模式' : '切换到深色模式');
            button.title = dark ? '切换到浅色模式' : '切换到深色模式';
            var meta = document.querySelector('meta[name="theme-color"]');
            if (meta) {
                meta.setAttribute('content', dark ? darkColor : light);
            }
        }

        apply(root.dataset.theme === 'dark' ? 'dark' : 'light');

        button.addEventListener('click', function () {
            var next = root.dataset.theme === 'dark' ? 'light' : 'dark';
            try {
                window.localStorage.setItem('ttdf-theme', next);
            } catch (error) {
                // Storage may be blocked; the current page still switches.
            }
            apply(next);
        });

        if (media.addEventListener) {
            media.addEventListener('change', function (event) {
                if (preferred === 'auto' && stored() === null) {
                    apply(event.matches ? 'dark' : 'light');
                }
            });
        }
    })();
</script>

<main id="main" class="site-main">
