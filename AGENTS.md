# TTDF 项目规则

本项目包含两个概念，先区分场景再动手：

## 项目定位

- **ttdf-framework**：指本仓库 `D:\Codes\TTDF` 本身，负责框架核心、autoloader、配置、路由、Hook、REST、设置表、测试、文档与发布。
- **typecho-theme**：指使用 TTDF v5 构建的独立 Typecho 主题工程，例如包含自己的 `functions.php`、`app/pages/` 页面模板、`app/config/`、`app/router/`、`app/components/` 和资源的主题。

当前 `master` 是 v5 开发线，版本固定为 `5.0.1-next`；`v4` 分支已冻结，只用于旧主题与旧站点，不在 `master` 上恢复 v4 全局 API。

# 开发场景 Skill 读取

任务开始时，先只读浏览线上 Skill 与 v5 文档。文档站点是 SPA，不要用浏览器
页面或 `.mdx` 后缀抓取；统一通过 Docs Agent API 读取：

```text
GET https://api.miomoe.cn/site/docs/page?path=skill/ttdf-dev
GET https://api.miomoe.cn/site/docs/page?path=skill/typecho-theme-dev
GET https://api.miomoe.cn/site/docs/page?path=ttdf/v5/index
```

框架内部任务读 `skill/ttdf-dev` 与 `ttdf/v5/*`，主题开发任务读
`skill/typecho-theme-dev` 与 `ttdf/v5/*`。线上文档是唯一真源，不要在仓库内维护完整文档镜像，也不要依赖 `D:\Codes\TTDF-Docs` 的旧内容。

# 硬性约束

