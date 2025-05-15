<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
?>
<!doctype html>
<html lang="<?php echo Get::Options('lang', false) ? Get::Options('lang', false) : 'zh-CN' ?>">

<head>
    <?php TTDF_Hook::do_action('load_head'); ?>
</head>

<body>
    <main id="app">
    