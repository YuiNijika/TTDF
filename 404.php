<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
class useSeo
{
    public static function Title() {
        echo '出错啦';
    }
    public static function Description() {
        echo '您访问的页面不存在';
    }
    public static function Keywords() {
        echo '404, error, 错误';
    }
}
Get::Template('AppHeader');
?>
<div class="error">
    <div style="text-align: center;">
        你似乎来到了没有知识存在的荒原
    </div>
</div>
<?php
Get::Template('AppFooter');
?>