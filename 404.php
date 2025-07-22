<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

const useSeo = [
    'title' => '出错啦',
    'description' => '您访问的页面不存在',
    'keywords' => '404, error, 错误'
];

Get::Components('AppHeader');
?>
<div class="error">
    <div style="text-align: center;">
        你似乎来到了没有知识存在的荒原
    </div>
</div>
<?php
Get::Components('AppFooter');
?>