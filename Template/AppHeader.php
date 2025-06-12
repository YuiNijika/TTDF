<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
?>
<!DOCTYPE html>
<html lang="<?php echo Get::Options('lang', false) ? Get::Options('lang', false) : 'zh-CN' ?>">

<head>
    <?php TTDF_Hook::do_action('load_head'); ?>
    <link rel="stylesheet" href="<?php get_theme_file_url('Assets/main.css?ver=') . get_theme_version(); ?>">
</head>

<body>
    <div id="app">