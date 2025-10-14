<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
?>
<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <?php TTDF_Hook::do_action('load_head'); ?>
    <link rel="stylesheet" href="<?php get_assets('app.css') ?>">
    <script type="module" src="<?php get_assets('main.js') ?>"></script>
</head>

<body>
    <div id="app">