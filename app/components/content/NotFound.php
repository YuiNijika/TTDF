<?php

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

use TTDF\Compat\Typecho;
use TTDF\Support\Escaper;
?>
<section class="container not-found">
    <p class="not-found__code">404</p>
    <p class="not-found__text">页面不存在。</p>

    <form class="site-search" method="post" action="<?php echo Escaper::url(Typecho::siteUrl()); ?>" role="search">
        <label class="sr-only" for="not-found-search">搜索文章</label>
        <input type="search" id="not-found-search" name="s" placeholder="搜索文章" autocomplete="off">
        <button class="btn" type="submit">搜索</button>
    </form>

    <a class="btn btn--primary" href="<?php echo Escaper::url(Typecho::siteUrl()); ?>">返回首页</a>
</section>
