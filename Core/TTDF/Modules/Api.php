<?php

declare(strict_types=1);

/**
 * TTDF REST API
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// -----------------------------------------------------------------------------
// 配置与常量定义
// -----------------------------------------------------------------------------
// 检查REST API是否启用
$restApiEnabled = true; // 默认值

// 检查主题设置项
$restApiSwitch = Get::Options(TTDF_CONFIG['REST_API']['OVERRIDE_SETTING'] ?? 'TTDF_RESTAPI_Switch');
if ($restApiSwitch === 'false') {
    $restApiEnabled = false;
}
// 如果没有设置项，则使用常量配置
elseif (!isset($restApiSwitch)) {
    $restApiEnabled = TTDF_CONFIG['REST_API']['ENABLED'] ?? false;
}

// 最终检查
if (!$restApiEnabled) {
    // 确保 Router::$current 是一个空字符串而不是 null
    if (!isset(Typecho\Router::$current)) {
        Typecho\Router::$current = '';
    }
    return;
}

// 使用 Enum 定义常量，增强类型安全
enum HttpCode: int
{
    case OK = 200;
    case BAD_REQUEST = 400;
    case UNAUTHORIZED = 401;
    case FORBIDDEN = 403;
    case NOT_FOUND = 404;
    case METHOD_NOT_ALLOWED = 405;
    case INTERNAL_ERROR = 500;
}

enum ContentFormat: string
{
    case HTML = 'html';
    case MARKDOWN = 'markdown';
}
// -----------------------------------------------------------------------------
// 辅助类 （分离功能)
// -----------------------------------------------------------------------------

/**
 * 封装 HTTP 请求信息，与超全局变量解耦。
 */
readonly class ApiRequest
{
    public string $path;
    public array $pathParts;
    public ContentFormat $contentFormat;
    public int $pageSize;
    public int $currentPage;
    public int $excerptLength;

    /**
     * 构造函数，初始化API请求参数
     * 
     * - 解析请求URI并提取API路径
     * - 设置默认内容格式(默认为HTML)
     * - 初始化分页参数(pageSize范围1-100，默认10)
     * - 初始化当前页码(最小为1)
     * - 设置摘要长度(最小为0，默认200)
     */
    public function __construct()
    {
        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
        $basePath = '/' . ltrim(__TTDF_RESTAPI_ROUTE__ ?? '', '/');

        $this->path = str_starts_with($requestUri, $basePath)
            ? (substr($requestUri, strlen($basePath)) ?: '/')
            : '/';

        $this->pathParts = explode('/', trim($this->path, '/'));

        $this->contentFormat = ContentFormat::tryFrom(strtolower($this->getQuery('format', 'html'))) ?? ContentFormat::HTML;

        $this->pageSize = max(1, min((int)$this->getQuery('pageSize', 10), 100));

        $this->currentPage = max(1, (int)$this->getQuery('page', 1));

        $this->excerptLength = max(0, (int)$this->getQuery('excerptLength', 200));
    }

    /**
     * 获取指定键的GET参数值
     * 
     * @param string $key 要获取的参数键名
     * @param mixed $default 当键不存在时返回的默认值
     * @return mixed 返回对应的参数值或默认值
     * @note 未来可扩展为从请求体或头中获取数据
     */
    public function getQuery(string $key, mixed $default = null): mixed
    {
        // 未来可以扩展为从请求体或头中获取数据
        return $_GET[$key] ?? $default;
    }
}

/**
 * 专门负责发送 JSON 响应。
 */
final class ApiResponse
{
    public function __construct(private ContentFormat $contentFormat) {}

