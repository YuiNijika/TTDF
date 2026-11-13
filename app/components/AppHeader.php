<?php

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

use TTDF\Compat\Typecho;
use TTDF\Hooks\Hooks;
use TTDF\Seo\Meta;
use TTDF\Support\Escaper;
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

$description = (string) ($viewData['description'] ?? '');
if ($description === '' && is_object($archive) && is_callable([$archive, 'getArchiveDescription'])) {
    $description = trim((string) $archive->getArchiveDescription());
}
if ($description === '') {
    $description = $siteDescription;
}

$canonical = (string) ($viewData['canonical'] ?? '');
if ($canonical === '' && is_object($archive) && is_callable([$archive, 'getArchiveUrl'])) {
    $canonical = trim((string) $archive->getArchiveUrl());
}
if ($canonical === '') {
    $canonical = Typecho::siteUrl();
}

$subtitle = (string) Options::instance()->get('SubTitle', '');
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php Meta::render([
        'title' => $title,
        'description' => $description,
        'canonical' => $canonical,
    ]); ?>
    <?php Hooks::doAction('theme.head'); ?>
</head>
<body>
<header>
    <a href="<?php echo Escaper::url(Typecho::siteUrl()); ?>"><?php echo Escaper::html($siteTitle); ?></a>
    <?php if ($subtitle !== ''): ?>
        <p><?php echo Escaper::html($subtitle); ?></p>
    <?php elseif ($siteDescription !== ''): ?>
        <p><?php echo Escaper::html($siteDescription); ?></p>
    <?php endif; ?>
</header>
<?php Hooks::doAction('theme.after_header'); ?>
<main>
