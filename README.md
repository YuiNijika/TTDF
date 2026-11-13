# TTDF v5

> The Intuitive Typecho Theme Framework.

TTDF v5 是面向 Typecho 1.2.1+ / 1.3.x 的现代主题开发框架，要求 PHP 8.2+。当前版本为 `5.0.0-next`，与冻结的 `v4` 分支并行维护。

## 特性

- PSR-4 自动加载与 `TTDF\` 命名空间
- 轻量启动器，生产环境不依赖 Composer
- Typecho 原生 Widget、路由、数据库与安全能力
- 安全的自动页面路由与组件加载
- 可配置的页面模板映射（Typecho 入口模板 → `app/components/` 组件）
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
3. 保留并检查 `app/setup.php` 的字段 schema，按需执行设置迁移。

## 目录结构

```text
functions.php                         主题入口，注册自动加载并启动框架
vendor/yuinijika/ttdf/core/           框架核心，只读依赖区
vendor/yuinijika/ttdf/static/         框架静态资源，只读依赖区
app/                                  主题配置、组件与页面
  components/                         可复用组件与模板映射目标
assets/                               主题自有静态资源
*.php                                 Typecho 模板
```

## 页面模板映射

`app/setup.php` 的 `Templates` 字段把 Typecho 模板入口映射到
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

`vendor/yuinijika/ttdf/static/` 中的 Vue、Element Plus 发行文件由 pnpm 同步，
设置页的 `options/options.js` 与 `options/options.css` 为手写源码：

```bash
cd vendor/yuinijika/ttdf
pnpm install
pnpm add vue@latest element-plus@latest
pnpm sync:static
```

`package.json` 与 `pnpm-lock.yaml` 用于复现构建，必须保留；只忽略 `node_modules/`。

## 安全说明

- 公开 REST 接口只读，默认关闭。
- 管理接口要求管理员登录、Typecho nonce（仅请求头）与同源。
- JSON 请求体上限 1 MiB，超限返回 413。
- 不提供匿名评论写入；评论走 Typecho 原生流程。
- CORS 使用显式白名单，不反射任意 Origin。
- 不信任 `HTTP_HOST`、`HTTP_X_FORWARDED_FOR` 或用户输入路径。

## 许可

MIT
