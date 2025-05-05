<?php

/**
 * TTDF REST API
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$restApiSwitch = Get::Options('TTDF_RESTAPI_Switch');
if ($restApiSwitch === 'false') {
    return;
} elseif (!isset($restApiSwitch) && (!defined('__TTDF_RESTAPI__') || !__TTDF_RESTAPI__)) {
    return;
}

class TTDF_API
{
    private static $dbApi;
    private static $contentFormat = 'html';
    private static $allowedFormats = ['html', 'markdown'];

    // HTTP 状态码常量
    const HTTP_OK = 200;
    const HTTP_BAD_REQUEST = 400;
    const HTTP_UNAUTHORIZED = 401;
    const HTTP_FORBIDDEN = 403;
    const HTTP_NOT_FOUND = 404;
    const HTTP_METHOD_NOT_ALLOWED = 405;
    const HTTP_INTERNAL_ERROR = 500;

    // 默认分页设置
    const DEFAULT_PAGE_SIZE = 10;
    const MAX_PAGE_SIZE = 100;
    const DEFAULT_PAGE = 1;

    /**
     * 初始化 API 设置
     */
    private static function init()
    {
        // 检查请求方法
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            self::sendErrorResponse('Method Not Allowed', self::HTTP_METHOD_NOT_ALLOWED);
        }

        // 设置响应头
        $response = \Typecho\Response::getInstance();
        $response->setStatus(self::HTTP_OK);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        header('Content-Security-Policy: default-src \'self\'; script-src \'self\'; style-src \'self\'; img-src \'self\' data:; font-src \'self\'; connect-src \'self\'; frame-src \'self\'; object-src \'none\';');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: no-referrer-when-downgrade');
        header('Permissions-Policy: interest-cohort=()');
        header('Access-Control-Allow-Origin: *');
        header('Vary: Origin');

        // 初始化 DB_API
        self::$dbApi = new DB_API();

        // 设置内容格式
        self::$contentFormat = self::getRequestFormat();
    }

    /**
     * 获取请求的内容格式
     */
    private static function getRequestFormat()
    {
        if (isset($_GET['format']) && in_array(strtolower($_GET['format']), self::$allowedFormats, true)) {
            return strtolower($_GET['format']);
        }
        return 'html';
    }

    /**
     * 处理 API 请求
     */
    public static function handleRequest()
    {
        try {
            self::init();

            $path = self::getRequestPath();

            // 路由分发
            switch ($path) {
                case '/':
                    $response = self::handleIndex();
                    break;
                case '/PostList':
                    $response = self::handlePostList();
                    break;
                case '/Category':
                    $response = self::handleCategory();
                    break;
                case '/Tag':
                    $response = self::handleTag();
                    break;
                case '/PostContent':
                    $response = self::handlePostContent();
                    break;
                default:
                    self::sendErrorResponse('Not Found', self::HTTP_NOT_FOUND);
            }

            self::sendResponse($response);
        } catch (Exception $e) {
            self::sendErrorResponse($e->getMessage(), self::HTTP_INTERNAL_ERROR, $e);
        }
    }

    /**
     * 获取请求路径
     */
    private static function getRequestPath()
    {
        $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $basePath = '/' . ltrim(__TTDF_RESTAPI_ROUTE__, '/');

        if (strpos($requestUri, $basePath) === 0) {
            return substr($requestUri, strlen($basePath)) ?: '/';
        }

        return '/';
    }

    /**
     * 发送错误响应
     */
    private static function sendErrorResponse($message, $code, Exception $e = null)
    {
        $response = [
            'code' => $code,
            'message' => $message,
            'timestamp' => time()
        ];

        if ($e && defined('__DEBUG__') && __DEBUG__) {
            $response['error'] = $e->getMessage();
            $response['trace'] = $e->getTraceAsString();
        }

        self::sendResponse($response, $code);
    }

    /**
     * 发送 JSON 响应
     */
    private static function sendResponse(array $response, $statusCode = self::HTTP_OK)
    {
        $response = array_merge([
            'code' => $statusCode,
            'message' => 'success',
            'data' => null,
            'meta' => [
                'format' => self::$contentFormat,
                'timestamp' => time()
            ]
        ], $response);

        $options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if (defined('__DEBUG__') && __DEBUG__) {
            $options |= JSON_PRETTY_PRINT;
        }

        http_response_code($statusCode);
        echo json_encode($response, $options);
        exit;
    }

    /**
     * 处理API默认请求
     */
    private static function handleIndex()
    {
        return [
            'data' => [
                'site' => self::getSiteInfo(),
                'version' => [
                    'typecho' => TTDF::TypechoVer(false),
                    'framework' => TTDF::Ver(false),
                    'php' => TTDF::PHPVer(false),
                    'theme' => GetTheme::Ver(false),
                ],
            ]
        ];
    }

    /**
     * 处理文章列表请求
     */
    private static function handlePostList()
    {
        $pageSize = self::getPageSize();
        $currentPage = self::getCurrentPage();

        $posts = self::$dbApi->getPostList($pageSize, $currentPage);
        $total = self::$dbApi->getTotalPosts();

        return [
            'data' => [
                'list' => array_map([self::class, 'formatPost'], $posts),
                'pagination' => self::buildPagination($total, $pageSize, $currentPage),
            ]
        ];
    }

    /**
     * 处理分类请求
     */
    private static function handleCategory()
    {
        $mid = isset($_GET['mid']) ? (int)$_GET['mid'] : null;
        $slug = isset($_GET['slug']) ? trim($_GET['slug']) : null;

        if ($mid || $slug) {
            $category = $mid ? self::$dbApi->getCategoryByMid($mid) : self::$dbApi->getCategoryBySlug($slug);

            if (!$category) {
                self::sendErrorResponse('Category not found', self::HTTP_NOT_FOUND);
            }

            $pageSize = self::getPageSize();
            $currentPage = self::getCurrentPage();

            $posts = self::$dbApi->getPostsInCategory($category['mid'], $pageSize, $currentPage);
            $total = self::$dbApi->getTotalPostsInCategory($category['mid']);

            return [
                'data' => [
                    'category' => self::formatCategory($category),
                    'list' => array_map([self::class, 'formatPost'], $posts),
                    'pagination' => self::buildPagination($total, $pageSize, $currentPage, 'category')
                ]
            ];
        }

        return [
            'data' => array_map([self::class, 'formatCategory'], self::$dbApi->getAllCategories())
        ];
    }

    /**
     * 处理标签请求
     */
    private static function handleTag()
    {
        $mid = isset($_GET['mid']) ? (int)$_GET['mid'] : null;
        $slug = isset($_GET['slug']) ? trim($_GET['slug']) : null;

        if ($mid || $slug) {
            $tag = $mid ? self::$dbApi->getTagByMid($mid) : self::$dbApi->getTagBySlug($slug);

            if (!$tag) {
                self::sendErrorResponse('Tag not found', self::HTTP_NOT_FOUND);
            }

            $pageSize = self::getPageSize();
            $currentPage = self::getCurrentPage();

            $posts = self::$dbApi->getPostsInTag($tag['mid'], $pageSize, $currentPage);
            $total = self::$dbApi->getTotalPostsInTag($tag['mid']);

            return [
                'data' => [
                    'tag' => self::formatTag($tag),
                    'list' => array_map([self::class, 'formatPost'], $posts),
                    'pagination' => self::buildPagination($total, $pageSize, $currentPage, 'tag')
                ]
            ];
        }

        return [
            'data' => array_map([self::class, 'formatTag'], self::$dbApi->getAllTags())
        ];
    }

    /**
     * 处理文章内容请求
     */
    private static function handlePostContent()
    {
        $cid = isset($_GET['cid']) ? (int)$_GET['cid'] : null;
        $slug = isset($_GET['slug']) ? trim($_GET['slug']) : null;

        if (!$cid && !$slug) {
            self::sendErrorResponse('Missing cid or slug parameter', self::HTTP_BAD_REQUEST);
        }

        $post = $cid ? self::$dbApi->getPostDetail($cid) : self::$dbApi->getPostDetailBySlug($slug);

        if (!$post) {
            self::sendErrorResponse('Post not found', self::HTTP_NOT_FOUND);
        }

        return [
            'data' => self::formatPost($post, true)
        ];
    }

    /**
     * 获取分页大小
     */
    private static function getPageSize()
    {
        $pageSize = isset($_GET['pageSize']) ? (int)$_GET['pageSize'] : self::DEFAULT_PAGE_SIZE;
        return min(max(1, $pageSize), self::MAX_PAGE_SIZE);
    }

    /**
     * 获取当前页码
     */
    private static function getCurrentPage()
    {
        return max(1, (int)($_GET['page'] ?? self::DEFAULT_PAGE));
    }

    /**
     * 构建分页数据
     */
    private static function buildPagination($total, $pageSize, $currentPage, $type = null)
    {
        $pagination = [
            'total' => (int)$total,
            'pageSize' => $pageSize,
            'currentPage' => $currentPage,
            'totalPages' => max(1, ceil($total / $pageSize))
        ];

        if ($type) {
            $pagination['type'] = $type;
        }

        return $pagination;
    }

    /**
     * 格式化分类数据
     */
    private static function formatCategory($category)
    {
        if (!is_array($category)) {
            return $category;
        }

        $category['description'] = self::formatContent($category['description'] ?? '');
        return $category;
    }

    /**
     * 格式化标签数据
     */
    private static function formatTag($tag)
    {
        if (!is_array($tag)) {
            return $tag;
        }

        $tag['description'] = self::formatContent($tag['description'] ?? '');
        return $tag;
    }

    /**
     * 格式化文章数据
     */
    private static function formatPost($post, $includeContent = false)
    {
        if (!is_array($post)) {
            return $post;
        }

        $formattedPost = [
            'id' => (int)$post['cid'],
            'title' => $post['title'] ?? '',
            'slug' => $post['slug'] ?? '',
            'created' => date('c', $post['created'] ?? time()),
            'modified' => date('c', $post['modified'] ?? time()),
            'commentsNum' => (int)($post['commentsNum'] ?? 0),
            'categories' => array_map(
                [self::class, 'formatCategory'],
                self::$dbApi->getPostCategories($post['cid'] ?? 0)
            ),
            'tags' => array_map(
                [self::class, 'formatTag'],
                self::$dbApi->getPostTags($post['cid'] ?? 0)
            ),
            'status' => $post['status'] ?? 'publish',
            'contentType' => self::$contentFormat
        ];

        if ($includeContent) {
            $formattedPost['content'] = self::formatContent($post['text'] ?? '');
            $formattedPost['excerpt'] = self::formatContent(
                self::generateExcerpt($post['text'] ?? '')
            );
        }

        return $formattedPost;
    }

    /**
     * 格式化内容为指定格式
     */
    private static function formatContent($content)
    {
        if (self::$contentFormat === 'markdown') {
            return $content;
        }

        if (!class_exists('Markdown')) {
            require_once __TYPECHO_ROOT_DIR__ . '/var/Typecho/Common/Markdown.php';
        }

        $content = preg_replace('/<!--.*?-->/s', '', $content);
        return Markdown::convert($content);
    }

    /**
     * 生成文章摘要
     */
    private static function generateExcerpt($content, $length = 200)
    {
        $text = strip_tags($content);
        $text = preg_replace('/\[.*?\]\(.*?\)/', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        if (mb_strlen($text) > $length) {
            $text = mb_substr($text, 0, $length) . '...';
        }

        return trim($text);
    }

    /**
     * 获取站点信息
     */
    private static function getSiteInfo()
    {
        return [
            'theme' => Get::Options('theme'),
            'title' => Get::Options('title'),
            'description' => self::formatContent(Get::Options('description')),
            'keywords' => Get::Options('keywords'),
            'siteUrl' => Get::Options('siteUrl'),
            'timezone' => Get::Options('timezone'),
            'lang' => Get::Options('lang', false) ?: 'zh-CN',
        ];
    }
}

// 注册路由
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePath = '/' . ltrim(__TTDF_RESTAPI_ROUTE__, '/');

if (strpos($requestUri, $basePath) === 0) {
    TTDF_API::handleRequest();
}
