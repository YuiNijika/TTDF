<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
TTDF_Hook::do_action('useHeadSEO');
?>
<!doctype html>
<html lang="<?php echo Get::Options('lang', false) ? Get::Options('lang', false) : 'zh-CN' ?>">

<head>
    <meta charset="<?php Get::Options('charset', true) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, shrink-to-fit=no" />
    <title><?php useSeoMeta::Title(); ?></title>
    <meta name="keywords" content="<?php useSeoMeta::Keywords(); ?>" />
    <meta name="description" content="<?php useSeoMeta::Description();; ?>" />
<?php TTDF_Hook::do_action('load_head', true); ?>
</head>

<body>
    <main id="app">