    /**
     * 发送JSON格式的API响应
     * 
     * 该方法会设置HTTP状态码、安全头信息，并输出标准化的JSON响应数据。
     * 响应数据包含状态码、消息、数据主体和元信息。
     * 在调试模式下会输出格式化的JSON。
     * 
     * @param array $data 响应数据数组，可包含'message'、'data'和'meta'字段
     * @param HttpCode $code HTTP状态码枚举，默认为200 OK
     * @return never 方法执行后会直接退出程序
     */
    public function send(array $data = [], HttpCode $code = HttpCode::OK): never
    {
        if (!headers_sent()) {
            http_response_code($code->value);
            header('Content-Type: application/json; charset=UTF-8');
            $this->setSecurityHeaders();
        }

        $response = [
            'code' => $code->value,
            'message' => $code === HttpCode::OK ? 'success' : ($data['message'] ?? 'Error'),
            'data' => $data['data'] ?? null,
            'meta' => [
                'format' => $this->contentFormat->value,
                'timestamp' => time(),
                ...($data['meta'] ?? [])
            ]
        ];

        $options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;
        if (defined('__DEBUG__') && __DEBUG__) {
            $options |= JSON_PRETTY_PRINT;
        }

        echo json_encode($response, $options);
        exit;
    }

    /**
     * 发送错误响应
     * 
     * @param string $message 错误消息
     * @param HttpCode $code HTTP状态码
     * @param Throwable|null $e 可选的异常对象，调试模式下会包含错误详情
     * @return never 终止程序执行
     * 
     * @throws void 此方法不会抛出异常
     */
    public function error(string $message, HttpCode $code, ?Throwable $e = null): never
    {
        $response = ['message' => $message];
        if ($e !== null && (defined('__DEBUG__') && __DEBUG__)) {
            $response['error_details'] = [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ];
        }
        $this->send($response, $code);
    }

    /**
     * 设置安全相关的HTTP响应头
     * 
     * 该方法用于设置一系列安全相关的HTTP头信息，包括缓存控制、CSP、HSTS等安全策略。
     * 
     * @return void
     */
    private function setSecurityHeaders(): void
    {
        $headers = [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains; preload',
            'Content-Security-Policy' => "default-src 'self'; object-src 'none';",
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'no-referrer-when-downgrade',
            'Permissions-Policy' => 'interest-cohort=()',
            'Access-Control-Allow-Origin' => '*', // 生产环境建议收紧，是否安全？
            'Vary' => 'Origin',
        ];
        foreach ($headers as $name => $value) {
            if (!headers_sent()) {
                header("$name: $value");
            }
        }
    }
}

/**
 * 专门负责格式化数据。
 */
final class ApiFormatter
{
    /**
     * Api 类的构造函数
     * 
     * @param DB_API $dbApi 数据库API实例
     * @param ContentFormat $contentFormat 内容格式化实例
     * @param int $excerptLength 摘要长度
     */
    public function __construct(
        private readonly DB_API $dbApi,
        private readonly ContentFormat $contentFormat,
        private readonly int $excerptLength
    ) {}

    /**
     * 格式化文章数据为统一格式
     * 
     * 将原始文章数据数组转换为标准化的格式，包含文章基础信息、分类、标签等内容。
     * 如果是文章类型(type=post)，还会自动添加分类和标签信息。
     * 
     * @param array $post 原始文章数据数组
     * @return array 格式化后的文章数据数组，包含：
     *     - cid: 文章ID
     *     - title: 标题
     *     - slug: 别名
     *     - type: 类型(post/page等)
     *     - created: 创建时间(ISO8601格式)
     *     - modified: 修改时间(ISO8601格式)
     *     - commentsNum: 评论数
     *     - authorId: 作者ID
     *     - status: 状态(publish/draft等)
     *     - contentType: 内容格式
     *     - fields: 自定义字段
     *     - content: 格式化后的内容
     *     - excerpt: 纯文本摘要
     *     - categories: 分类列表(仅type=post时)
     *     - tags: 标签列表(仅type=post时)
     */
    public function formatPost(array $post): array
    {
        $formattedPost = [
            'cid' => (int)($post['cid'] ?? 0),
            'title' => $post['title'] ?? '',
            'slug' => $post['slug'] ?? '',
            'type' => $post['type'] ?? 'post',
            'created' => date('c', $post['created'] ?? time()),
            'modified' => date('c', $post['modified'] ?? time()),
            'commentsNum' => (int)($post['commentsNum'] ?? 0),
            'authorId' => (int)($post['authorId'] ?? 0),
            'status' => $post['status'] ?? 'publish',
            'contentType' => $this->contentFormat->value,
            'fields' => $this->dbApi->getPostFields($post['cid'] ?? 0),
            'content' => $this->formatContent($post['text'] ?? ''),
            'excerpt' => $this->generatePlainExcerpt($post['text'] ?? '', $this->excerptLength),
        ];

        if ($formattedPost['type'] === 'post') {
            $formattedPost['categories'] = array_map(
                [$this, 'formatCategory'],
                $this->dbApi->getPostCategories($post['cid'] ?? 0)
            );
            $formattedPost['tags'] = array_map(
                [$this, 'formatTag'],
                $this->dbApi->getPostTags($post['cid'] ?? 0)
            );
        }
        return $formattedPost;
    }

