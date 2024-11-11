<?php
/**
 * 这里是前端输出中的Header内容。
 */
if (!defined("__TYPECHO_ROOT_DIR__")) {
    exit();
} ?>
<!doctype html>
<html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, shrink-to-fit=no"/>
        <meta name="renderer" content="webkit"/>
        <link href="<?php echo Get::Options("faviconUrl") ? Get::Options("faviconUrl") : Get::AssetsUrl() . "/images/favicon.svg"; ?>" rel="icon" />
        <link rel="stylesheet" href="<?php Get::AssetsUrl(); ?>/style.css?ver=<?php GetTheme::Ver(); ?>">
        <title><?php $archiveTitle = $this->archiveTitle(
            [
                "category" => _t("「%s」分类"),
                "search" => _t("搜索结果"),
                "tag" => _t("「%s」标签"),
                "author" => _t("「%s」发布的文章"),
            ],""," - "
        );
        echo $archiveTitle;
        if ($this->_currentPage > 1) {
            echo "「第" . $this->_currentPage . "页」 - ";
        }
        $title = Get::Options("title");
        echo $title;
        if ($this->is("index") && !empty(Get::Options("subTitle"))) {
            echo " - ";
            $subTitle = Get::Options("subTitle");
            echo $subTitle;
        }
        ?></title>
        <?php $this->header(); ?>
    </head>
<body>
    <div id="app">
