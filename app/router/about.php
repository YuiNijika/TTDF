<?php

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

use TTDF\Support\Escaper;
use TTDF\Compat\Typecho;

?>
<!DOCTYPE html>
<html lang="zh-Hans">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>About | TTDF</title>
</head>
<body>
    <main>
        <h1>About</h1>
        <p>当前路由：<?php echo Escaper::html((string) ($route['path'] ?? 'about')); ?></p>
        <p><a href="<?php echo Escaper::url(Typecho::siteUrl()); ?>">返回首页</a></p>
    </main>
</body>
</html>