    /**
     * 格式化分类数据
     * 
     * 对分类的描述内容进行格式化处理
     * 
     * @param array $category 包含分类数据的数组
     * @return array 返回处理后的分类数组
     */
    public function formatCategory(array $category): array
    {
        $category['description'] = $this->formatContent($category['description'] ?? '');
        return $category;
    }

    /**
     * 格式化标签数组
     * 
     * 对标签的描述内容进行格式化处理，并返回处理后的完整标签数组
     * 
     * @param array $tag 包含标签信息的数组
     * @return array 格式化后的标签数组
     */
    public function formatTag(array $tag): array
    {
        $tag['description'] = $this->formatContent($tag['description'] ?? '');
        return $tag;
    }

    /**
     * 格式化评论数据为统一结构
     * 
     * 将原始评论数组转换为标准化的关联数组，确保所有字段都有默认值。
     * 处理字段包括评论ID、文章ID、作者信息、时间戳转换、内容格式化等。
     * 
     * @param array $comment 原始评论数据数组
     * @return array 格式化后的评论数据，包含以下字段：
     *               - coid: 评论ID(int)
     *               - cid: 关联文章ID(int)
     *               - author: 作者名称(string)
     *               - mail: 作者邮箱(string)
     *               - url: 作者网址(string)
     *               - ip: 评论IP(string)
     *               - created: ISO格式创建时间(string)
     *               - modified: ISO格式修改时间(string)
     *               - text: 格式化后的评论内容(string)
     *               - status: 评论状态(默认'approved')
     *               - parent: 父评论ID(int)
     *               - authorId: 作者用户ID(int)
     */
    public function formatComment(array $comment): array
    {
        return [
            'coid' => (int)($comment['coid'] ?? 0),
            'cid' => (int)($comment['cid'] ?? 0),
            'author' => $comment['author'] ?? '',
            'mail' => $comment['mail'] ?? '',
            'url' => $comment['url'] ?? '',
            'ip' => $comment['ip'] ?? '',
            'created' => date('c', $comment['created'] ?? time()),
            'modified' => date('c', $comment['modified'] ?? time()),
            'text' => $this->formatContent($comment['text'] ?? ''),
            'status' => $comment['status'] ?? 'approved',
            'parent' => (int)($comment['parent'] ?? 0),
            'authorId' => (int)($comment['authorId'] ?? 0)
        ];
    }

    /**
     * 格式化附件数据为统一格式
     * 
     * 将原始附件数组转换为包含标准化字段的数组，确保各字段类型一致
     * 
     * @param array $attachment 原始附件数据数组
     * @return array 格式化后的附件数据，包含以下字段：
     *     - cid: int 附件ID
     *     - title: string 附件标题
     *     - type: string 附件类型
     *     - size: int 附件大小
     *     - created: string ISO8601格式创建时间
     *     - modified: string ISO8601格式修改时间
     *     - status: string 附件状态(默认'publish')
     */
    public function formatAttachment(array $attachment): array
    {
        return [
            'cid' => (int)($attachment['cid'] ?? 0),
            'title' => $attachment['title'] ?? '',
            'type' => $attachment['type'] ?? '',
            'size' => (int)($attachment['size'] ?? 0),
            'created' => date('c', $attachment['created'] ?? time()),
            'modified' => date('c', $attachment['modified'] ?? time()),
            'status' => $attachment['status'] ?? 'publish',
        ];
    }