- 运行时要求 PHP 8.2+、Typecho 1.2.1+ / 1.3.x；所有 PHP 文件使用 `declare(strict_types=1)`。
- 框架代码使用 `TTDF\` 命名空间，PSR-4 映射为 `TTDF\` → `vendor/yuinijika/ttdf/core/`、`TTDF\App\` → `app/`。
- 框架核心托管在 `vendor/yuinijika/ttdf/core/`，属于只读依赖区。主题开发者只修改 `app/`、`assets/` 与根 `index.php` 元信息；升级框架时整体替换 `vendor/yuinijika/ttdf/`，不要手工改核心文件。
- 主题目录固定为：`functions.php` 唯一初始化 PHP 入口、根 `index.php` 仅作 Typecho 主题元信息 stub、`app/pages/` 仅 Typecho 页面类型模板、`app/config/app.php` 运行配置、`app/config/theme.php` 后台设置字段、`app/config/fields.php` 文章/页面字段、`app/router/` TTDF 自定义 URL 路由、`app/components/` 组件。不要恢复 `app/app.config.php`、`app/config/setup.php`、`app/setup.php`、`app/fields.php`。
- `app/pages/` 只接受 Typecho 页面类型文件（`index.php`、`{type}.php`、`{type}/{slug}.php`）；`header.php`、`footer.php`、`sidebar.php`、`comments.php` 不属于页面类型，一律作为组件放在 `app/components/`，约定名分别是 `AppHeader`、`AppFooter`、`Sidebar`、`Comments`。入口模板用 `Components::render($root, 'AppHeader', ['archive' => $this])` 加载，禁止 `$this->need('header.php')` 这类片段加载；`PageTemplates::isSafeFile()` 必须继续拒绝这些保留名，`PageTemplatesTest` 必须覆盖该拒绝行为。
- 根 `index.php` 是 Typecho 后台主题发现机制的硬性要求（`Widget\Themes\Rows` 固定读取主题根 `index.php` 的 docblock 元信息），只允许保留 `<?php`、严格类型声明、元信息注释和 `__TYPECHO_ROOT_DIR__` 守卫，不得写页面渲染逻辑。真实首页模板是 `app/pages/index.php`。
- 主题版本号的唯一来源是根 `index.php` 的 `@version` 元信息；`app/config/app.php` 不配置版本号，`Typecho::themeVersion()` 只读取主题根 `index.php` 的 docblock，不回退到设置表或配置数组。
- `Assets::url()` 与 `Assets::staticUrl()` 的 `?v=` 必须使用主题版本号，不得使用 `Framework::VERSION`；版本读不到时不追加查询参数，也不要为了缓存刷新去读配置或设置表。
- `Assets::url($path, $base = 'assets')` 与 `printStyles()` / `printScripts()` 默认补 `assets/` 前缀；路径已以该前缀开头时不得重复拼接，前缀只接受相对目录段，含 `..`、反斜杠、绝对路径或空值时按“无前缀”处理。
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
- 主题设置只能通过 `TTDF\Theme\Options` 读写；每个主题只写一行，`value` 是完整设置 JSON，字段必须来自 `app/config/theme.php` 白名单。默认落在自定义 `ttdf` 表，`name` 是主题名。
- Typecho `config.inc.php` 未定义 `__TTDF_DATABASE_TABLE__`，或定义为真时使用 `ttdf` 表；显式定义为 `false` 时改存 Typecho 原生 `typecho_options`，主记录名 `ttdf:{主题名}`、`user = 0`，备份名 `ttdf:{主题名}:backup_{Unix时间戳}`。原生模式不得建表、不得读写用户级 options，也不得覆盖 `theme`、`theme:{主题名}`、`siteUrl` 等 Typecho 自有选项。
- `typecho_options.name` 是 `varchar(32)`：主记录放不下时禁止保存并给出明确异常；备份名超出 32 字符时改用紧凑标记 `ttdf:{主题名}:b_{Unix时间戳}`，`backups()` 与 `restore()` 必须同时识别 `:backup_` 与 `:b_` 两种形式。
- `app/config/app.php` 只公开 `debug`、`rest` 与 `router`。`version`、`assets`、`settings` 不得写入主题的公开配置：版本在根 `index.php`，资源 CDN 在主题设置 `Assets_CDN`。
- `settings.table` 是框架内部的隐藏存储默认值，不定义设置项，也不要求在 `app/config/app.php` 出现；`TTDF_SETTINGS_TABLE` 环境变量仍可覆盖表名。设置不再使用 `settings.schema_version` 或 `__schema_version_{theme}` 行。
- `debug`、REST、CORS、Token 开关与 Token 环境变量通过 `TTDF\Theme\ThemeConfig` 合并静态默认值与主题设置；资源 CDN 只从主题设置 `Assets_CDN` 读取，静态 `assets.cdn` 已移除。
- 页面模板入口固定在 `app/pages/`，并使用 Typecho 官方模板名（`index.php`、`post.php`、`page.php`、`archive.php`、`single.php`、`404.php`）；默认只保留 `index.php`、`archive.php`、`post.php`、`page.php`、`404.php`。页面主体映射使用 `TemplateMap` 字段与 `TTDF\Theme\Templates`，槽位只能是 `post`、`page`、`index`、`archive`、`category`、`tag`、`search`、`author`、`date`、`404`。
- 评论渲染固定为 `app/components/Comments.php`，不得再创建 `app/pages/comments.php`；`post.php` / `page.php` 的内联回退与 `Post` / `Page` 组件都必须通过 `Components::render(..., 'Comments', ['archive' => $archive])` 加载。页头、页尾、侧栏同理固定在 `app/components/AppHeader.php`、`AppFooter.php`、`Sidebar.php`，入口模板只在最外层用 `Components::render()` 调用一次。
- 评论组件内不能访问 `$archive->user` / `$archive->options`（Typecho 受保护成员）；登录用户与站点配置分别使用 `Widget\User::alloc()` 与 `Widget\Options::alloc()`。
- `TemplateMap` 的组件值只允许 `[A-Za-z0-9_/-]`，不得包含 `..`；`Templates::render()` 必须通过 `Components::exists()` 校验后再 include，组件缺失时返回 `false` 并保留入口模板回退。
- `TemplateMap` 字段必须声明 `components` 白名单（如 `['Post', 'Page', 'Archive', 'NotFound']`），只列可独立渲染页面主体的组件；`PostMeta`、`Comments`、`AppHeader` 等片段不得进入白名单。`Settings::validate()` 与 `Templates::mapping()` 都必须按白名单过滤，运行时过滤用于兼容旧版本保存的非法映射。
- `404` 只能映射到 `NotFound` 或显式留空；不要映射到 `Post` / `PostMeta`，Typecho 404 Archive 的 `type` 为 null，`permalink()` 会触发 TypeError。
- `Templates::render()` 必须在独立输出缓冲中执行组件；组件抛错时丢弃已输出的半截 HTML。生产模式返回 `false` 让入口模板回退，调试模式重新抛出到全局错误页。
- `Diagnostics\ErrorHandler` 必须清空半截输出并渲染完整独立 HTML 错误页，不得只 echo `TTDF error:` 文本；状态码沿用 Typecho 已设置的 4xx/5xx，否则回退 500，生产模式隐藏异常消息与堆栈，调试模式显示并转义详情。
- 每次修改设置时整份 JSON 一次写入；覆盖已有 JSON 前必须自动备份为 `{主题名}:backup_{Unix时间戳}`，备份与主记录留在同一个存储。手动备份、备份列表与恢复只能由管理员接口触发，不得在普通前台请求中执行。
- `Options::all()` 只返回当前主题那一行 JSON；`Options::get()` 不读取无前缀旧键。迁移是单向的：只在当前主题还没有有效 JSON 文档时，把 `{主题名}_{字段名}` 与无前缀 v4 字段转换为首次 JSON；已有 JSON 必须直接返回 `already-current`，不得覆盖、不得改写或删除旧行、其他主题行与备份行。
- 字段改过名时在 `app/config/theme.php` 用 `aliases` 声明旧名字供迁移读取；取值优先级为 `{主题名}_{当前字段名}` > `{当前字段名}` > `{主题名}_{旧字段名}` > `{旧字段名}`，别名按当前字段类型归一化，且不得出现在设置表单、`Schema::fields()` / `names()` 或保存后的 JSON 中。
- 禁止在每次请求中建表、写入默认值或执行迁移；建表与迁移只允许在后台设置页、保存动作或显式迁移命令中执行。
- REST 公开接口只读，必须过滤未发布、私密、密码保护和未审核内容；匿名评论写入已移除。
- 管理接口必须同时满足管理员登录 + Typecho nonce + 同源；CORS 只允许显式白名单，禁止反射任意 `Origin`。
- 管理 nonce 只从 `X-Typecho-Token` 请求头读取，不接受 query string 传递，避免 token 进入日志与 Referer。
- CORS Origin 必须是 `http(s)://host[:port]`；拒绝 `null`、路径、CRLF 与通配符，避免响应头注入。
- 公开 REST Token 由 `REST_Token_Switch` / `rest.token_enabled`（环境变量 `TTDF_REST_TOKEN_ENABLED`）显式开关，默认关闭；关闭时不校验 Bearer Token，开启时必须从环境变量读取至少 32 字符的值并使用 `hash_equals`，不得授予管理员权限。
- JSON 请求体上限 1 MiB，超限返回 413；不要先读取完整请求体再判断大小。
- REST 路由必须在 `Framework::boot()` 中按请求同步，不依赖后台保存时的内存路由或 `__routes_registered` 标记；公开接口关闭时仍要为管理员注册管理路由。
- REST 扩展入口固定为 `TTDF\Http\Rest\Registry`，只能通过 `rest.routes` 钩子按请求注册；不得在核心 `Router` 里增加主题专属 `match` 分支，也不得在主题内维护全局路由数组。
- 自定义公开路由只允许 GET，仍受 REST 开关、公开 Token 与 CORS 约束；自定义管理路由必须位于 `admin/` 下，仍受管理员登录 + nonce + 同源校验。不得在扩展路由内绕过 `Auth`。
- `Registry` 路径只允许小写字母、数字、`-`、`_` 与 `{name}` 占位符；最多 8 段、180 字符；拒绝 `..`、反斜杠、空段、重复参数名、大写与查询字符串。相同 `method + path` 重复注册必须抛 `InvalidArgumentException`。
- `Registry` 必须拒绝占用内置公开前缀（`site`、`posts`、`pages`、`categories`、`tags`、`comments`、`attachments`、`search`）与内置管理前缀（`admin/options`、`admin/schema`、`admin/config`、`admin/media` 等）及其子路径，避免覆盖 `posts/{cid}`、`admin/media/{id}` 等内置路由。
- 自定义路由处理器只返回数组、`Result` 或 `null`；`Result::success()` / `Result::error()` / `Result::noContent()` 负责状态码与载荷，其他返回类型必须落到 500。
- REST 钩子固定为 `rest.request`、`rest.routes`、`rest.response`、`rest.exception`。`rest.routes` 每次 REST 请求都会执行，注册回调必须轻量，不得查询数据库、建表、迁移或发起外部请求。
- `rest.response` 是 filter，参数为 `array $payload, int $status, string $scope`，必须返回数组；框架在非数组或回调抛错时回退原载荷。过滤器不得注入邮箱、IP、Token、日志或未审核字段。
- `rest.exception` 只用于受控日志，不得回显异常消息、SQL、文件路径或 Token；生产响应必须继续只返回 `Internal server error`。
- `REST_Token_Env` 缺失时回退到 `rest.token_env`；开关开启但环境变量名或值为空、或值少于 32 字符时必须返回 500，禁止静默放行。开关只影响 `/ttdf/v1` 公共只读接口，后台管理接口仍使用管理员登录 + nonce + 同源校验。
- 自定义 AJAX 只能通过 `TTDF\Ajax\Endpoint` 注册与分发，入口固定为 `/ttdf/ajax`；动作名必须匹配 `^[a-z0-9][a-z0-9_-]{0,63}$`。
- AJAX 动作默认使用 `POST` + 管理员 + Typecho nonce + 同源校验；`auth => user` 只要求登录用户，`auth => public` 必须显式关闭 nonce 并确认动作无副作用、无敏感数据。
- AJAX 鉴权与输入解析分别固定在 `TTDF\Ajax\Guard` 与 `TTDF\Ajax\Payload`；不要在主题内自建入口、读取 `php://input` 或绕过 nonce。
- 运行时控制台脚本由 `TTDF\Diagnostics\RuntimeConsole` 在 `theme.footer` 统一输出，`window.frameWorkConfig` 只允许 `version` 与 `loadtime`；不得恢复 `TyAjax`、`RESTAPI` 或其他接口信息字段。
- 媒体库支持三种声明：`type => 'Media'` 直接渲染媒体选择器并保存 Typecho 图片附件 `cid` 数字字符串；`type => 'Text'` / `type => 'Textarea'` 且 `media => true`（或 `media => ['enabled' => true]`）时保留可编辑控件并在旁边提供媒体库按钮，保存的是图片地址而不是 `cid`。`Settings::mediaMode()` 返回 `reference`（`Media`）或 `url`（文本类字段），`Settings::isMediaField()` 保持 `mediaMode() !== null` 语义；`Media::normalizeUrl()` 只接受 `http(s)` 绝对 URL、`/` 开头的站点根路径与遗留 `cid`，`Media::normalizeUrlList()` 对 `Textarea` 逐行清洗并把非法行丢弃。前端选择图片后 `Text` 写入地址、`Textarea` 追加为新行；不得再手工拼接上传目录或读取附件路径。
- 媒体库接口只允许管理员登录 + Typecho nonce + 同源访问；只暴露 `type=attachment`、`status=publish` 的图片附件，不得返回其他附件类型。
- 媒体上传只接受 `$_FILES['file']`，单张上限 5 MiB，必须同时校验 `getimagesize()` MIME 与 JPG/PNG/GIF/WebP/BMP 扩展名；上传文件写入 Typecho 原生上传目录并建立附件记录。
- 媒体删除必须限定在 Typecho 上传目录内并校验真实路径；上传目录优先读取 `__TYPECHO_UPLOAD_DIR__`，否则读取 `Widget\Upload::UPLOAD_DIR`；上传根目录使用 `__TYPECHO_UPLOAD_ROOT_DIR__`（缺失时回退 `__TYPECHO_ROOT_DIR__`），上传 URL 使用 `__TYPECHO_UPLOAD_URL__`（缺失时回退站点地址）；只有检测不到 Typecho 常量时才使用默认 `usr/uploads/`，不得在业务代码中硬编码。禁止通过损坏的附件路径删除任意文件；重命名只允许修改附件 `title`，不得改文件路径或扩展名。
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
- v4 钩子名 `load_head`、`load_foot`、`header`、`footer`、`before_render`、`after_render` 由 `TTDF\Hooks\Hooks` 转发到对应 v5 Hook 并共享回调，同一事件只执行一次；`load_foot` 对应 `theme.footer`，不要恢复 `TTDF_Hook` 全局类。
- 主题 `app/components/AppHeader.php` / `AppFooter.php` 只加载自定义 assets，并分别触发 `theme.head` / `theme.footer`；不要直接调用 `$this->header()` / `$this->footer()`，也不要把它们放回 `app/pages/`。框架由 `TTDF\Theme\ArchiveLayout` 在 Hook 内调用 Typecho 原生 header/footer，`theme.after_main` 只用于 `</main>` 之后的附加逻辑。
- 主题根 `functions.php` 在 Archive 初始化阶段从主题根加载；`themeInit()` 随后把 `themeDir` 指向 `app/pages/`，Typecho 才据此执行 `need()` 与 `render()`。REST 与自定义页面必须兼容 Typecho 的 404 handler，不要在启动阶段写数据库。`themeConfig()` / `themeFields()` / `themeInit()` 由核心注册，仍会在 Typecho 需要时调用。
- 自动路由同时支持 `[slug].php` 与 `[slug]/index.php`；动态目录必须先校验 `realpath` 边界。
- 示例主题的页面模板使用 `app/pages/index.php` / `archive.php` / `post.php` / `page.php`，页头、页尾、侧栏与评论分别由 `app/components/AppHeader.php` / `AppFooter.php` / `Sidebar.php` / `Comments.php` 渲染，数据读取走 Typecho 原生 Widget；组件内只能用 `$viewData['archive']`，不能假设 `$this` 存在，模板内引用主题根资源时必须使用 `\TTDF\Framework::root()`，不要依赖 `__DIR__` 推断主题根。
- `PageTemplates` 选择模板时必须保持 Typecho 原生回退顺序：`{type}/{slug}.php` → `{type}.php` → attachment 的 `page.php` / `post.php` → `single.php` / `archive.php` → `index.php`；文件名只允许 `[A-Za-z0-9_/-]+.php`，拒绝 `..`、反斜杠、绝对路径和跨目录 `realpath`，并拒绝 `header.php` / `footer.php` / `comments.php` / `sidebar.php` 保留名。

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
3. 如需迁移旧值，更新 `TTDF\Upgrade\Migrator`；字段改过名时在字段声明 `aliases`，不要改回字段名
4. 设置页 JS 只通过 `/ttdf/v1/admin/*` 读写，并携带 `X-Typecho-Token`
5. 不在前台请求中触发建表或迁移
6. 设置页“更多操作”的“立即备份”“恢复备份”分别调用 `POST /admin/backup`、`GET /admin/backups` 与 `POST /admin/restore`；恢复后重新加载表单，“执行迁移”调用 `POST /admin/migrate`，旧格式才写入首次 JSON，已有 JSON 返回 `already-current`
7. 新增或调整 Arco 字段控件时，同步更新 `static/options/options.js`、`options.css` 与 `tests/options/*.test.mjs`
8. 设置页依赖通过 `Vue` / `ArcoVue` 全局构建延迟解析，测试中不得依赖加载顺序副作用
9. `type => 'Media'`、`Text + media` 与 `Textarea + media` 字段由设置页媒体库对话框通过 `/ttdf/v1/admin/media*` 选择附件；`Media` 保存 `cid`，文本类字段保存图片地址（`Textarea` 每行一个，追加不覆盖），都不要保存临时 URL；`Text + media` / `Textarea + media` 必须保留可编辑控件与媒体库按钮
10. 媒体库改动至少补主题输出、列表过滤、分页/搜索、上传校验、重命名与删除边界测试

