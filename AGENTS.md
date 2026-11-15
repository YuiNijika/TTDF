# TTDF 项目规则

本项目包含两个概念，先区分场景再动手：

## 项目定位

- **ttdf-framework**：指本仓库 `D:\Codes\TTDF` 本身，负责框架核心、autoloader、配置、路由、Hook、REST、设置表、测试、文档与发布。
- **typecho-theme**：指使用 TTDF v5 构建的独立 Typecho 主题工程，例如包含自己的 `functions.php`、`app/pages/` 页面模板、`app/config/`、`app/router/`、`app/components/` 和资源的主题。

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
- 框架核心托管在 `vendor/yuinijika/ttdf/core/`，属于只读依赖区。主题开发者只修改 `app/`、`assets/` 与根 `index.php` 元信息；升级框架时整体替换 `vendor/yuinijika/ttdf/`，不要手工改核心文件。
- 主题目录固定为：`functions.php` 唯一初始化 PHP 入口、根 `index.php` 仅作 Typecho 主题元信息 stub、`app/pages/` Typecho 页面类型模板与 partial、`app/config/app.php` 运行配置、`app/config/theme.php` 后台设置字段、`app/config/fields.php` 文章/页面字段、`app/router/` TTDF 自定义 URL 路由、`app/components/` 组件。不要恢复 `app/app.config.php`、`app/config/setup.php`、`app/setup.php`、`app/fields.php`。
- 根 `index.php` 是 Typecho 后台主题发现机制的硬性要求（`Widget\Themes\Rows` 固定读取主题根 `index.php` 的 docblock 元信息），只允许保留 `<?php`、严格类型声明、元信息注释和 `__TYPECHO_ROOT_DIR__` 守卫，不得写页面渲染逻辑。真实首页模板是 `app/pages/index.php`。
- 主题版本号的唯一来源是根 `index.php` 的 `@version` 元信息；`app/config/app.php` 不配置版本号，`Typecho::themeVersion()` 只读取主题根 `index.php` 的 docblock，不回退到设置表或配置数组。
- `functions.php` 只负责 PHP 版本检查、注册自动加载器与调用 `Framework::boot()`；`themeConfig()` / `themeFields()` / `themeInit()` 由框架核心 `TypechoFunctions.php` 统一注册，主题不要重复定义。
- `router.pages_dir` 是相对 `app/` 的路径，默认 `router`；不要写 `app/router`，也不要把路由页面放到主题根目录。
- `app/router/` 只承载 TTDF 自定义 URL 路由；Typecho 已判定的首页、文章、独立页面、归档、分类、标签、搜索、作者、日期与 404 视图统一放在 `app/pages/`。框架通过 `TTDF\Theme\PageTemplates` 在 `themeInit` 阶段改写 `Archive::$themeDir`，不得自行解析 URL 复刻 Typecho 的归档判断。
- 框架静态资源托管在 `vendor/yuinijika/ttdf/static/`，属于只读依赖区；不得再向 `core/` 放置 Vue、Arco Design Vue、设置页脚本或样式。
- `vendor/yuinijika/ttdf/static/options/options.js` 与 `options.css` 是手写源码；`static/vue/*`、`static/arco/*` 只通过 `pnpm add vue@latest @arco-design/web-vue@latest` 与 `pnpm sync:static` 同步，不手工编辑 dist 文件。
- 设置页 UI 固定使用 Arco Design Vue，不得重新引入 Element Plus、`el-*` 标签或 Element Plus CSS 选择器。Arco 组件交互回归使用 `pnpm test:ui`（Vitest + jsdom + `@vue/test-utils`）。
- 设置页分类使用语义化 `<nav>` + 单内容面板，不使用 Arco Tabs 的滑动内容容器；首次加载使用骨架屏而非 spinner；保持中性色板，不添加蓝色标题条、蓝色焦点框或导航强调条；placeholder 不回填字段 `description`。
- `vendor/yuinijika/ttdf/package.json` 与 `pnpm-lock.yaml` 必须保留以复现静态资源，只忽略 `node_modules/`；不要在主题包中分发 `node_modules/`。
- `vendor/yuinijika/ttdf/core/` 与 `vendor/yuinijika/ttdf/static/` 必须随主题包分发，生产环境不依赖 Composer 或 Node.js；`vendor/` 下的 Composer 依赖与 `vendor/composer/` 仍不提交。
- 生产安装必须支持直接解压主题包运行，不要求站点安装 Composer；Composer 只用于开发、测试和静态分析。
- 除 Typecho 必需的 `themeConfig()`、`themeFields()` 外，不新增全局函数或全局类。
- 不新增 v4 兼容层；`Get`、`GetPost`、`GetUser`、`GetComment`、`GetSite`、`GetTheme`、`TTDF_*`、OPP 函数、全局 `config()` 已移除。
- 主题设置保留自定义 `ttdf` 表，但只能通过 `TTDF\Theme\Options` 读写；值使用 JSON，字段必须来自 `app/config/theme.php` 白名单。
- `app/config/app.php` 只公开 `debug`、`rest` 与 `router`。`version`、`assets`、`settings` 不得写入主题的公开配置：版本在根 `index.php`，资源 CDN 在主题设置 `Assets_CDN`。
- `settings.table` / `settings.schema_version` 是框架内部的隐藏存储默认值，不定义设置项，也不要求在 `app/config/app.php` 出现；主题可按需隐藏覆盖，`TTDF_SETTINGS_TABLE` 环境变量仍可覆盖表名。
- `debug`、REST、CORS、Token 开关与 Token 环境变量通过 `TTDF\Theme\ThemeConfig` 合并静态默认值与主题设置；资源 CDN 只从主题设置 `Assets_CDN` 读取，静态 `assets.cdn` 已移除。
- 页面模板入口固定在 `app/pages/`，并使用 Typecho 官方模板名（`index.php`、`post.php`、`page.php`、`archive.php`、`single.php`、`404.php`）；默认只保留 `index.php`、`archive.php`、`post.php`、`page.php`、`404.php`。页面主体映射使用 `TemplateMap` 字段与 `TTDF\Theme\Templates`，槽位只能是 `post`、`page`、`index`、`archive`、`category`、`tag`、`search`、`author`、`date`、`404`。
- 评论渲染固定为 `app/components/Comments.php`，不得再创建 `app/pages/comments.php`；`post.php` / `page.php` 的内联回退与 `Post` / `Page` 组件都必须通过 `Components::render(..., 'Comments', ['archive' => $archive])` 加载。
- 评论组件内不能访问 `$archive->user` / `$archive->options`（Typecho 受保护成员）；登录用户与站点配置分别使用 `Widget\User::alloc()` 与 `Widget\Options::alloc()`。
- `TemplateMap` 的组件值只允许 `[A-Za-z0-9_/-]`，不得包含 `..`；`Templates::render()` 必须通过 `Components::exists()` 校验后再 include，组件缺失时返回 `false` 并保留入口模板回退。
- `TemplateMap` 字段必须声明 `components` 白名单（如 `['Post', 'Page', 'Archive', 'NotFound']`），只列可独立渲染页面主体的组件；`PostMeta`、`Comments`、`AppHeader` 等片段不得进入白名单。`Settings::validate()` 与 `Templates::mapping()` 都必须按白名单过滤，运行时过滤用于兼容旧版本保存的非法映射。
- `404` 只能映射到 `NotFound` 或显式留空；不要映射到 `Post` / `PostMeta`，Typecho 404 Archive 的 `type` 为 null，`permalink()` 会触发 TypeError。
- `Templates::render()` 必须在独立输出缓冲中执行组件；组件抛错时丢弃已输出的半截 HTML。生产模式返回 `false` 让入口模板回退，调试模式重新抛出到全局错误页。
- `Diagnostics\ErrorHandler` 必须清空半截输出并渲染完整独立 HTML 错误页，不得只 echo `TTDF error:` 文本；状态码沿用 Typecho 已设置的 4xx/5xx，否则回退 500，生产模式隐藏异常消息与堆栈，调试模式显示并转义详情。
- `Options::all()` 只返回当前主题前缀下的值；迁移只处理当前主题与 schema 字段，不得改写其他主题的行。
- 禁止在每次请求中建表、写入默认值或执行迁移；建表与迁移只允许在后台设置页、保存动作或显式迁移命令中执行。
- REST 公开接口只读，必须过滤未发布、私密、密码保护和未审核内容；匿名评论写入已移除。
- 管理接口必须同时满足管理员登录 + Typecho nonce + 同源；CORS 只允许显式白名单，禁止反射任意 `Origin`。
- 管理 nonce 只从 `X-Typecho-Token` 请求头读取，不接受 query string 传递，避免 token 进入日志与 Referer。
- CORS Origin 必须是 `http(s)://host[:port]`；拒绝 `null`、路径、CRLF 与通配符，避免响应头注入。
- 公开 REST Token 由 `REST_Token_Switch` / `rest.token_enabled`（环境变量 `TTDF_REST_TOKEN_ENABLED`）显式开关，默认关闭；关闭时不校验 Bearer Token，开启时必须从环境变量读取至少 32 字符的值并使用 `hash_equals`，不得授予管理员权限。
- JSON 请求体上限 1 MiB，超限返回 413；不要先读取完整请求体再判断大小。
- REST 路由必须在 `Framework::boot()` 中按请求同步，不依赖后台保存时的内存路由或 `__routes_registered` 标记；公开接口关闭时仍要为管理员注册管理路由。
- `REST_Token_Env` 缺失时回退到 `rest.token_env`；开关开启但环境变量名或值为空、或值少于 32 字符时必须返回 500，禁止静默放行。开关只影响 `/ttdf/v1` 公共只读接口，后台管理接口仍使用管理员登录 + nonce + 同源校验。
- 媒体库支持专用 `Media` 字段类型与 `Text + media` 字段能力两种声明：`type => 'Media'` 直接渲染媒体选择器；`type => 'Text'` 且 `media => true`（或 `media => ['enabled' => true]`）时保留文本框并在旁边提供媒体库按钮。两者都只保存 Typecho 图片附件 `cid` 的数字字符串。读取历史绝对 URL 仅作兼容，主题输出必须走 `TTDF\Theme\Media::url()`，不得自行拼接上传目录或读取附件路径。
- 媒体库接口只允许管理员登录 + Typecho nonce + 同源访问；只暴露 `type=attachment`、`status=publish` 的图片附件，不得返回其他附件类型。
- 媒体上传只接受 `$_FILES['file']`，单张上限 5 MiB，必须同时校验 `getimagesize()` MIME 与 JPG/PNG/GIF/WebP/BMP 扩展名；上传文件写入 Typecho 原生上传目录并建立附件记录。
- 媒体删除必须限定在 `usr/uploads/` 内并校验真实路径，禁止通过损坏的附件路径删除任意文件；重命名只允许修改附件 `title`，不得改文件路径或扩展名。
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
- 主题根 `functions.php` 在 Archive 初始化阶段从主题根加载；`themeInit()` 随后把 `themeDir` 指向 `app/pages/`，Typecho 才据此执行 `need()` 与 `render()`。REST 与自定义页面必须兼容 Typecho 的 404 handler，不要在启动阶段写数据库。`themeConfig()` / `themeFields()` / `themeInit()` 由核心注册，仍会在 Typecho 需要时调用。
- 自动路由同时支持 `[slug].php` 与 `[slug]/index.php`；动态目录必须先校验 `realpath` 边界。
- 示例主题的页面模板使用 `app/pages/index.php` / `archive.php` / `post.php` / `page.php`，评论由 `app/components/Comments.php` 渲染，数据读取走 Typecho 原生 Widget；模板内引用主题根资源时必须使用 `\TTDF\Framework::root()`，不要依赖 `__DIR__` 推断主题根。
- `PageTemplates` 选择模板时必须保持 Typecho 原生回退顺序：`{type}/{slug}.php` → `{type}.php` → attachment 的 `page.php` / `post.php` → `single.php` / `archive.php` → `index.php`；文件名只允许 `[A-Za-z0-9_/-]+.php`，拒绝 `..`、反斜杠、绝对路径和跨目录 `realpath`。

