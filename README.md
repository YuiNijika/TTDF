# TTDF v5

> The Intuitive Typecho Theme Framework.

TTDF v5 是面向 Typecho 1.2.1+ / 1.3.x 的现代主题开发框架，要求 PHP 8.2+。当前版本为 `5.0.4-next`，与冻结的 `v4` 分支并行维护。

## 特性

- PSR-4 自动加载与 `TTDF\` 命名空间
- 轻量启动器，生产环境不依赖 Composer
- Typecho 原生 Widget、路由、数据库与安全能力
- 安全的自动页面路由与组件加载
- 可配置的页面模板映射（Typecho 入口模板 → `app/components/` 组件）
- 基于 Arco Design Vue 的后台设置面板与组件交互回归
- 首页、归档、文章、页面与评论模板，使用 Typecho 原生 Widget
- 确定性 Hook / Filter 系统
- 只读公开 REST API 与 nonce 保护的后台接口
- 自定义设置表、JSON 值与一次性迁移
- PHPUnit、PHPStan 与 GitHub Actions

## 运行环境

- PHP 8.2+
- Typecho 1.2.1+ / 1.3.x

## 安装

1. 将主题目录放入 `usr/themes/`。
2. 在 Typecho 后台启用主题。
3. 打开主题设置，保存一次设置以初始化 `ttdf` 表；REST 路由会在每次请求启动时自动同步。
4. 如需公开 REST API，在设置中开启，并配置 CORS 白名单。

主题包必须包含 `vendor/yuinijika/ttdf/core/` 与 `vendor/yuinijika/ttdf/static/`。这两个目录是框架托管区，主题开发者只应修改 `app/`、模板与 `assets/`；升级框架时整体替换 `vendor/yuinijika/ttdf/`，不要手工修改核心文件。

## 升级

1. 备份主题的 `app/`、模板与 `assets/`。
2. 用新版本整体替换 `vendor/yuinijika/ttdf/`。
3. 保留并检查 `app/config/theme.php` 的字段 schema，按需执行设置迁移。

## 目录结构

```text
functions.php                         唯一 PHP 入口，仅注册自动加载并启动框架
index.php                              Typecho 主题元信息，@version 是主题版本唯一来源
vendor/yuinijika/ttdf/core/           框架核心，只读依赖区
vendor/yuinijika/ttdf/static/         框架静态资源，只读依赖区
app/                                  主题代码区
  config/                             主题配置
    app.php                           运行配置：debug、REST、CORS、router
    theme.php                         后台主题设置字段与分组
    fields.php                        文章/页面自定义字段
  router/                             自动路由页面，router.pages_dir 默认指向这里
  pages/                              仅 Typecho 页面类型模板
  components/                         可复用组件与模板映射目标
  support/                            主题自有 PHP 类，目录小写，命名空间为主题名
