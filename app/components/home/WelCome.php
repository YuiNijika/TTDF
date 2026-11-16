<?php

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

use TTDF\Compat\Typecho;
use TTDF\Framework;
use TTDF\Theme\Options;
use TTDF\View\Components;

$root = Framework::root();
$options = Typecho::options();
$siteTitle = trim((string) ($options?->title ?? ''));

$lead = trim((string) Options::instance()->get('SubTitle', ''));
if ($lead === '') {
    $lead = trim((string) ($options?->description ?? ''));
}

$code = <<<'PHP'
<?php

use TTDF\Framework;
use TTDF\Theme\Templates;
use TTDF\View\Components;

$root = Framework::root();

Components::render($root, 'layout/AppHeader', ['archive' => $this]);
Templates::render($root, 'index', $this, 'home/WelCome');
Components::render($root, 'layout/AppFooter', ['archive' => $this]);
PHP;

Components::render($root, 'home/Hero', [
    'eyebrow' => 'TTDF Next ' . Framework::VERSION,
    'title' => $siteTitle !== '' ? $siteTitle : 'TTDF Next',
    'lead' => $lead !== '' ? $lead : 'TTDF Next 负责加载、路由、组件、设置与 REST；主题只写页面、组件和配置。',
    'primaryLabel' => '阅读文档',
    'primaryUrl' => 'https://blog.miomoe.cn/docs/ttdf/v5/index',
    'secondaryLabel' => 'GitHub',
    'secondaryUrl' => 'https://github.com/YuiNijika/TTDF',
]);

Components::render($root, 'home/FeatureGrid', [
    'title' => '框架能力',
    'items' => [
        [
            'tag' => '目录',
            'title' => '启动入口',
            'text' => 'functions.php 只做启动。页面在 app/pages，组件在 app/components，自定义 URL 在 app/router。',
        ],
        [
            'tag' => '组件',
            'title' => '页面主体',
            'text' => '入口模板用 Templates::render() 选择组件，后台可以按页面类型替换。',
        ],
        [
            'tag' => '设置',
            'title' => 'JSON 存储',
            'text' => '字段写在 app/config/theme.php。每个主题一行 JSON，保存前自动备份。',
        ],
        [
            'tag' => '安全',
            'title' => '默认关闭',
            'text' => '公开 REST 默认关闭。管理接口要求登录、nonce 和同源。',
        ],
        [
            'tag' => 'REST',
            'title' => '只读接口',
            'text' => '/ttdf/v1 只读；扩展路由通过 rest.routes 注册。',
        ],
        [
            'tag' => '部署',
            'title' => '解压即用',
            'text' => '生产环境不需要 Composer 或 Node.js，升级时替换 vendor/yuinijika/ttdf。',
        ],
    ],
]);
?>
<section class="container section">
    <div class="section__head">
        <h2 class="section__title">入口模板</h2>
    </div>
    <?php Components::render($root, 'home/CodeBlock', ['title' => 'app/pages/index.php', 'meta' => 'PHP', 'code' => $code]); ?>
</section>

<section class="container section">
    <div class="section__head">
        <h2 class="section__title">最新文章</h2>
        <p class="section__note">按发布时间排序</p>
    </div>

    <?php $archive = $viewData['archive'] ?? null; ?>
    <?php if ($archive instanceof \Widget\Archive && $archive->have()): ?>
        <div class="post-list">
            <?php while ($archive->next()): ?>
                <?php Components::render($root, 'content/PostCard', ['archive' => $archive]); ?>
            <?php endwhile; ?>
        </div>

        <nav class="pagination" aria-label="分页">
            <?php $archive->pageNav('上一页', '下一页'); ?>
        </nav>
    <?php else: ?>
        <p class="empty">暂无文章。</p>
    <?php endif; ?>
</section>
