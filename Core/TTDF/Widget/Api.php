<?php

/**
 * TTDF REST API
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

$restApiSwitch = Get::Options('TTDF_RESTAPI_Switch');
if ($restApiSwitch === 'false') {
    // 确保 Router::$current 是一个空字符串而不是 null
    if (!isset(Typecho\Router::$current)) {
        Typecho\Router::$current = '';
    }
    return;
} elseif (!isset($restApiSwitch) && (!defined('__TTDF_RESTAPI__') || !__TTDF_RESTAPI__)) {
    if (!isset(Typecho\Router::$current)) {
        Typecho\Router::$current = '';
    }
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
            $pathParts = explode('/', trim($path, '/'));

            // 路由分发
            switch ($pathParts[0]) {
                case '':
                    $response = self::handleIndex();
                    break;
                case 'posts':
                    $response = self::handlePostList($pathParts);
                    break;
                case 'content':
                    $response = self::handlePostContent($pathParts);
                    break;
                case 'category':
                    $response = self::handleCategory($pathParts);
                    break;
                case 'tag':
                    $response = self::handleTag($pathParts);
                    break;
                case 'search':
                    $response = self::handleSearch($pathParts);
                    break;
                case 'options':
                    $response = self::handleOptions($pathParts);
                    break;
                case 'themeOptions':
                    $response = self::handleThemeOptions($pathParts);
                    break;
                case 'fields':
                    $response = self::handleFieldSearch($pathParts);
                    break;
                case 'advancedFields':
                    $response = self::handleAdvancedFieldSearch($pathParts);
                    break;
                case 'comments':
                    $response = self::handleComments($pathParts);
                    break;
                case 'pages':
                    $response = self::handlePageList($pathParts);
                    break;
                case 'attachments':
                    $response = self::handleAttachmentList($pathParts);
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
    private static function handlePostList($pathParts)
    {
        $pageSize = self::getPageSize();
        $currentPage = self::getCurrentPage();

        $posts = self::$dbApi->getPostList($pageSize, $currentPage);
        $total = self::$dbApi->getTotalPosts();

        return [
            'data' => [
                'list' => array_map(fn($post) => self::formatPost($post, true), $posts),
                'pagination' => self::buildPagination($total, $pageSize, $currentPage),
                'page' => $currentPage,
                'pageSize' => $pageSize,
                'total' => $total
            ]
        ];
    }

    /**
     * 处理页面列表请求
     */
    private static function handlePageList($pathParts)
    {
        $pageSize = self::getPageSize();
        $currentPage = self::getCurrentPage();

        $pages = self::$dbApi->getAllPages($pageSize, $currentPage);
        $total = self::$dbApi->getTotalPages();

        return [
            'data' => [
                'list' => array_map([self::class, 'formatPost'], $pages),
                'pagination' => self::buildPagination($total, $pageSize, $currentPage, 'pages'),
                'page' => $currentPage,
                'pageSize' => $pageSize,
                'total' => $total
            ]
        ];
    }

    /**
     * 处理分类请求
     */
    private static function handleCategory($pathParts)
    {
        if (count($pathParts) === 1) {
            // /category - 获取所有分类
            return [
                'data' => array_map([self::class, 'formatCategory'], self::$dbApi->getAllCategories()),
                'page' => 1,
                'pageSize' => 'all',
                'total' => count(self::$dbApi->getAllCategories())
            ];
        }

        // 解析路径参数
        $identifier = $pathParts[1];
        $isMid = is_numeric($identifier);

        $category = $isMid ? self::$dbApi->getCategoryByMid($identifier) : self::$dbApi->getCategoryBySlug($identifier);

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
                'pagination' => self::buildPagination($total, $pageSize, $currentPage, 'category'),
                'page' => $currentPage,
                'pageSize' => $pageSize,
                'total' => $total
            ]
        ];
    }

    /**
     * 处理标签请求
     */
    private static function handleTag($pathParts)
    {
        if (count($pathParts) === 1) {
            // /tag - 获取所有标签
            return [
                'data' => array_map([self::class, 'formatTag'], self::$dbApi->getAllTags()),
                'page' => 1,
                'pageSize' => 'all',
                'total' => count(self::$dbApi->getAllTags())
            ];
        }

        // 解析路径参数
        $identifier = $pathParts[1];
        $isMid = is_numeric($identifier);

        $tag = $isMid ? self::$dbApi->getTagByMid($identifier) : self::$dbApi->getTagBySlug($identifier);

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
                'pagination' => self::buildPagination($total, $pageSize, $currentPage, 'tag'),
                'page' => $currentPage,
                'pageSize' => $pageSize,
                'total' => $total
            ]
        ];
    }

    /**
     * 处理文章内容请求
     */
    private static function handlePostContent($pathParts)
    {
        if (count($pathParts) < 2) {
            self::sendErrorResponse('Missing post identifier', self::HTTP_BAD_REQUEST);
        }

        $identifier = $pathParts[1];
        $isCid = is_numeric($identifier);

        $post = $isCid ? self::$dbApi->getPostDetail($identifier) : self::$dbApi->getPostDetailBySlug($identifier);

        if (!$post) {
            self::sendErrorResponse('Post not found', self::HTTP_NOT_FOUND);
        }

        return [
            'data' => self::formatPost($post, true),
            'page' => 1,
            'pageSize' => 1,
            'total' => 1
        ];
    }

    // 添加搜索处理方法
    private static function handleSearch($pathParts)
    {
        if (count($pathParts) < 2 || empty($pathParts[1])) {
            self::sendErrorResponse('Missing search keyword', self::HTTP_BAD_REQUEST);
        }

        // 对关键词进行URL解码
        $keyword = urldecode($pathParts[1]);

        // 记录日志用于调试（调试完成后可移除）
        error_log("Search keyword: " . $keyword);

        $pageSize = self::getPageSize();
        $currentPage = self::getCurrentPage();

        try {
            $posts = self::$dbApi->searchPosts($keyword, $pageSize, $currentPage);
            $total = self::$dbApi->getSearchPostsCount($keyword);

            // 记录搜索结果数量（调试用）
            error_log("Search results: " . count($posts) . " out of " . $total);

            return [
                'data' => [
                    'keyword' => $keyword,
                    'list' => array_map(fn($post) => self::formatPost($post, true), $posts),
                    'pagination' => self::buildPagination($total, $pageSize, $currentPage, 'search'),
                    'page' => $currentPage,
                    'pageSize' => $pageSize,
                    'total' => $total
                ]
            ];
        } catch (Exception $e) {
            // 记录错误信息
            error_log("Search error: " . $e->getMessage());
            self::sendErrorResponse('Search failed', self::HTTP_INTERNAL_ERROR, $e);
        }
    }

    /**
     * 处理options请求
     */
    private static function handleOptions($pathParts)
    {
        if (count($pathParts) === 1) {
            // /options - 获取所有公开选项
            return [
                'data' => self::getAllPublicOptions(),
                'page' => 1,
                'pageSize' => 'all',
                'total' => count(self::getAllPublicOptions())
            ];
        }

        // /options/{name} - 获取特定选项
        $optionName = $pathParts[1];
        $optionValue = Get::Options($optionName);

        if ($optionValue === null) {
            self::sendErrorResponse('Option not found', self::HTTP_NOT_FOUND);
        }

        return [
            'data' => [
                'name' => $optionName,
                'value' => $optionValue
            ],
            'page' => 1,
            'pageSize' => 1,
            'total' => 1
        ];
    }

    /**
     * 获取所有公开选项
     */
    private static function getAllPublicOptions()
    {
        $options = Helper::options();
        $publicOptions = [];

        // 定义允许公开的选项白名单
        $allowedOptions = [
            'title',
            'description',
            'keywords',
            'theme',
            'plugins',
            'timezone',
            'lang',
            'charset',
            'contentType',
            'siteUrl',
            'rootUrl',
            'rewrite',
            'generator',
            'feedUrl',
            'searchUrl'
        ];

        foreach ($allowedOptions as $option) {
            if (isset($options->$option)) {
                $publicOptions[$option] = $options->$option;
            }
        }

        return $publicOptions;
    }

    /**
     * 处理主题设置请求
     */
    private static function handleThemeOptions($pathParts)
    {
        // 主题名称
        $themeName = GetTheme::Name(false);

        // 获取主题设置
        $themeOptions = self::getThemeOptions($themeName);

        if (count($pathParts) === 1) {
            // /themeOtions - 获取所有主题设置
            return [
                'data' => $themeOptions,
                'page' => 1,
                'pageSize' => 'all',
                'total' => count($themeOptions)
            ];
        }

        // /themeOptions/{name} - 获取特定主题设置项
        $optionName = $pathParts[1];

        if (!isset($themeOptions[$optionName])) {
            self::sendErrorResponse('Theme option not found', self::HTTP_NOT_FOUND);
        }

        return [
            'data' => [
                'name' => $optionName,
                'value' => $themeOptions[$optionName]
            ],
            'page' => 1,
            'pageSize' => 1,
            'total' => 1
        ];
    }

    /**
     * 获取主题设置项
     * @param string $themeName 主题名称
     * @return array 主题设置数组
     */
    private static function getThemeOptions($themeName)
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix(); // 获取表前缀

        $row = $db->fetchRow($db->select('value')
            ->from($prefix . 'options') // 使用正确的表名格式
            ->where('name = ?', 'theme:' . $themeName)
            ->limit(1));

        if (!$row || !isset($row['value'])) {
            return [];
        }

        // 反序列化主题设置
        $options = @unserialize($row['value']);
        return is_array($options) ? $options : [];
    }
    // 添加字段搜索处理方法
    private static function handleFieldSearch($pathParts)
    {
        if (count($pathParts) < 3) {
            self::sendErrorResponse('Missing field parameters', self::HTTP_BAD_REQUEST);
        }

        // 定义字段名和字段值
        $fieldName = $pathParts[1];
        $fieldValue = urldecode($pathParts[2]);

        // 构建条件数组
        $conditions[] = [
            'name' => $fieldName,
            'operator' => $_GET['operator'] ?? '=',
            'value' => $fieldValue,
            'value_type' => $_GET['value_type'] ?? 'str'
        ];

        $pageSize = self::getPageSize();
        $currentPage = self::getCurrentPage();

        $posts = self::$dbApi->getPostsByField($fieldName, $fieldValue, $pageSize, $currentPage);
        $total = self::$dbApi->getPostsCountByField($fieldName, $fieldValue);

        return [
            'data' => [
                'conditions' => [
                    'name' => $fieldName,
                    'value' => $fieldValue,
                    'value_type' => $_GET['value_type'] ?? 'str'
                ],
                'list' => array_map(fn($post) => self::formatPost($post, true), $posts),
                'pagination' => self::buildPagination($total, $pageSize, $currentPage, 'field'),
                'page' => $currentPage,
                'pageSize' => $pageSize,
                'total' => $total
            ]
        ];
    }

    /**
     * 处理高级字段搜索请求
     * @param array $pathParts 路由路径数组
     */
    private static function handleAdvancedFieldSearch($pathParts)
    {
        $conditions = [];

        // 精简匹配 {name}/{value}
        if (count($pathParts) >= 3) {
            $fieldName = $pathParts[1];
            $fieldValue = urldecode($pathParts[2]);

            $conditions[] = [
                'name' => $fieldName,
                'operator' => $_GET['operator'] ?? '=',
                'value' => $fieldValue,
                'value_type' => $_GET['value_type'] ?? 'str'
            ];
        }
        // 高级字段 ?conditions=[JSON]
        elseif (isset($_GET['conditions'])) {
            $decoded = json_decode($_GET['conditions'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $conditions = $decoded;
            }
        }

        // 验证条件
        if (empty($conditions)) {
            self::sendErrorResponse('Invalid search conditions', self::HTTP_BAD_REQUEST);
        }

        $pageSize = self::getPageSize();
        $currentPage = self::getCurrentPage();

        $posts = self::$dbApi->getPostsByAdvancedFields($conditions, $pageSize, $currentPage);
        $total = self::$dbApi->getPostsCountByAdvancedFields($conditions);

        return [
            'data' => [
                'conditions' => $conditions,
                'list' => array_map(fn($post) => self::formatPost($post, true), $posts),
                'pagination' => self::buildPagination($total, $pageSize, $currentPage, 'advanced-fields'),
                'page' => $currentPage,
                'pageSize' => $pageSize,
                'total' => $total
            ]
        ];
    }

    /**
     * 处理评论请求
     */
    private static function handleComments($pathParts)
    {
        if (count($pathParts) === 1) {
            // /comments - 获取所有评论
            $pageSize = self::getPageSize();
            $currentPage = self::getCurrentPage();

            $comments = self::$dbApi->getAllComments($pageSize, $currentPage);
            $total = self::$dbApi->getTotalComments();

            return [
                'data' => [
                    'list' => array_map([self::class, 'formatComment'], $comments),
                    'pagination' => self::buildPagination($total, $pageSize, $currentPage, 'comments'),
                    'page' => $currentPage,
                    'pageSize' => $pageSize,
                    'total' => $total
                ]
            ];
        } elseif (count($pathParts) >= 2 && $pathParts[1] === 'post') {
            // /comments/post/{cid} - 获取特定文章的评论
            if (count($pathParts) < 3) {
                self::sendErrorResponse('Missing post ID', self::HTTP_BAD_REQUEST);
            }

            $cid = $pathParts[2];
            if (!is_numeric($cid)) {
                self::sendErrorResponse('Invalid post ID', self::HTTP_BAD_REQUEST);
            }

            $pageSize = self::getPageSize();
            $currentPage = self::getCurrentPage();

            $comments = self::$dbApi->getPostComments($cid, $pageSize, $currentPage);
            $total = self::$dbApi->getTotalPostComments($cid);

            // 检查文章是否存在
            $post = self::$dbApi->getPostDetail($cid);
            if (!$post) {
                self::sendErrorResponse('Post not found', self::HTTP_NOT_FOUND);
            }

            return [
                'data' => [
                    'post' => [
                        'cid' => (int)$post['cid'],
                        'title' => $post['title'] ?? ''
                    ],
                    'list' => array_map([self::class, 'formatComment'], $comments),
                    'pagination' => self::buildPagination($total, $pageSize, $currentPage, 'comments'),
                    'page' => $currentPage,
                    'pageSize' => $pageSize,
                    'total' => $total
                ]
            ];
        }

        self::sendErrorResponse('Not Found', self::HTTP_NOT_FOUND);
    }

    /**
     * 格式化评论数据
     */
    private static function formatComment($comment)
    {
        if (!is_array($comment)) {
            return $comment;
        }

        return [
            'coid' => (int)$comment['coid'],
            'cid' => (int)$comment['cid'],
            'author' => $comment['author'] ?? '',
            'mail' => $comment['mail'] ?? '',
            'url' => $comment['url'] ?? '',
            'ip' => $comment['ip'] ?? '',
            'created' => date('c', $comment['created'] ?? time()),
            'modified' => date('c', $comment['modified'] ?? time()),
            'text' => self::formatContent($comment['text'] ?? ''),
            'status' => $comment['status'] ?? 'approved',
            'parent' => (int)($comment['parent'] ?? 0),
            'authorId' => (int)($comment['authorId'] ?? 0)
        ];
    }

    /**
     * 处理附件列表请求
     */
    private static function handleAttachmentList($pathParts)
    {
        $pageSize = self::getPageSize();
        $currentPage = self::getCurrentPage();

        $attachments = self::$dbApi->getAllAttachments($pageSize, $currentPage);
        $total = self::$dbApi->getTotalAttachments();

        return [
            'data' => [
                'list' => array_map([self::class, 'formatAttachment'], $attachments),
                'pagination' => self::buildPagination($total, $pageSize, $currentPage, 'attachments'),
                'page' => $currentPage,
                'pageSize' => $pageSize,
                'total' => $total
            ]
        ];
    }

    /**
     * 格式化附件数据
     */
    private static function formatAttachment($attachment)
    {
        if (!is_array($attachment)) {
            return $attachment;
        }

        $options = Helper::options();

        return [
            'cid' => (int)$attachment['cid'],
            'title' => $attachment['title'] ?? '',
            'type' => $attachment['type'] ?? '',
            'size' => (int)($attachment['size'] ?? 0),
            'created' => date('c', $attachment['created'] ?? time()),
            'modified' => date('c', $attachment['modified'] ?? time()),
            'status' => $attachment['status'] ?? 'publish',
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
            'cid' => (int)$post['cid'],
            'title' => $post['title'] ?? '',
            'slug' => $post['slug'] ?? '',
            'type' => $post['type'] ?? 'post',
            'created' => date('c', $post['created'] ?? time()),
            'modified' => date('c', $post['modified'] ?? time()),
            'commentsNum' => (int)($post['commentsNum'] ?? 0),
            'authorId' => (int)($post['authorId'] ?? 0),
            'status' => $post['status'] ?? 'publish',
            'contentType' => self::$contentFormat,
            'fields' => self::$dbApi->getPostFields($post['cid'] ?? 0), // 添加字段数据
        ];

        if ($formattedPost['type'] === 'post') {
            $formattedPost['categories'] = array_map(
                [self::class, 'formatCategory'],
                self::$dbApi->getPostCategories($post['cid'] ?? 0)
            );
            $formattedPost['tags'] = array_map(
                [self::class, 'formatTag'],
                self::$dbApi->getPostTags($post['cid'] ?? 0)
            );
        }

        // 获取摘要长度参数，默认为200
        $excerptLength = isset($_GET['excerptLength']) ? (int)$_GET['excerptLength'] : 200;

        // 总是包含内容
        $formattedPost['content'] = self::formatContent($post['text'] ?? '');

        // 生成纯文本摘要
        $formattedPost['excerpt'] = self::generatePlainExcerpt(
            $post['text'] ?? '',
            $excerptLength
        );

        return $formattedPost;
    }

    /**
     * 生成纯文本摘要
     */
    private static function generatePlainExcerpt($content, $length = 200)
    {
        // 去除HTML
        $text = strip_tags($content);

        // 处理Markdown
        $text = preg_replace('/```.*?```/s', '', $text);  // 去除代码块
        $text = preg_replace('/~~~.*?~~~/s', '', $text);  // 去除代码块
        $text = preg_replace('/`.*?`/', '', $text);       // 去除行内代码
        $text = preg_replace('/$$([^$$]+)\]$[^)]+$/', '$1', $text);
        $text = preg_replace('/!$$([^$$]*)\]$[^)]+$/', '', $text);
        $text = preg_replace('/^#{1,6}\s*/m', '', $text);
        $text = preg_replace('/[\*\_]{1,3}([^*_]+)[\*\_]{1,3}/', '$1', $text);
        $text = preg_replace('/^\s*>\s*/m', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        // 截取指定长度
        if (mb_strlen($text) > $length) {
            $text = mb_substr($text, 0, $length);

            // 确保截断后不会在单词中间断开
            if (preg_match('/\s\S+$/', $text, $matches)) {
                $text = mb_substr($text, 0, mb_strlen($text) - mb_strlen($matches[0]));
            }
        }

        return $text;
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