    /**
     * 格式化内容为指定格式
     * 
     * 根据设置的contentFormat决定是否进行Markdown转换
     * 若格式为MARKDOWN则直接返回原内容
     * 否则会先移除HTML注释，再通过Markdown类进行转换
     * 
     * @param string $content 待格式化的原始内容
     * @return string 格式化后的内容
     */
    private function formatContent(string $content): string
    {
        if ($this->contentFormat === ContentFormat::MARKDOWN) {
            return $content;
        }
        if (!class_exists('Markdown')) {
            require_once __TYPECHO_ROOT_DIR__ . '/var/Typecho/Common/Markdown.php';
        }
        return Markdown::convert(preg_replace('/<!--.*?-->/s', '', $content));
    }

    /**
     * 生成纯文本摘要
     * 
     * 从内容中移除HTML和Markdown标记后截取指定长度的纯文本摘要
     * 
     * @param string $content 原始内容（可能包含HTML/Markdown）
     * @param int $length 摘要长度（小于等于0时返回空字符串）
     * @return string 处理后的纯文本摘要
     * @private 该方法是类内部实现细节
     */
    private function generatePlainExcerpt(string $content, int $length): string
    {
        if ($length <= 0) {
            return '';
        }
        // 移除HTML和Markdown
        $text = strip_tags($content);
        $text = preg_replace(['/```.*?```/s', '/~~~.*?~~~/s', '/`.*?`/', '/!\[.*?\]\(.*?\)/', '/\[.*?\]\(.*?\)/', '/^#{1,6}\s*/m', '/[\*\_]{1,3}/', '/^\s*>\s*/m', '/\s+/'], ' ', $text);
        $text = trim($text);

        if (mb_strlen($text) > $length) {
            $text = mb_substr($text, 0, $length);
            // 避免截断在单词中间
            if (preg_match('/^(.*)\s\S*$/u', $text, $matches)) {
                $text = $matches[1];
            }
        }
        return $text;
    }
}


// -----------------------------------------------------------------------------
// 核心 API 类 (只负责路由和协调)
// -----------------------------------------------------------------------------
final class TTDF_API
{
    /**
     * Api 构造函数
     * 
     * @param ApiRequest $request API请求对象
     * @param ApiResponse $response API响应对象
     * @param DB_API $db 数据库操作对象
     * @param ApiFormatter $formatter API数据格式化对象
     */
    public function __construct(
        private readonly ApiRequest $request,
        private readonly ApiResponse $response,
        private readonly DB_API $db,
        private readonly ApiFormatter $formatter
    ) {}

