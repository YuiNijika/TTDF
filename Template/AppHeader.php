<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
?>
<!doctype html>
<html lang="<?php echo Get::Options('lang', false) ? Get::Options('lang', false) : 'zh-CN' ?>">

<head>
    <meta charset="<?php Get::Options('charset', true) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, shrink-to-fit=no" />
    <?php
        TTDF::Functions('SEO');
        TTDF_Hook::do_action('load_head');
    ?>
    <link rel="stylesheet" href="<?php GetTheme::AssetsUrl() ?>/main.css?ver=<?php GetTheme::Ver(); ?>">
</head>

<body>
    <main id="app">