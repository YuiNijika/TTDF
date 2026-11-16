<?php

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

use TTDF\Framework;
use TTDF\Support\Escaper;
use TTDF\Theme\Assets;
use TTDF\View\Components;

$root = Framework::root();

Components::render($root, 'layout/AppHeader', ['title' => '关于']);
?>
<section class="container article">
    <h1 class="article__title">关于这个示例主题</h1>

    <div class="article__body prose">
        <p>
            这是一个可以直接改的最小主题：页头、页尾、文章列表、文章页、独立页面、404 都是独立组件，
            样式只有 <code>assets/style.css</code> 一个文件。
        </p>

        <h2>目录</h2>
        <ul>
            <li><code>app/pages/</code>：Typecho 页面类型模板，只放 index、archive、post、page、404。</li>
            <li><code>app/components/content/</code>：页面主体与内容片段，例如 Post、Page、Archive、PostCard、Comments。</li>
            <li><code>app/components/layout/</code>：页面骨架，只放 AppHeader 与 AppFooter。</li>
            <li><code>app/components/home/</code>：首页专用组件，例如 WelCome、Hero、FeatureGrid、CodeBlock。</li>
            <li><code>app/config/</code>：<code>app.php</code> 运行配置、<code>theme.php</code> 后台设置字段、<code>fields.php</code> 文章字段。</li>
            <li><code>app/router/</code>：TTDF 自定义 URL，本页就是 <code>app/router/about.php</code>。</li>
        </ul>

        <h2>当前路由</h2>
        <p>路径：<code><?php echo Escaper::html((string) ($route['path'] ?? 'about')); ?></code></p>
        <p><a href="<?php echo Escaper::url(\TTDF\Compat\Typecho::siteUrl()); ?>">返回首页</a></p>

        <h2>样式</h2>
        <p>
            所有页面共用 <code>assets/style.css</code>；组件里没有内联样式。
            资源地址统一用 <code>Assets::url()</code>，会自动带上主题版本号。
        </p>
        <p>本页样式表：<?php echo Escaper::html(Assets::url('style.css')); ?></p>
    </div>
</section>
<?php Components::render($root, 'layout/AppFooter', []); ?>