# 常见任务流程

## 修改框架核心

1. 先浏览 `https://blog.miomoe.cn/docs/skill/ttdf-dev.mdx`
2. 修改 `vendor/yuinijika/ttdf/core/` 或 `app/`，保持 PSR-4 与命名空间边界；核心改动必须同时更新线上 `ttdf-dev` Skill 的路径说明
3. 补对应单元测试，安全相关改动补回归用例
4. 运行 `composer lint`、`composer analyse`、`composer test`；修改设置页时另运行 `pnpm check:static` 与 `pnpm test:ui`
5. 公共 API 或行为变化时同步线上 `ttdf/v5/*` 文档

回归至少覆盖：公开 REST 内容过滤、评论字段最小化、附件父内容可见性、管理接口 401/403、CORS 畸形 Origin、1 MiB 请求体 413、设置主题隔离、日志脱敏与换行注入。

## 修改主题设置

1. 在 `app/config/theme.php` 增加字段，声明类型、默认值与说明
2. 在 `TTDF\Theme\Settings::validate()` 补类型转换与白名单校验
3. 如需迁移旧值，更新 `TTDF\Upgrade\Migrator`
4. 设置页 JS 只通过 `/ttdf/v1/admin/*` 读写，并携带 `X-Typecho-Token`
5. 不在前台请求中触发建表或迁移
6. 设置页“保存设置”下拉菜单的“执行迁移”调用 `POST /admin/migrate`，保持幂等
7. 新增或调整 Arco 字段控件时，同步更新 `static/options/options.js`、`options.css` 与 `tests/options/*.test.mjs`
8. 设置页依赖通过 `Vue` / `ArcoVue` 全局构建延迟解析，测试中不得依赖加载顺序副作用
9. `type => 'Media'` 或 `Text + media` 字段由设置页媒体库对话框通过 `/ttdf/v1/admin/media*` 选择附件；保存 `cid`，不要保存临时 URL；`Text + media` 必须同时保留可编辑文本框与媒体库按钮
10. 媒体库改动至少补主题输出、列表过滤、分页/搜索、上传校验、重命名与删除边界测试

