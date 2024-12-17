<?php
/**
 * 这里是前端输出中的Header内容。
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<!doctype html>
<html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, shrink-to-fit=no"/>
        <meta name="renderer" content="webkit"/>
        <link href="<?php echo Get::Options("faviconUrl") ? Get::Options("faviconUrl") : GetTheme::Url(true, 'Assets') . "/images/favicon.svg"; ?>" rel="icon" />
        <?php 
            $cssFiles = [
                'style.css',
            ];
            foreach ($cssFiles as $css){
        ?> 
        <link rel="stylesheet" href="<?php echo GetTheme::Url(false, 'Assets') . "/" . $css; ?>?ver=<?php GetTheme::Ver(); ?>">
        <?php }; ?>
        <title><?php $archiveTitle = GetPost::ArchiveTitle(
            [
                "category" => _t("「%s」分类"),
                "search" => _t("搜索结果"),
                "tag" => _t("「%s」标签"),
                "author" => _t("「%s」发布的文章"),
            ],""," - "
        );
        echo $archiveTitle;
        if (Get::Is("index") && !empty(Get::Options("subTitle")) && Get::CurrentPage() > 1) {
            echo "「第" . Get::CurrentPage() . "页」 - ";
        }
        $title = Get::Options("title");
        echo $title;
        if (Get::Is("index") && !empty(Get::Options("subTitle"))) {
            echo " - ";
            $subTitle = Get::Options("subTitle");
            echo $subTitle;
        }
        ?></title>
        <?php Get::Header(); ?>
    </head>
<body>
    <div id="app">
