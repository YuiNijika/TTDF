<?php

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

use TTDF\Compat\Typecho;
use TTDF\Hooks\Hooks;
use TTDF\Support\Escaper;
use TTDF\Theme\Media;
use TTDF\Theme\Options;

$options = Typecho::options();
$siteTitle = (string) ($options?->title ?? '');
$siteDescription = (string) ($options?->description ?? '');
$archive = $viewData['archive'] ?? null;
$archiveTitle = is_object($archive) && is_callable([$archive, 'getArchiveTitle'])
    ? trim((string) $archive->getArchiveTitle())
    : '';

$title = (string) ($viewData['title'] ?? '');
if ($title === '') {
    $title = $archiveTitle !== '' && $archiveTitle !== $siteTitle
        ? $archiveTitle . ' · ' . $siteTitle
        : $siteTitle;
}

$subtitle = (string) Options::instance()->get('SubTitle', '');
$logoUrl = Media::url(Options::instance()->get('Logo', ''));
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo Escaper::html($title); ?></title>
    <?php Hooks::doAction('theme.head', $archive); ?>
</head>
<body>
<header>
    <?php if ($logoUrl !== ''): ?>
        <a class="site-logo" href="<?php echo Escaper::url(Typecho::siteUrl()); ?>">
            <img src="<?php echo Escaper::url($logoUrl); ?>" alt="<?php echo Escaper::attr($siteTitle); ?>">
        </a>
    <?php else: ?>
        <a href="<?php echo Escaper::url(Typecho::siteUrl()); ?>"><?php echo Escaper::html($siteTitle); ?></a>
    <?php endif; ?>
    <?php if ($subtitle !== ''): ?>
        <p><?php echo Escaper::html($subtitle); ?></p>
    <?php elseif ($siteDescription !== ''): ?>
        <p><?php echo Escaper::html($siteDescription); ?></p>
    <?php endif; ?>
</header>
<?php Hooks::doAction('theme.after_header', $archive); ?>
<main>