## 新增 REST 接口

1. 公开接口只允许 GET，并在 `PublicController` 显式过滤 `publish` / `approved` / 密码保护
2. 管理接口放 `/ttdf/v1/admin/*`，必须管理员登录 + nonce
3. 不返回邮箱、IP、Token、日志或未审核数据
4. 补鉴权、CORS、分页和内容过滤测试
5. 媒体管理接口固定为 `GET/POST /admin/media`、`GET/POST/DELETE /admin/media/{id}`；上传是 `multipart/form-data`，其余写操作使用 JSON

## 新增模板槽位

1. 在 `TTDF\Theme\Templates::SLOTS` 增加槽位与中文标签
2. 在 `Settings::validate()` 的 `TemplateMap` 分支保持白名单校验
3. 在入口模板调用 `Templates::render()`，并保留内联回退
4. 归档槽位通过 `Templates::archiveSlot()` 解析，不要在模板里散落 `if/else` 组件名
5. 补 `TemplatesTest` 与设置页字段文档

## 新增或调整页面模板

1. Typecho 页面类型模板放 `app/pages/`，评论等可复用渲染单元放 `app/components/`，根目录只保留元信息 stub `index.php`
2. 默认入口命名为 `index.php`、`archive.php`、`post.php`、`page.php`、`404.php`；归档细粒度模板使用 `{type}/{slug}.php`
3. 模板内 `$this->need()` 与 `$this->render()` 由框架改写后的 `themeDir` 解析，不要手工拼主题根路径
4. 需要在模板中引用主题根资产或组件清单时使用 `\TTDF\Framework::root()`，不要通过 `__DIR__` 推导
5. 修改 `PageTemplates` 的回退顺序时同步更新 `PageTemplatesTest` 与线上 `ttdf/v5/structure`、`skill/typecho-theme-dev`
6. 评论表单与评论列表只通过 `Comments` 组件加载，不要恢复 `need('comments.php')`

