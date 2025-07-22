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
// Token 验证中间件
// -----------------------------------------------------------------------------
final class TokenValidator
{
    public static function validate(): void
    {
        $tokenConfig = TTDF_CONFIG['REST_API']['TOKEN'] ?? [];
        if (!($tokenConfig['ENABLED'] ?? false)) {
            return;
        }

        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $tokenValue = $tokenConfig['VALUE'] ?? '';
        $tokenFormat = $tokenConfig['FORMAT'] ?? 'Bearer';

        switch ($tokenFormat) {
            case 'Bearer':
                if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                    self::sendErrorResponse('Missing or invalid Bearer token', HttpCode::UNAUTHORIZED);
                }
                if (trim($matches[1]) !== $tokenValue) {
                    self::sendErrorResponse('Invalid token', HttpCode::FORBIDDEN);
                }
                break;
                
            case 'Token':
                if (trim($authHeader) !== $tokenValue) {
                    self::sendErrorResponse('Invalid token', HttpCode::FORBIDDEN);
                }
                break;
                
            default:
                self::sendErrorResponse('Unsupported token format', HttpCode::BAD_REQUEST);
        }
    }

    private static function sendErrorResponse(string $message, HttpCode $code): never
    {
        if (!headers_sent()) {
            \Typecho\Response::getInstance()->setStatus($code->value);
            header('Content-Type: application/json; charset=UTF-8');
        }

        echo json_encode([
            'code' => $code->value,
            'message' => $message,
            'timestamp' => time()
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

// -----------------------------------------------------------------------------
// 辅助类 分离功能
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

    public function __construct()
    {
        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
        $basePath = '/' . ltrim(__TTDF_RESTAPI_ROUTE__ ?? '', '/');

        $this->path = str_starts_with($requestUri, $basePath)
            ? (substr($requestUri, strlen($basePath)) ?: '/')
            : '/';

        $this->pathParts = array_values(array_filter(explode('/', trim($this->path, '/'))));

        $this->contentFormat = ContentFormat::tryFrom(strtolower($this->getQuery('format', 'html'))) ?? ContentFormat::HTML;

        $this->pageSize = max(1, min((int)$this->getQuery('pageSize', 10), 100));

        $this->currentPage = max(1, (int)$this->getQuery('page', 1));

        $this->excerptLength = max(0, (int)$this->getQuery('excerptLength', 200));
    }

    public function getQuery(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }
}

/**
 * 专门负责发送 JSON 响应
 */
final class ApiResponse
{
    public function __construct(private ContentFormat $contentFormat) {}

    public function send(array $data = [], HttpCode $code = HttpCode::OK): never
    {
        if (!headers_sent()) {
            \Typecho\Response::getInstance()->setStatus($code->value);
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

    private function setSecurityHeaders(): void
    {
        $headers = TTDF_CONFIG['REST_API']['HEADERS'] ?? [];
        
        // 动态设置允许的来源
        $requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowedOrigins = [$requestOrigin, $_SERVER['HTTP_HOST'] ?? ''];
        $headers['Access-Control-Allow-Origin'] = in_array($requestOrigin, $allowedOrigins, true) 
            ? $requestOrigin 
            : ($allowedOrigins[1] ?? '*');
            
        // 添加必要的CORS头
        $headers['Access-Control-Allow-Headers'] = 'Content-Type, Authorization, X-Requested-With';
        $headers['Access-Control-Allow-Methods'] = 'GET, POST, OPTIONS';
        $headers['Access-Control-Allow-Credentials'] = 'true';
        
        // 防止缓存
        $headers['Cache-Control'] = 'no-cache, no-store, must-revalidate';
        $headers['Pragma'] = 'no-cache';
        $headers['Expires'] = '0';

        foreach ($headers as $name => $value) {
            if (!headers_sent() && $value !== null) {
                header("$name: $value");
            }
        }
    }
}

/**
 * 专门负责格式化数据
 */
final class ApiFormatter
{
    public function __construct(
        private readonly DB_API $dbApi,
        private readonly ContentFormat $contentFormat,
        private readonly int $excerptLength
    ) {}

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

    public function formatCategory(array $category): array
    {
        $category['description'] = $this->formatContent($category['description'] ?? '');
        return $category;
    }

    public function formatTag(array $tag): array
    {
        $tag['description'] = $this->formatContent($tag['description'] ?? '');
        return $tag;
    }

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
// 核心 API 类 路由和协调
// -----------------------------------------------------------------------------
final class TTDF_API
{
    public function __construct(
        private readonly ApiRequest $request,
        private readonly ApiResponse $response,
        private readonly DB_API $db,
        private readonly ApiFormatter $formatter
    ) {}

    public function handleRequest(): never
    {
        try {
            // 处理OPTIONS预检请求
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
                $this->response->send([], HttpCode::OK);
            }

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

    private function handlePostList(): array
    {
        $posts = $this->db->getPostList($this->request->pageSize, $this->request->currentPage);
        $total = $this->db->getTotalPosts();
        return [
            'data' => array_map([$this->formatter, 'formatPost'], $posts),
            'meta' => ['pagination' => $this->buildPagination($total)]
        ];
    }

    private function handlePageList(): array
    {
        $pages = $this->db->getAllPages($this->request->pageSize, $this->request->currentPage);
        $total = $this->db->getTotalPages();
        return [
            'data' => array_map([$this->formatter, 'formatPost'], $pages),
            'meta' => ['pagination' => $this->buildPagination($total)]
        ];
    }

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

    private function handleAttachmentList(): array
    {
        $attachments = $this->db->getAllAttachments($this->request->pageSize, $this->request->currentPage);
        $total = $this->db->getTotalAttachments();
        return [
            'data' => array_map([$this->formatter, 'formatAttachment'], $attachments),
            'meta' => ['pagination' => $this->buildPagination($total)]
        ];
    }

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
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
$basePath = '/' . ltrim(__TTDF_RESTAPI_ROUTE__ ?? '', '/');

if (str_starts_with($requestUri, $basePath)) {
    try {
        // 先验证Token
        TokenValidator::validate();
        
        // 然后继续原有流程
        $request   = new ApiRequest();
        $response  = new ApiResponse($request->contentFormat);
        $db        = new DB_API();
        $formatter = new ApiFormatter($db, $request->contentFormat, $request->excerptLength);
        $api = new TTDF_API($request, $response, $db, $formatter);
        $api->handleRequest();
    } catch (Throwable $e) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=UTF-8');
        }
        error_log("API Bootstrap Error: " . $e->getMessage());
        echo json_encode([
            'code' => 500,
            'message' => 'API failed to start.',
            'error' => defined('__TYPECHO_DEBUG__') && __TYPECHO_DEBUG__ ? $e->getMessage() : 'An unexpected error occurred.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}