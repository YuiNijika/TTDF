# TTDF 项目规则

本项目包含两个概念，先区分场景再动手：

## 项目定位

- **ttdf-framework**：指本仓库 `D:\Codes\TTDF` 本身，负责框架核心、autoloader、配置、路由、Hook、REST、设置表、测试、文档与发布。
- **typecho-theme**：指使用 TTDF v5 构建的独立 Typecho 主题工程，例如包含自己的 `functions.php`、模板、`app/config/`、`app/router/`、`app/components/` 和资源的主题。

当前 `master` 是 v5 开发线，版本固定为 `5.0.0-next`；`v4` 分支已冻结，只用于旧主题与旧站点，不在 `master` 上恢复 v4 全局 API。

# 开发场景 Skill 读取

任务开始时，先只读浏览线上 Skill 首页：

- 框架内部任务：浏览 `https://blog.miomoe.cn/docs/skill/ttdf-dev.mdx`
- 主题开发任务：浏览 `https://blog.miomoe.cn/docs/skill/typecho-theme-dev.mdx`
- v5 文档：浏览 `https://blog.miomoe.cn/docs/ttdf/v5/index.mdx`

线上 URL 后缀 `.mdx` 直接返回 Markdown。线上文档是唯一真源，不要在仓库内维护完整文档镜像，也不要依赖 `D:\Codes\TTDF-Docs` 的旧内容。

# 硬性约束