## 文档更新

1. 文档只通过线上 Docs Agent 维护，API 根地址为 `https://api.miomoe.cn`；线上文档是唯一真源
2. 读取页面：`GET /site/docs/page?path=ttdf/v5/structure`；提交修订：`POST /agent/docs/revisions`（`X-API-Key`）
3. 新建页面使用文档系统的 create 流程；已有页面使用修订接口，不要用修订接口创建新页面
4. 修订提交后进入待审队列，取返回的 `uuid`，用 `POST /agent/token` 换取 JWT，再以 `Cookie: access_token=<jwt>` 调用 `POST /admin/site/docs/revisions/{uuid}/approve`
5. 分区目录与排序通过 `PUT /admin/site/docs/metas` 维护，同样需要 JWT Cookie
6. 文档路径：v5 文档 `ttdf/v5/*`，v4 归档 `ttdf/v4/*`，Skill `skill/ttdf-dev` 与 `skill/typecho-theme-dev`
7. 不在仓库内保存完整文档或 Skill 镜像；API Key 只从本机受控环境读取，禁止写入仓库、命令行历史或提交记录
8. `ttdf/v4/*` 只做归档修订，不新增功能文档；新内容写入 `ttdf/v5/*`
9. 修改页面模板目录、启动流程、公共 API 或安全边界时，必须同步更新受影响的 v5 文档与两个 Skill

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