assets/                              主题自有静态资源
```

`functions.php` 只负责加载自动加载器、注册主题命名空间（如
`Autoloader::register(__DIR__, 'MyTheme')`）并调用 `TTDF\Framework::boot()`。Typecho
需要的 `themeConfig()` 与 `themeFields()` 由框架核心统一注册，主题不需要再写
这两个函数，也不要在主题根目录新增其他 PHP 入口。页面模板固定放在
`app/pages/`，由框架在 `themeInit` 阶段接管 Typecho 的模板目录。`header.php`、
`footer.php`、`sidebar.php`、`comments.php` 不属于页面类型，必须分别写成
`app/components/layout/AppHeader.php`、`layout/AppFooter.php`、`layout/Sidebar.php`、
`content/Comments.php`。主题自有类放在 `app/support/`，例如
`app/support/PageData.php` 声明 `namespace MyTheme\Support;`；不要使用
`TTDF\App\`，也不要占用 `TTDF\` 命名空间。

## 主题配置

`app/config/app.php` 是主题运行配置的唯一入口：

```php
return [
    'debug' => false,
    'rest' => [
        'enabled' => false,
        'prefix' => 'ttdf/v1',
        'cors' => ['allowed_origins' => []],
        'token_env' => 'TTDF_REST_TOKEN',
    ],
    'router' => [
        'enabled' => true,
        'pages_dir' => 'router',
    ],
];
```

- `router.pages_dir` 是相对 `app/` 的路径，默认值 `router` 表示 `app/router/`。
- 主题版本只写在根 `index.php` 的 docblock `@version`；`app/config/app.php`
  不再接受 `version`。
- `debug`、REST 开关、CORS 白名单与 Token 环境变量可以在后台“运行配置”中覆盖。
- 资源 CDN 只来自主题设置 `Assets_CDN`；`app/config/app.php` 不再接受 `assets`。
- `settings.table` 是框架内部隐藏默认值，不在公开配置中声明；必要时可由
  `TTDF_SETTINGS_TABLE` 环境变量覆盖表名。
- 每个主题只保留一行 JSON：默认在 `ttdf` 表，`name` 是主题名；覆盖前自动备份为
  `{主题名}:backup_{Unix时间戳}`。
- Typecho `config.inc.php` 定义 `__TTDF_DATABASE_TABLE__ = false` 时不建表，设置改存
  `typecho_options` 的 `ttdf:{主题名}`（`user = 0`），备份为
  `ttdf:{主题名}:backup_{Unix时间戳}`；行名超过 32 字符时用 `:b_` 紧凑标记。
- 资源 URL 的 `?v=` 使用根 `index.php` 的 `@version`，主题自己的 `assets/` 与随包
  分发的 `vendor/yuinijika/ttdf/static/` 都跟随主题版本。

## 页面模板映射

`app/config/theme.php` 的 `Templates` 字段把 Typecho 模板入口映射到
`app/components/` 组件。默认映射与回退组件包括 `Post`、`Page`、`Archive`
与 `NotFound`；入口模板通过 `TTDF\Theme\Templates::render()` 渲染配置组件，
组件缺失时继续执行模板内置内容。

## 文档

- v5 文档：<https://blog.miomoe.cn/docs/ttdf/v5/index>
- v4 归档文档：<https://blog.miomoe.cn/docs/ttdf/v4/index>
- 框架开发 Skill：<https://blog.miomoe.cn/docs/skill/ttdf-dev>
- 主题开发 Skill：<https://blog.miomoe.cn/docs/skill/typecho-theme-dev>
- v4 稳定版：<https://github.com/YuiNijika/TTDF/tree/v4>

线上文档是唯一真源，仓库内不维护文档镜像。

## 开发

```bash
composer install
composer lint
composer analyse
composer test
```

本机 Composer 若受 CA 链限制，可使用 `.tmp/phpunit-12.phar` 与 `.tmp/phpstan.phar` 执行同等检查。

### 更新框架静态资源

`vendor/yuinijika/ttdf/static/` 中的 Vue、Arco Design Vue 发行文件由 pnpm 同步，
设置页的 `options/options.js` 与 `options/options.css` 为手写源码：

```bash
cd vendor/yuinijika/ttdf
pnpm install
pnpm add vue@latest @arco-design/web-vue@latest
pnpm sync:static
pnpm check:static
pnpm test:ui
```

`package.json` 与 `pnpm-lock.yaml` 用于复现构建，必须保留；只忽略 `node_modules/`。

`pnpm test:ui` 使用 Vitest + jsdom 覆盖全部设置字段控件，以及保存、错误提示、
Tab URL 同步、导出、导入和迁移流程。

## 安全说明

- 公开 REST 接口只读，默认关闭。
- 管理接口要求管理员登录、Typecho nonce（仅请求头）与同源。
- JSON 请求体上限 1 MiB，超限返回 413。
- 不提供匿名评论写入；评论走 Typecho 原生流程。
- CORS 使用显式白名单，不反射任意 Origin。
- 不信任 `HTTP_HOST`、`HTTP_X_FORWARDED_FOR` 或用户输入路径。

## 许可

MIT