    /**
     * 处理API请求的主方法
     * 
     * 该方法负责处理所有传入的API请求，根据请求路径分发到不同的处理函数，
     * 并返回相应的数据或错误响应。
     * 
     * 请求方法限制为GET，其他方法将返回405错误。
     * 支持的端点包括：posts, pages, content, category等，
     * 未匹配的端点将返回404错误。
     * 
     * 内部异常将被捕获并返回500错误响应。
     * 
     * @return never 此方法不会返回，总是通过response对象输出结果
     */
    public function handleRequest(): never
    {
        try {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
                $this->response->error('Method Not Allowed', HttpCode::METHOD_NOT_ALLOWED);
            }

            $endpoint = $this->request->pathParts[0] ?? '';

            $data = match ($endpoint) {
                '' => $this->handleIndex(),
                'posts' => $this->handlePostList(),
                'pages' => $this->handlePageList(),
                'content' => $this->handlePostContent(),
                'category' => $this->handleCategory(),
                'tag' => $this->handleTag(),
                'search' => $this->handleSearch(),
                'options' => $this->handleOptions(),
                'themeOptions' => $this->handleThemeOptions(),
                'fields' => $this->handleFieldSearch(),
                'advancedFields' => $this->handleAdvancedFieldSearch(),
                'comments' => $this->handleComments(),
                'attachments' => $this->handleAttachmentList(),
                default => $this->response->error('Endpoint not found', HttpCode::NOT_FOUND),
            };

            $this->response->send($data);
        } catch (Throwable $e) {
            $this->response->error('Internal Server Error', HttpCode::INTERNAL_ERROR, $e);
        }
    }

    /**
     * 处理首页API请求，返回站点基本信息和版本信息
     * 
     * @return array 包含以下结构的数组：
     * - 'data' => [
     *     'site' => [
     *         'theme' => string 当前主题名称,
     *         'title' => string 站点标题,
     *         'description' => string 格式化后的站点描述,
     *         'keywords' => string 站点关键词,
     *         'siteUrl' => string 站点URL,
     *         'timezone' => string 时区设置,
     *         'lang' => string 语言设置(默认zh-CN)
     *     ],
     *     'version' => [
     *         'typecho' => string Typecho版本,
     *         'framework' => string 框架版本,
     *         'php' => string PHP版本,
     *         'theme' => string 主题版本
     *     ]
     * ]
     */
    private function handleIndex(): array
    {
        return ['data' => [
            'site' => [
                'theme' => Get::Options('theme'),
                'title' => Get::Options('title'),
                'description' => $this->formatter->formatCategory(['description' => Get::Options('description')])['description'],
                'keywords' => Get::Options('keywords'),
                'siteUrl' => Get::Options('siteUrl'),
                'timezone' => Get::Options('timezone'),
                'lang' => Get::Options('lang', false) ?: 'zh-CN',
            ],
            'version' => [
                'typecho' => TTDF::TypechoVer(false),
                'framework' => TTDF::Ver(false),
                'php' => TTDF::PHPVer(false),
                'theme' => GetTheme::Ver(false),
            ],
        ]];
    }

    /**
     * 处理文章列表数据
     * 
     * 从数据库获取文章列表并进行格式化，同时构建分页信息
     * 
     * @return array 返回包含文章数据和分页信息的数组
     * @return array['data'] 格式化后的文章列表数据
     * @return array['meta']['pagination'] 分页信息
     */
    private function handlePostList(): array
    {
        $posts = $this->db->getPostList($this->request->pageSize, $this->request->currentPage);
        $total = $this->db->getTotalPosts();
        return [
            'data' => array_map([$this->formatter, 'formatPost'], $posts),
            'meta' => ['pagination' => $this->buildPagination($total)]
        ];
    }

    /**
     * 处理页面列表数据
     * 
     * 从数据库获取分页页面数据并格式化返回
     * 
     * @return array 包含格式化后的页面数据和分页元信息
     *               - data: 格式化后的页面数据数组
     *               - meta: 分页元信息，包含分页结构
     */
    private function handlePageList(): array
    {
        $pages = $this->db->getAllPages($this->request->pageSize, $this->request->currentPage);
        $total = $this->db->getTotalPages();
        return [
            'data' => array_map([$this->formatter, 'formatPost'], $pages),
            'meta' => ['pagination' => $this->buildPagination($total)]
        ];
    }

    /**
     * 处理文章内容请求
     * 
     * 根据请求路径中的标识符（ID或别名）获取文章详情数据并格式化返回
     * 
     * @return array 包含格式化后文章数据的数组，结构为 ['data' => formattedPost]
     * @throws ResponseException 当缺少标识符或文章不存在时抛出HTTP错误响应
     */
    private function handlePostContent(): array
    {
        $identifier = $this->request->pathParts[1] ?? null;
        if ($identifier === null) {
            $this->response->error('Missing post identifier', HttpCode::BAD_REQUEST);
        }

        $post = is_numeric($identifier)
            ? $this->db->getPostDetail($identifier)
            : $this->db->getPostDetailBySlug($identifier);

        if (!$post) {
            $this->response->error('Post not found', HttpCode::NOT_FOUND);
        }

        return ['data' => $this->formatter->formatPost($post)];
    }

    /**
     * 处理分类相关请求
     * 
     * 根据请求路径参数返回分类数据：
     * - 无参数时返回全部分类列表
     * - 有参数时返回指定分类详情及关联文章
     * 
     * @return array 返回数据结构包含：
     *               - data: 格式化后的分类数据/文章数据
     *               - meta: 分页信息或总数统计
     * @throws Error 当指定分类不存在时返回404错误
     */
    private function handleCategory(): array
    {
        $identifier = $this->request->pathParts[1] ?? null;
        if ($identifier === null) {
            $categories = $this->db->getAllCategories();
            return [
                'data' => array_map([$this->formatter, 'formatCategory'], $categories),
                'meta' => ['total' => count($categories)]
            ];
        }

        $category = is_numeric($identifier) ? $this->db->getCategoryByMid($identifier) : $this->db->getCategoryBySlug($identifier);
        if (!$category) $this->response->error('Category not found', HttpCode::NOT_FOUND);

        $posts = $this->db->getPostsInCategory($category['mid'], $this->request->pageSize, $this->request->currentPage);
        $total = $this->db->getTotalPostsInCategory($category['mid']);

        return [
            'data' => [
                'category' => $this->formatter->formatCategory($category),
                'posts' => array_map([$this->formatter, 'formatPost'], $posts),
            ],
            'meta' => ['pagination' => $this->buildPagination($total)]
        ];
    }

    /**
     * 处理标签相关请求
     * 
     * 根据请求路径中的标识符返回标签数据：
     * - 当无标识符时，返回所有标签列表
     * - 当有标识符时（数字ID或slug），返回指定标签及其关联文章
     * 
     * @return array 返回包含标签数据的数组，结构为：
     *               - data: 格式化后的标签/文章数据
     *               - meta: 分页元信息或总数统计
     * @throws \Exception 当指定标签不存在时抛出404错误
     */
    private function handleTag(): array
    {
        $identifier = $this->request->pathParts[1] ?? null;
        if ($identifier === null) {
            $tags = $this->db->getAllTags();
            return [
                'data' => array_map([$this->formatter, 'formatTag'], $tags),
                'meta' => ['total' => count($tags)]
            ];
        }

        $tag = is_numeric($identifier) ? $this->db->getTagByMid($identifier) : $this->db->getTagBySlug($identifier);
        if (!$tag) $this->response->error('Tag not found', HttpCode::NOT_FOUND);

        /**
         * 处理搜索请求
         * 
         * 从请求路径中获取搜索关键词，验证后查询匹配的文章列表及总数
         * 返回格式化后的文章数据和分页信息
         * 
         * @return array 包含搜索结果和分页信息的数组
         * @throws \Exception 当缺少搜索关键词时抛出400错误
         */
        $posts = $this->db->getPostsInTag($tag['mid'], $this->request->pageSize, $this->request->currentPage);
        $total = $this->db->getTotalPostsInTag($tag['mid']);

        return [
            'data' => [
                'tag' => $this->formatter->formatTag($tag),
                'posts' => array_map([$this->formatter, 'formatPost'], $posts),
            ],
            'meta' => ['pagination' => $this->buildPagination($total)]
        ];
    }

    /**
     * 处理搜索请求
     * 
     * 从请求路径中获取关键词，搜索匹配的文章并返回格式化结果
     * 
     * @return array 包含搜索结果和分页信息的数组
     * @throws \Exception 当缺少搜索关键词时抛出400错误
     */
    private function handleSearch(): array
    {
        $keyword = $this->request->pathParts[1] ?? null;
        if (empty($keyword)) {
            $this->response->error('Missing search keyword', HttpCode::BAD_REQUEST);
        }

        $decodedKeyword = urldecode($keyword);
        $posts = $this->db->searchPosts($decodedKeyword, $this->request->pageSize, $this->request->currentPage);
        $total = $this->db->getSearchPostsCount($decodedKeyword);

        return [
            'data' => [
                'keyword' => $decodedKeyword,
                'posts' => array_map([$this->formatter, 'formatPost'], $posts),
            ],
            'meta' => ['pagination' => $this->buildPagination($total)]
        ];
    }

    /**
     * 处理选项请求
     * 
     * 根据请求路径获取单个选项值或返回允许的公共选项列表
     * 
     * @return array 返回格式:
     * - 当未指定选项名时: ['data' => [选项名 => 值,...]]
     * - 当指定选项名时: ['data' => ['name' => 选项名, 'value' => 值]]
     * @throws Exception 当指定选项不存在时返回404错误
     */
    private function handleOptions(): array
    {
        $optionName = $this->request->pathParts[1] ?? null;
        if ($optionName === null) {
            $allowedOptions = ['title', 'description', 'keywords', 'theme', 'plugins', 'timezone', 'lang', 'charset', 'contentType', 'siteUrl', 'rootUrl', 'rewrite', 'generator', 'feedUrl', 'searchUrl'];
            $allOptions = Helper::options();
            $publicOptions = [];
            foreach ($allowedOptions as $option) {
                if (isset($allOptions->$option)) {
                    $publicOptions[$option] = $allOptions->$option;
                }
            }
            return ['data' => $publicOptions];
        }

        $optionValue = Get::Options($optionName);
        if ($optionValue === null) {
            $this->response->error('Option not found', HttpCode::NOT_FOUND);
        }
        return ['data' => ['name' => $optionName, 'value' => $optionValue]];
    }

    /**
     * 处理主题选项数据
     * 
     * 从数据库获取当前主题的选项数据，并根据请求路径返回特定选项或全部选项
     * 
     * @return array 返回包含主题选项的数组，格式为 ['data' => 选项数据]
     *               - 当请求指定选项时，返回格式为 ['data' => ['name' => 选项名, 'value' => 选项值]]
     *               - 当选项不存在时，会抛出404错误
     * 
     * @throws Typecho_Exception 当请求的选项不存在时抛出404错误
     */
    private function handleThemeOptions(): array
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $themeName = GetTheme::Name(false);

        $row = $db->fetchRow($db->select('value')->from($prefix . 'options')->where('name = ?', 'theme:' . $themeName)->limit(1));
        $themeOptions = ($row && isset($row['value'])) ? (@unserialize($row['value']) ?: []) : [];
        if (!is_array($themeOptions)) $themeOptions = [];

        $optionName = $this->request->pathParts[1] ?? null;
        if ($optionName === null) {
            return ['data' => $themeOptions];
        }

        if (!isset($themeOptions[$optionName])) {
            $this->response->error('Theme option not found', HttpCode::NOT_FOUND);
        }
        return ['data' => ['name' => $optionName, 'value' => $themeOptions[$optionName]]];
    }

    /**
     * 处理字段搜索请求
     * 
     * 根据请求路径中的字段名和字段值进行文章搜索，返回格式化后的搜索结果和分页信息
     * 
     * @return array 包含搜索结果和分页信息的数组，结构为:
     *     [
     *         'data' => [
     *             'conditions' => ['name' => 字段名, 'value' => 解码后的字段值],
     *             'posts' => 格式化后的文章列表
     *         ],
     *         'meta' => ['pagination' => 分页信息]
     *     ]
     * @throws \Exception 当缺少字段参数时抛出400错误
     */
    private function handleFieldSearch(): array
    {
        $fieldName = $this->request->pathParts[1] ?? null;
        $fieldValue = $this->request->pathParts[2] ?? null;

        if ($fieldName === null || $fieldValue === null) {
            $this->response->error('Missing field parameters', HttpCode::BAD_REQUEST);
        }

        $decodedValue = urldecode($fieldValue);
        $posts = $this->db->getPostsByField($fieldName, $decodedValue, $this->request->pageSize, $this->request->currentPage);
        $total = $this->db->getPostsCountByField($fieldName, $decodedValue);

        return [
            'data' => [
                'conditions' => ['name' => $fieldName, 'value' => $decodedValue],
                'posts' => array_map([$this->formatter, 'formatPost'], $posts),
            ],
            'meta' => ['pagination' => $this->buildPagination($total)]
        ];
    }

    /**
     * 处理高级字段搜索请求
     * 
     * 该方法用于解析并验证请求中的高级搜索条件，然后从数据库获取匹配的文章数据
     * 
     * @return array 返回包含搜索结果和分页信息的数组，结构为:
     *               [
     *                   'data' => [
     *                       'conditions' => 解码后的搜索条件,
     *                       'posts' => 格式化后的文章数据
     *                   ],
     *                   'meta' => ['pagination' => 分页信息]
     *               ]
     * @throws JsonException 当JSON解析失败时抛出
     */
    private function handleAdvancedFieldSearch(): array
    {
        $conditions = $this->request->getQuery('conditions');
        if (empty($conditions)) {
            $this->response->error('Invalid search conditions', HttpCode::BAD_REQUEST);
        }
        try {
            $decodedConditions = json_decode($conditions, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->response->error('Invalid JSON in conditions parameter', HttpCode::BAD_REQUEST);
        }

        $posts = $this->db->getPostsByAdvancedFields($decodedConditions, $this->request->pageSize, $this->request->currentPage);
        $total = $this->db->getPostsCountByAdvancedFields($decodedConditions);

        return [
            'data' => [
                'conditions' => $decodedConditions,
                'posts' => array_map([$this->formatter, 'formatPost'], $posts),
            ],
            'meta' => ['pagination' => $this->buildPagination($total)]
        ];
    }

    /**
     * 处理评论请求
     * 
     * 根据请求路径参数获取特定文章评论或全部评论，并返回格式化后的评论数据及分页信息
     * 
     * @return array 包含格式化评论数据和分页信息的数组，结构为:
     *               [
     *                   'data' => 格式化后的评论数组,
     *                   'meta' => ['pagination' => 分页信息]
     *               ]
     * @throws Exception 当请求的文章不存在时抛出404错误
     */
    private function handleComments(): array
    {
        $subPath = $this->request->pathParts[1] ?? null;
        $cid = $this->request->pathParts[2] ?? null;

        if ($subPath === 'post' && is_numeric($cid)) {
            if (!$this->db->getPostDetail($cid)) {
                $this->response->error('Post not found', HttpCode::NOT_FOUND);
            }
            $comments = $this->db->getPostComments($cid, $this->request->pageSize, $this->request->currentPage);
            $total = $this->db->getTotalPostComments($cid);
        } else {
            $comments = $this->db->getAllComments($this->request->pageSize, $this->request->currentPage);
            $total = $this->db->getTotalComments();
        }

        return [
            'data' => array_map([$this->formatter, 'formatComment'], $comments),
            'meta' => ['pagination' => $this->buildPagination($total)]
        ];
    }

    /**
     * 处理附件列表数据
     * 
     * 从数据库获取所有附件数据并格式化，同时构建分页信息
     * 
     * @return array 返回包含附件数据和分页信息的数组，结构为：
     *     [
     *         'data' => 格式化后的附件列表,
     *         'meta' => ['pagination' => 分页信息]
     *     ]
     */
    private function handleAttachmentList(): array
    {
        $attachments = $this->db->getAllAttachments($this->request->pageSize, $this->request->currentPage);
        $total = $this->db->getTotalAttachments();
        return [
            'data' => array_map([$this->formatter, 'formatAttachment'], $attachments),
            'meta' => ['pagination' => $this->buildPagination($total)]
        ];
    }

    /**
     * 构建分页信息数组
     * 
     * @param int $total 总记录数
     * @return array 返回包含分页信息的数组，包括：
     *               - total: 总记录数
     *               - pageSize: 每页显示数量
     *               - currentPage: 当前页码
     *               - totalPages: 总页数（至少为1）
     */
    private function buildPagination(int $total): array
    {
        return [
            'total' => $total,
            'pageSize' => $this->request->pageSize,
            'currentPage' => $this->request->currentPage,
            'totalPages' => $this->request->pageSize > 0 ? max(1, (int)ceil($total / $this->request->pageSize)) : 1,
        ];
    }
}

// -----------------------------------------------------------------------------
// 应用启动入口 (Entry Point)
// -----------------------------------------------------------------------------
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';
$basePath = '/' . ltrim(__TTDF_RESTAPI_ROUTE__ ?? '', '/');

if (str_starts_with($requestUri, $basePath)) {
    try {
        // 实例化所有依赖组件
        $request   = new ApiRequest();
        $response  = new ApiResponse($request->contentFormat);
        $db        = new DB_API(); // 假设 DB_API 存在且可被实例化
        $formatter = new ApiFormatter($db, $request->contentFormat, $request->excerptLength);

        // 将依赖注入到主 API 类中
        $api = new TTDF_API($request, $response, $db, $formatter);

        // 运行 API
        $api->handleRequest();
    } catch (Throwable $e) {
        // 兜底错误处理，防止因组件初始化失败导致白屏
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=UTF-8');
        }
        error_log("API Bootstrap Error: " . $e->getMessage());
        echo json_encode([
            'code' => 500,
            'message' => 'API failed to start.',
            'error' => defined('__TYPECHO_DEBUG__') && __TYPECHO_DEBUG__ ? $e->getMessage() : 'An unexpected error occurred.'
        ]);
        exit;
    }
}