- 运行时要求 PHP 8.2+、Typecho 1.2.1+ / 1.3.x；所有 PHP 文件使用 `declare(strict_types=1)`。
- 框架代码使用 `TTDF\` 命名空间，PSR-4 映射为 `TTDF\` → `vendor/yuinijika/ttdf/core/`、`TTDF\App\` → `app/`。
- 框架核心托管在 `vendor/yuinijika/ttdf/core/`，属于只读依赖区。主题开发者只修改 `app/`、模板与 `assets/`；升级框架时整体替换 `vendor/yuinijika/ttdf/`，不要手工改核心文件。
- 主题目录固定为：`functions.php` 唯一 PHP 入口、`app/config/app.php` 运行配置、`app/config/setup.php` 设置字段、`app/config/theme.php` 内容字段、`app/router/` 自动路由页面、`app/components/` 组件。不要恢复 `app/app.config.php`、`app/setup.php`、`app/fields.php` 或 `app/pages/`。
- `functions.php` 只负责 PHP 版本检查、注册自动加载器与调用 `Framework::boot()`；`themeConfig()` / `themeFields()` 由框架核心 `TypechoFunctions.php` 统一注册，主题不要重复定义。
- `router.pages_dir` 是相对 `app/` 的路径，默认 `router`；不要写 `app/router`，也不要把路由页面放到主题根目录。
- 框架静态资源托管在 `vendor/yuinijika/ttdf/static/`，属于只读依赖区；不得再向 `core/` 放置 Vue、Element Plus、设置页脚本或样式。
- `vendor/yuinijika/ttdf/static/options/options.js` 与 `options.css` 是手写源码；`static/vue/*`、`static/element-plus/*` 只通过 `pnpm add vue@latest element-plus@latest` 与 `pnpm sync:static` 同步，不手工编辑 dist 文件。
- `vendor/yuinijika/ttdf/package.json` 与 `pnpm-lock.yaml` 必须保留以复现静态资源，只忽略 `node_modules/`；不要在主题包中分发 `node_modules/`。
- `vendor/yuinijika/ttdf/core/` 与 `vendor/yuinijika/ttdf/static/` 必须随主题包分发，生产环境不依赖 Composer 或 Node.js；`vendor/` 下的 Composer 依赖与 `vendor/composer/` 仍不提交。
- 生产安装必须支持直接解压主题包运行，不要求站点安装 Composer；Composer 只用于开发、测试和静态分析。
- 除 Typecho 必需的 `themeConfig()`、`themeFields()` 外，不新增全局函数或全局类。
- 不新增 v4 兼容层；`Get`、`GetPost`、`GetUser`、`GetComment`、`GetSite`、`GetTheme`、`TTDF_*`、OPP 函数、全局 `config()` 已移除。
- 主题设置保留自定义 `ttdf` 表，但只能通过 `TTDF\Theme\Options` 读写；值使用 JSON，字段必须来自 `app/config/setup.php` 白名单。
- `app/config/app.php` 的 `settings.table` / `settings.schema_version` 只描述存储表与 schema 版本，不定义设置项；`debug`、REST、CORS、Token 环境变量、资源 CDN 通过 `TTDF\Theme\ThemeConfig` 合并配置默认值与站点设置。
- 页面模板入口固定使用 Typecho 官方模板名（`post.php`、`page.php`、`archive.php`、`404.php`）；页面主体映射使用 `TemplateMap` 字段与 `TTDF\Theme\Templates`，槽位只能是 `post`、`page`、`index`、`archive`、`category`、`tag`、`search`、`author`、`date`、`404`。
- `TemplateMap` 的组件值只允许 `[A-Za-z0-9_/-]`，不得包含 `..`；`Templates::render()` 必须通过 `Components::exists()` 校验后再 include，组件缺失时返回 `false` 并保留入口模板回退。
- `Options::all()` 只返回当前主题前缀下的值；迁移只处理当前主题与 schema 字段，不得改写其他主题的行。
- 禁止在每次请求中建表、写入默认值或执行迁移；建表与迁移只允许在后台设置页、保存动作或显式迁移命令中执行。
- REST 公开接口只读，必须过滤未发布、私密、密码保护和未审核内容；匿名评论写入已移除。
- 管理接口必须同时满足管理员登录 + Typecho nonce + 同源；CORS 只允许显式白名单，禁止反射任意 `Origin`。
- 管理 nonce 只从 `X-Typecho-Token` 请求头读取，不接受 query string 传递，避免 token 进入日志与 Referer。
- CORS Origin 必须是 `http(s)://host[:port]`；拒绝 `null`、路径、CRLF 与通配符，避免响应头注入。
- Token 不设默认值；至少 32 字符，使用 `hash_equals`，不得授予管理员权限。
- JSON 请求体上限 1 MiB，超限返回 413；不要先读取完整请求体再判断大小。
- REST 路由必须在 `Framework::boot()` 中按请求同步，不依赖后台保存时的内存路由或 `__routes_registered` 标记；公开接口关闭时仍要为管理员注册管理路由。
- `REST_Token_Env` 缺失时回退到 `rest.token_env`，显式空字符串表示禁用 Token；不要把“空值”和“未配置”混为一谈。
- 所有输出必须转义；URL 从 Typecho 配置与路由生成，不信任 `HTTP_HOST`；SQL 只通过 Typecho 查询构造器绑定参数。
- 公开评论 HTML 必须使用 Typecho 的 `stripTags` 与 `commentsHTMLTagAllowed` 白名单清理；不要直接输出 Markdown 渲染结果。
- 文件 include 必须做 `realpath` 边界校验；禁止 `eval`、`extract`、`unserialize` 用户输入和跨目录 include。
- 临时文件只放 `.tmp/`；禁止使用或创建 `.workbuddy/`；`logs/` 下的运行日志不提交，只保留 `.htaccess` 与 `index.php` 守卫。
- 不运行全仓格式化器批量重排无关文件；缩进跟随所在目录既有风格。
- 修改后至少运行 `composer lint`、`composer analyse`、`composer test`；本机 Composer 受 CA 限制时，可用 `.tmp/phpunit-12.phar` 与 `.tmp/phpstan.phar` 执行同等检查。

# Typecho 兼容性事实

- nonce 使用 `Widget\Security::alloc()->getToken($suffix)`；Typecho 没有 `Typecho\Widget\Security`，也没有 `Typecho\Security` 静态类。
- 管理员判断使用 `Widget\User::alloc()`；Typecho 没有 `Typecho\Widget\User`。
- `Typecho\Router` 没有 `add()` / `remove()`；用 `Helper::options()->routingTable` 与 `Typecho\Router\Parser` 构造内存路由，再调用 `Typecho\Router::setRoutes([$routes])`。
- Markdown 使用 `Utils\Markdown::convert()`；不要调用不存在的 `Typecho\Common::markdown()`。
- 查询类型是 `Typecho\Db\Query`；数组条件写作 `field IN ?`，不要写成 `field IN (?)`。
- 附件 `contents.text` 是 JSON（含 `path`、`name`、`size`、`type`、`mime`），不是文件路径；URL 必须走 `Widget\Upload::attachmentHandle()`，不要读取不存在的 `$options->attachmentUrl` 或 `contents.size`。
- 损坏的附件 JSON 不得回退成可访问路径；只对确认为旧版纯路径的文本走 legacy 分支。
- `Widget\Upload::attachmentHandle()` 在 Typecho 1.2.x 接收 `['attachment' => (object) ['path' => ...]]`，在 1.3.x 接收 `Typecho\Config`；通过反射兼容，不要写死单版签名。
- Typecho 只有 `Widget\Archive` 的 `header` / `footer` / `beforeRender` / `afterRender` 是真实钩子；不存在 `Widget\Contents\Post` / `Widget\Contents\Page` 类，不要按旧文档注册。
- 主题 `functions.php` 在 Archive 渲染阶段加载；REST 与自定义页面必须兼容 Typecho 的 404 handler，不要在启动阶段写数据库。`themeConfig()` / `themeFields()` 由核心注册，仍会在 Typecho 需要时调用。
- 自动路由同时支持 `[slug].php` 与 `[slug]/index.php`；动态目录必须先校验 `realpath` 边界。
- 示例主题使用 `index.php` / `archive.php` / `post.php` / `page.php` / `comments.php`，数据读取走 Typecho 原生 Widget。

# 常见任务流程

## 修改框架核心

1. 先浏览 `https://blog.miomoe.cn/docs/skill/ttdf-dev.mdx`
2. 修改 `vendor/yuinijika/ttdf/core/` 或 `app/`，保持 PSR-4 与命名空间边界；核心改动必须同时更新线上 `ttdf-dev` Skill 的路径说明
3. 补对应单元测试，安全相关改动补回归用例
4. 运行 `composer lint`、`composer analyse`、`composer test`
5. 公共 API 或行为变化时同步线上 `ttdf/v5/*` 文档

回归至少覆盖：公开 REST 内容过滤、评论字段最小化、附件父内容可见性、管理接口 401/403、CORS 畸形 Origin、1 MiB 请求体 413、设置主题隔离、日志脱敏与换行注入。

## 修改主题设置

1. 在 `app/config/setup.php` 增加字段，声明类型、默认值与说明
2. 在 `TTDF\Theme\Settings::validate()` 补类型转换与白名单校验
3. 如需迁移旧值，更新 `TTDF\Upgrade\Migrator`
4. 设置页 JS 只通过 `/ttdf/v1/admin/*` 读写，并携带 `X-Typecho-Token`
5. 不在前台请求中触发建表或迁移
6. 设置页“保存设置”下拉菜单的“执行迁移”调用 `POST /admin/migrate`，保持幂等

## 新增 REST 接口

1. 公开接口只允许 GET，并在 `PublicController` 显式过滤 `publish` / `approved` / 密码保护
2. 管理接口放 `/ttdf/v1/admin/*`，必须管理员登录 + nonce
3. 不返回邮箱、IP、Token、日志或未审核数据
4. 补鉴权、CORS、分页和内容过滤测试

## 新增模板槽位

1. 在 `TTDF\Theme\Templates::SLOTS` 增加槽位与中文标签
2. 在 `Settings::validate()` 的 `TemplateMap` 分支保持白名单校验
3. 在入口模板调用 `Templates::render()`，并保留内联回退
4. 归档槽位通过 `Templates::archiveSlot()` 解析，不要在模板里散落 `if/else` 组件名
5. 补 `TemplatesTest` 与设置页字段文档

## 文档更新

1. 只通过线上 Docs Agent 维护：`docs-rev.py pull` → 本地编辑 → `push --dry-run` → 确认后提交
2. 新建页面使用 `create`，已有页面使用 `push`；不要用 `push` 建页
3. 文档路径：v5 文档 `ttdf/v5/*`，v4 归档 `ttdf/v4/*`，Skill `skill/ttdf-dev` 与 `skill/typecho-theme-dev`
4. 不在仓库内保存完整文档或 Skill 镜像
5. `create` 会直接上线；`push` 只进入待审队列，可用 `POST /admin/site/docs/revisions/{uuid}/approve` 批准
6. 分区目录与排序通过 `PUT /admin/site/docs/metas` 维护；先用 `POST /agent/token` 换取 JWT，再以 `Cookie: access_token=<jwt>` 调用后台接口
7. `ttdf/v4/*` 只做归档修订，不新增功能文档；新内容写入 `ttdf/v5/*`

## 发布

- 版本号：`5.0.0-next`
- 发布标签：`v5.0.0-next`
- 发布前确认 `composer lint`、`composer analyse`、`composer test` 全绿
- 发布包必须包含 `vendor/yuinijika/ttdf/core/` 与 `vendor/yuinijika/ttdf/static/`，并排除 `tests/`、`vendor/composer/`、`vendor/yuinijika/ttdf/node_modules/`、`.tmp/`、`logs/`
- `v4` 分支不改动

# 前端 UI 规范

改动前端组件 / 页面 / 交互前必须读取：
- `web-design-guidelines`：先按 vercel-labs/web-interface-guidelines 最新规范设计，实现后逐条自检并输出 findings
- `web-artifacts-builder`：避免模板化 AI 风格，保持克制排版，移动端遵循触控与安全区规范
- 手势驱动 UI、弹簧动画、拖拽 / 滑动 / sheet 交互、半透明材质：先读取 `apple-design`，按 Apple 动效与材质规范实现

# GitHub Release 风格

发布 GitHub Release 时严格按以下结构，不写版本号 / 日期 / 作者：

1. 总述段落（2-4 个主题）
2. `## 新增功能`
3. `## 优化与调整`
4. `## 修复问题`

格式要求：列表项 `- `，小节标题 `## `，列表项之间不空行；“修复问题”每项以“修复”开头并写清现象 + 根因或方案；内容结合 commit 历史与项目记忆，只写真实改动。