## 新增 REST 接口

1. 主题专属接口优先通过 `rest.routes` + `Registry` 实现，不修改 `core/`；只有框架级通用能力才改 `PublicController` / `AdminController`
2. 公开接口只允许 GET，并在处理器或 `PublicController` 显式过滤 `publish` / `approved` / 密码保护
3. 管理接口放 `/ttdf/v1/admin/*`，必须管理员登录 + nonce + 同源
4. 不返回邮箱、IP、Token、日志或未审核数据
5. 补鉴权、CORS、分页、内容过滤与非法/重复/保留路径测试；扩展路由至少覆盖公开关闭 404、管理 401/403、`rest.response` scope 与 `rest.exception`
6. 媒体管理接口固定为 `GET/POST /admin/media`、`GET/POST/DELETE /admin/media/{id}`；上传是 `multipart/form-data`，其余写操作使用 JSON

## 新增 AJAX 动作

1. 在 `framework.boot` 钩子内调用 `TTDF\Ajax\Endpoint::register()`，动作名使用小写字母、数字、下划线与短横线
2. 默认保持 `POST` + 管理员 + nonce + 同源；确需登录用户或公开动作时显式声明 `auth`，公开动作同时设置 `nonce => false`
3. 前端请求地址使用 `Endpoint::url($action)`，写操作从 `X-Typecho-Token` 请求头携带 Typecho nonce
4. 处理器只返回数组，响应由 `TTDF\Http\Response` 统一封装；不要自行输出 JSON 或 `exit`
5. 补 `AjaxTest` 覆盖动作名校验、鉴权、nonce、方法限制、JSON 请求体与 `index.php/` 路径

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
6. 评论表单与评论列表只通过 `Comments` 组件加载，页头、页尾、侧栏只通过 `AppHeader` / `AppFooter` / `Sidebar` 组件加载，不要恢复 `need('comments.php')`、`need('header.php')`、`need('footer.php')`、`need('sidebar.php')`

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

- 版本号：`5.0.1-next`
- 发布标签：`v5.0.1-next`
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
