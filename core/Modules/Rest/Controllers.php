<?php

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

require_once 'Enums.php';
require_once 'Core.php';

// 控制器基类
abstract class BaseController
{
    protected ApiRequest $request;
    protected ApiResponse $response;
    protected TTDF_Db_API $db;
    protected ApiFormatter $formatter;

    public function __construct(
        ApiRequest $request,
        ApiResponse $response,
        TTDF_Db_API $db,
        ApiFormatter $formatter
    ) {
        $this->request = $request;
        $this->response = $response;
        $this->db = $db;
        $this->formatter = $formatter;
    }

    protected function buildPagination(int $total): array
    {
        return [
            'total' => $total,
            'pageSize' => $this->request->pageSize,
            'currentPage' => $this->request->currentPage,
            'totalPages' => $this->request->pageSize > 0 ? max(1, (int)ceil($total / $this->request->pageSize)) : 1,
        ];
    }
}

// 主页控制器
class IndexController extends BaseController
{
    public function handle(): array
    {
        return [
            'data' => [
                'site' => [
                    'lang' => Get::Options('lang', false) ?: 'zh-CN',
                    'title' => Get::Options('title'),
                    'description' => Get::Options('description'),
                    'keywords' => Get::Options('keywords'),
                    'siteUrl' => Get::Options('siteUrl'),
                    'timezone' => Get::Options('timezone'),
                    'theme' => Get::Options('theme'),
                    'framework' => 'TTDF',
                ],
                'version' => [
                    'typecho' => TTDF::TypechoVer(false),
                    'framework' => TTDF::Ver(false),
                    'php' => TTDF::PHPVer(false),
                    'theme' => GetTheme::Ver(false),
                ]
            ]
        ];
    }
}

// 文章控制器
class PostController extends BaseController
{
    public function handleList(): array
    {
        $posts = $this->db->getPostList($this->request->pageSize, $this->request->currentPage);
        $total = $this->db->getTotalPosts();
        return [
            'data' => array_map([$this->formatter, 'formatPost'], $posts),
            'meta' => ['pagination' => $this->buildPagination($total)]
        ];
    }

    public function handleContent(): array
    {
        $identifier = $this->request->pathParts[1] ?? null;
        if ($identifier === null) {
            $this->response->error('Missing post identifier', HttpCode::BAD_REQUEST);
        }

        // 修复：确保传入的是整数类型
        $post = is_numeric($identifier)
            ? $this->db->getPostDetail((int)$identifier)
            : $this->db->getPostDetailBySlug($identifier);

        if (!$post) {
            $this->response->error('Post not found', HttpCode::NOT_FOUND);
        }

        return ['data' => $this->formatter->formatPost($post)];
    }
}

// 页面控制器
class PageController extends BaseController
{
    public function handleList(): array
    {
        $pages = $this->db->getAllPages($this->request->pageSize, $this->request->currentPage);
        $total = $this->db->getTotalPages();
        return [
            'data' => array_map([$this->formatter, 'formatPost'], $pages),
            'meta' => ['pagination' => $this->buildPagination($total)]
        ];
    }
}

// 分类控制器
class CategoryController extends BaseController
{
    public function handle(): array
    {
        $identifier = $this->request->pathParts[1] ?? null;
        if ($identifier === null) {
            $categories = $this->db->getAllCategories();
            return [
                'data' => array_map([$this->formatter, 'formatCategory'], $categories),
                'meta' => ['total' => count($categories)]
            ];
        }

        $category = is_numeric($identifier) ? $this->db->getCategoryByMid((int)$identifier) : $this->db->getCategoryBySlug($identifier);
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
}

// 标签控制器
class TagController extends BaseController
{
    public function handle(): array
    {
        $identifier = $this->request->pathParts[1] ?? null;
        if ($identifier === null) {
            $tags = $this->db->getAllTags();
            return [
                'data' => array_map([$this->formatter, 'formatTag'], $tags),
                'meta' => ['total' => count($tags)]
            ];
        }

        $tag = is_numeric($identifier) ? $this->db->getTagByMid((int)$identifier) : $this->db->getTagBySlug($identifier);
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
}

// 搜索控制器
class SearchController extends BaseController
{
    public function handle(): array
    {
        // 路由参数: /search/关键词
        // 查询参数: /search?keyword=关键词
        $keyword = $this->request->pathParts[1] ?? $this->request->getQuery('keyword') ?? null;

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
}

// 选项控制器
class OptionController extends BaseController
{
    public function handleOptions(): array
    {
        $optionName = $this->request->pathParts[1] ?? null;
        if ($optionName === null) {
            // 处理获取所有选项的请求
            $allowedOptions = ['title', 'description', 'keywords', 'theme', 'plugins', 'timezone', 'lang', 'charset', 'contentType', 'siteUrl', 'rootUrl', 'rewrite', 'generator', 'feedUrl', 'searchUrl'];
            $allOptions = Get::Options(false);
            $publicOptions = [];

            // 检查是否有受限选项
            $limitConfig = TTDF_CONFIG['REST_API']['LIMIT'] ?? [];
            $restrictedOptions = !empty($limitConfig['OPTIONS']) ? explode(',', $limitConfig['OPTIONS']) : [];

            foreach ($allowedOptions as $option) {
                // 跳过受限选项
                if (in_array($option, $restrictedOptions)) {
                    continue;
                }

                if (isset($allOptions->$option)) {
                    $publicOptions[$option] = $allOptions->$option;
                }
            }
            return ['data' => $publicOptions];
        }

        // 检查单个选项是否受限
        $limitConfig = TTDF_CONFIG['REST_API']['LIMIT'] ?? [];
        $restrictedOptions = !empty($limitConfig['OPTIONS']) ? explode(',', $limitConfig['OPTIONS']) : [];

        if (in_array($optionName, $restrictedOptions)) {
            $this->response->error('Access Forbidden', HttpCode::FORBIDDEN);
        }

        $optionValue = Get::Options($optionName);
        if ($optionValue === null) {
            $this->response->error('Option not found', HttpCode::NOT_FOUND);
        }
        return ['data' => ['name' => $optionName, 'value' => $optionValue]];
    }
}

// 字段控制器
class FieldController extends BaseController
{
    public function handleFieldSearch(): array
    {
        $fieldName = $this->request->pathParts[1] ?? null;
        $fieldValue = $this->request->pathParts[2] ?? null;

        if ($fieldName === null || $fieldValue === null) {
            $this->response->error('Missing field parameters', HttpCode::BAD_REQUEST);
        }

        // 检查字段是否受限
        $limitConfig = TTDF_CONFIG['REST_API']['LIMIT'] ?? [];
        $restrictedFields = !empty($limitConfig['FIELDS']) ? explode(',', $limitConfig['FIELDS']) : [];

        if (in_array($fieldName, $restrictedFields)) {
            $this->response->error('Access Forbidden', HttpCode::FORBIDDEN);
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

    public function handleAdvancedFieldSearch(): array
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

        // 检查字段是否受限
        $limitConfig = TTDF_CONFIG['REST_API']['LIMIT'] ?? [];
        $restrictedFields = !empty($limitConfig['FIELDS']) ? explode(',', $limitConfig['FIELDS']) : [];

        // 检查条件中是否包含受限字段
        foreach ($decodedConditions as $condition) {
            if (isset($condition['name']) && in_array($condition['name'], $restrictedFields)) {
                $this->response->error('Access Forbidden', HttpCode::FORBIDDEN);
            }
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
}

// 评论控制器
class CommentController extends BaseController
{
    public function handle(): array
    {
        $subPath = $this->request->pathParts[1] ?? null;

        // 如果没有子路径，返回评论列表
        if ($subPath === null) {
            $comments = $this->db->getAllComments($this->request->pageSize, $this->request->currentPage);
            $total = $this->db->getTotalComments();

            return [
                'data' => array_map([$this->formatter, 'formatComment'], $comments),
                'meta' => ['pagination' => $this->buildPagination($total)]
            ];
        }

        // 如果子路径是数字，返回指定ID评论的详情
        if (is_numeric($subPath)) {
            $commentId = (int)$subPath;
            $comment = $this->db->getCommentById($commentId);

            if (!$comment) {
                $this->response->error('Comment not found', HttpCode::NOT_FOUND);
            }

            return ['data' => $this->formatter->formatComment($comment)];
        }

        // 返回指定cid的评论列表
        if ($subPath === 'cid') {
            $cid = $this->request->pathParts[2] ?? null;

            if (!is_numeric($cid)) {
                $this->response->error('Invalid post ID', HttpCode::BAD_REQUEST);
            }

            if (!$this->db->getPostDetail((int)$cid)) {
                $this->response->error('Post not found', HttpCode::NOT_FOUND);
            }

            $comments = $this->db->getPostComments((int)$cid, $this->request->pageSize, $this->request->currentPage);
            $total = $this->db->getTotalPostComments((int)$cid);

            return [
                'data' => array_map([$this->formatter, 'formatComment'], $comments),
                'meta' => ['pagination' => $this->buildPagination($total)]
            ];
        }

        // 其他情况返回404
        $this->response->error('Endpoint not found', HttpCode::NOT_FOUND);
    }

    public function handlePostComment(): array
    {
        try {
            // 记录请求开始
            error_log("开始处理评论提交请求");

            // 获取POST数据
            $input = file_get_contents('php://input');
            $postData = json_decode($input, true);

            // 如果JSON解析失败，尝试使用表单数据
            if (!is_array($postData) && !empty($_POST)) {
                $postData = $_POST;
            }

            // 如果仍然没有数据，返回错误
            if (!is_array($postData)) {
                $error_msg = "无法解析POST数据";
                error_log($error_msg);
                $this->response->error($error_msg, HttpCode::BAD_REQUEST);
                return [];
            }

            error_log("接收到的POST数据: " . json_encode($postData));

            // 验证必需字段（包括mail）
            $requiredFields = ['cid', 'text', 'author', 'mail'];
            foreach ($requiredFields as $field) {
                if (empty($postData[$field])) {
                    $error_msg = "缺少必需字段: {$field}";
                    error_log($error_msg);
                    $this->response->error($error_msg, HttpCode::BAD_REQUEST);
                    return []; // 添加返回以避免继续执行
                }
            }

            // 验证邮箱格式
            if (!filter_var($postData['mail'], FILTER_VALIDATE_EMAIL)) {
                $error_msg = "邮箱格式无效";
                error_log($error_msg);
                $this->response->error($error_msg, HttpCode::BAD_REQUEST);
                return [];
            }

            // 验证文章是否存在
            $cid = (int)$postData['cid'];
            $post = $this->db->getPostDetail($cid);
            if (!$post) {
                $error_msg = "文章未找到，ID: {$cid}";
                error_log($error_msg);
                $this->response->error($error_msg, HttpCode::NOT_FOUND);
                return [];
            }

            // 获取客户端IP地址
            $clientIp = TTDF_Widget::GetClientIp();
            if (empty($clientIp)) {
                $clientIp = 'unknown';
            }

            // 准备评论数据
            $commentData = [
                'cid' => $cid,
                'created' => time(),
                'author' => $postData['author'],
                'mail' => $postData['mail'],
                'text' => $postData['text'],
                'status' => Helper::options()->commentsRequireModeration ? 'waiting' : 'approved',
                'agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'ip' => $clientIp,
                'type' => 'comment',
                'parent' => 0
            ];

            // 添加可选字段
            if (!empty($postData['url'])) {
                // 验证URL格式
                $url = $postData['url'];
                if (strpos($url, 'http://') !== 0 && strpos($url, 'https://') !== 0) {
                    $url = 'http://' . $url;
                }
                $commentData['url'] = $url;
            }

            if (!empty($postData['parent']) && is_numeric($postData['parent'])) {
                $commentData['parent'] = (int)$postData['parent'];
            }

            error_log("准备插入的评论数据: " . json_encode($commentData));

            // 插入评论到数据库
            $insertId = $this->db->insertComment($commentData);
            error_log("评论插入完成，ID: " . $insertId);

            if ($insertId) {
                // 获取刚插入的评论
                $comment = $this->db->getCommentById($insertId);
                error_log("获取到插入的评论: " . json_encode($comment));

                $result = [
                    'data' => $this->formatter->formatComment($comment),
                    'message' => 'Comment submitted successfully'
                ];

                error_log("返回结果: " . json_encode($result));
                return $result;
            } else {
                $error_msg = "评论提交失败";
                error_log($error_msg);
                $this->response->error($error_msg, HttpCode::INTERNAL_ERROR);
                return [];
            }
        } catch (Exception $e) {
            $error_msg = "提交评论时发生错误: " . $e->getMessage();
            error_log($error_msg);
            error_log("堆栈跟踪: " . $e->getTraceAsString());
            $this->response->error($error_msg, HttpCode::INTERNAL_ERROR);
            return [];
        }
    }
}

// 附件控制器
class AttachmentController extends BaseController
{
    public function handleList(): array
    {
        $attachments = $this->db->getAllAttachments($this->request->pageSize, $this->request->currentPage);
        $total = $this->db->getTotalAttachments();

        return [
            'data' => array_map([$this->formatter, 'formatAttachment'], $attachments),
            'meta' => ['pagination' => $this->buildPagination($total)]
        ];
    }
}

// TTDF控制器
class TTDFController extends BaseController
{
    public function handle(): array
    {
        $subPath = $this->request->pathParts[1] ?? null;

        switch ($subPath) {
            case 'options':
                return $this->handleOptions();
            case 'config':
                return $this->handleConfig();
            case 'form-data':
                return $this->handleFormData();
            case 'theme-info':
                return $this->handleThemeInfo();
            case 'export':
                return $this->handleExport();
            case 'import':
                return $this->handleImport();
            default:
                $this->response->error('Endpoint not found', HttpCode::NOT_FOUND);
        }
    }

    private function checkAdminPermission(): bool
    {
        // 记录开始检查权限
        if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
            TTDF_Debug::logApiProcess('permission_check', ['stage' => 'start']);
        }

        $user = Typecho_Widget::widget('Widget_User');

        // 记录用户状态信息
        $loginStatus = $user->hasLogin();
        $userGroup = $user->group ?? 'none';
        $passCheck = $user->pass('administrator', true);

        if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
            TTDF_Debug::logApiProcess('permission_check', [
                'login_status' => $loginStatus,
                'user_group' => $userGroup,
                'pass_check' => $passCheck
            ]);
        }

        $result = $loginStatus && ($userGroup === 'administrator' || $passCheck);

        // 记录最终结果
        if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
            TTDF_Debug::logApiProcess('permission_check', [
                'stage' => 'completed',
                'result' => $result ? 'granted' : 'denied'
            ]);
        }

        return $result;
    }

    private function handleOptions(): array
    {
        if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
            TTDF_Debug::logApiProcess('handle_options', [
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
                'stage' => 'start'
            ]);
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($method === 'GET') {
            if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
                TTDF_Debug::logApiProcess('handle_options', ['action' => 'get_options']);
            }
            return $this->getOptions();
        } elseif ($method === 'POST') {
            if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
                TTDF_Debug::logApiProcess('handle_options', ['action' => 'save_options']);
            }
            return $this->saveOptions();
        } else {
            if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
                TTDF_Debug::logApiProcess('handle_options', [
                    'action' => 'error',
                    'reason' => 'method_not_allowed'
                ]);
            }
            $this->response->error('Method Not Allowed', HttpCode::METHOD_NOT_ALLOWED);
        }
    }

    private function getOptions(): array
    {
        if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
            TTDF_Debug::logApiProcess('get_options', ['stage' => 'start']);
        }

        if (!$this->checkAdminPermission()) {
            if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
                TTDF_Debug::logApiProcess('get_options', [
                    'stage' => 'error',
                    'reason' => 'unauthorized'
                ]);
            }
            $this->response->error('Unauthorized', HttpCode::UNAUTHORIZED);
        }

        if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
            TTDF_Debug::logApiProcess('get_options', ['stage' => 'fetching_data']);
        }

        // 获取所有 ttdf 选项
        $options = TTDF_Db::getAllTtdf();

        if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
            TTDF_Debug::logApiProcess('get_options', [
                'stage' => 'completed',
                'options_count' => count($options)
            ]);
        }

        return ['data' => $options];
    }

    private function saveOptions(): array
    {
        if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
            TTDF_Debug::logApiProcess('save_options', ['stage' => 'start']);
        }

        if (!$this->checkAdminPermission()) {
            if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
                TTDF_Debug::logApiProcess('save_options', [
                    'stage' => 'error',
                    'reason' => 'unauthorized'
                ]);
            }
            $this->response->error('Unauthorized', HttpCode::UNAUTHORIZED);
        }

        // 获取POST数据
        $input = file_get_contents('php://input');
        $postData = json_decode($input, true);

        if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
            TTDF_Debug::logApiProcess('save_options', [
                'stage' => 'data_received',
                'data_type' => gettype($postData),
                'raw_input_length' => strlen($input)
            ]);
        }

        // 如果JSON解析失败，尝试使用表单数据
        if (!is_array($postData) && !empty($_POST)) {
            $postData = $_POST;
            if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
                TTDF_Debug::logApiProcess('save_options', ['data_source' => 'form_data']);
            }
        }

        if (!is_array($postData)) {
            if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
                TTDF_Debug::logApiProcess('save_options', [
                    'stage' => 'error',
                    'reason' => 'invalid_data_format'
                ]);
            }
            $this->response->error('Invalid data format', HttpCode::BAD_REQUEST);
        }

        try {
            // 获取当前主题名
            $themeName = Helper::options()->theme;

            if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
                TTDF_Debug::logApiProcess('save_options', [
                    'stage' => 'processing',
                    'theme_name' => $themeName,
                    'items_count' => count($postData)
                ]);
            }

            // 保存每个选项
            $savedCount = 0;
            foreach ($postData as $name => $value) {
                // 跳过系统字段
                if (in_array($name, ['action', '_'])) {
                    continue;
                }

                // 处理数组值
                if (is_array($value)) {
                    $value = implode(',', $value);
                }

                // 保存到数据库
                TTDF_Db::setTtdf($name, $value);
                $savedCount++;
            }

            if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
                TTDF_Debug::logApiProcess('save_options', [
                    'stage' => 'completed',
                    'saved_items' => $savedCount
                ]);
            }

            return [
                'data' => ['message' => '保存成功'],
                'meta' => ['timestamp' => time()]
            ];
        } catch (Exception $e) {
            if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
                TTDF_Debug::logApiProcess('save_options', [
                    'stage' => 'error',
                    'reason' => 'exception',
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
            }
            $this->response->error('Failed to save options: ' . $e->getMessage(), HttpCode::INTERNAL_ERROR);
        }
    }

    private function handleExport(): array
    {
        if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
            TTDF_Debug::logApiProcess('handle_export', ['stage' => 'start']);
        }

        if (!$this->checkAdminPermission()) {
            if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
                TTDF_Debug::logApiProcess('handle_export', [
                    'stage' => 'error',
                    'reason' => 'unauthorized'
                ]);
            }
            $this->response->error('Unauthorized', HttpCode::UNAUTHORIZED);
        }

        try {
            // 获取当前主题的所有设置
            $settings = TTDF_Db::getAllTtdf(true); // true表示只获取当前主题的设置

            // 获取主题信息
            $themeName = Helper::options()->theme ?? 'TTDF';
            $themeVersion = GetTheme::Ver(false) ?? '1.0.0';

            // 构建导出数据
            $exportData = [
                'version' => $themeVersion,
                'theme' => $themeName,
                'exportTime' => date('c'), // ISO 8601格式
                'settings' => $settings
            ];

            if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
                TTDF_Debug::logApiProcess('handle_export', [
                    'stage' => 'completed',
                    'settings_count' => count($settings)
                ]);
            }

            return [
                'data' => $exportData,
                'meta' => ['timestamp' => time()]
            ];
        } catch (Exception $e) {
            if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
                TTDF_Debug::logApiProcess('handle_export', [
                    'stage' => 'error',
                    'reason' => 'exception',
                    'message' => $e->getMessage()
                ]);
            }
            $this->response->error('Failed to export settings: ' . $e->getMessage(), HttpCode::INTERNAL_ERROR);
        }
    }

    private function handleImport(): array
    {
        if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
            TTDF_Debug::logApiProcess('handle_import', ['stage' => 'start']);
        }

        if (!$this->checkAdminPermission()) {
            if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
                TTDF_Debug::logApiProcess('handle_import', [
                    'stage' => 'error',
                    'reason' => 'unauthorized'
                ]);
            }
            $this->response->error('Unauthorized', HttpCode::UNAUTHORIZED);
        }

        // 获取POST数据
        $input = file_get_contents('php://input');
        $postData = json_decode($input, true);

        if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
            TTDF_Debug::logApiProcess('handle_import', [
                'stage' => 'data_received',
                'data_type' => gettype($postData),
                'raw_input_length' => strlen($input)
            ]);
        }

        // 如果JSON解析失败，尝试使用表单数据
        if (!is_array($postData) && !empty($_POST)) {
            $postData = $_POST;
        }

        if (!is_array($postData)) {
            if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
                TTDF_Debug::logApiProcess('handle_import', [
                    'stage' => 'error',
                    'reason' => 'invalid_data'
                ]);
            }
            $this->response->error('Invalid import data', HttpCode::BAD_REQUEST);
        }

        try {
            // 验证导入数据格式
            if (!isset($postData['settings']) || !is_array($postData['settings'])) {
                throw new Exception('Invalid import data format: missing settings');
            }

            $settings = $postData['settings'];
            $importedCount = 0;

            // 批量导入设置
            foreach ($settings as $key => $value) {
                if (is_string($key) && !empty($key)) {
                    TTDF_Db::setTtdf($key, $value);
                    $importedCount++;
                }
            }

            if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
                TTDF_Debug::logApiProcess('handle_import', [
                    'stage' => 'completed',
                    'imported_count' => $importedCount
                ]);
            }

            return [
                'data' => ['message' => '导入成功'],
                'meta' => [
                    'timestamp' => time(),
                    'imported_count' => $importedCount
                ]
            ];
        } catch (Exception $e) {
            if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
                TTDF_Debug::logApiProcess('handle_import', [
                    'stage' => 'error',
                    'reason' => 'exception',
                    'message' => $e->getMessage()
                ]);
            }
            $this->response->error('Failed to import settings: ' . $e->getMessage(), HttpCode::INTERNAL_ERROR);
        }
    }

    private function handleConfig(): array
    {
        if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
            TTDF_Debug::logApiProcess('handle_config', ['stage' => 'start']);
        }

        if (!$this->checkAdminPermission()) {
            if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
                TTDF_Debug::logApiProcess('handle_config', [
                    'stage' => 'error',
                    'reason' => 'unauthorized'
                ]);
            }
            $this->response->error('Unauthorized', HttpCode::UNAUTHORIZED);
        }

        try {
            // 获取配置数据
            $setupFile = __DIR__ . '/../../../app/setup.php';
            if (!file_exists($setupFile)) {
                $setupFile = __DIR__ . '/../../../app/Setup.php';
            }

            if (!file_exists($setupFile)) {
                throw new Exception('Setup file not found');
            }

            $tabs = require $setupFile;

            // 构建字段配置数据
            $fieldsConfig = [];
            foreach ($tabs as $tab) {
                if (isset($tab['fields'])) {
                    foreach ($tab['fields'] as $field) {
                        if (isset($field['name'])) {
                            $fieldsConfig[$field['name']] = $field;
                        }
                    }
                }
            }

            if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
                TTDF_Debug::logApiProcess('handle_config', [
                    'stage' => 'completed',
                    'tabs_count' => count($tabs),
                    'fields_count' => count($fieldsConfig)
                ]);
            }

            return [
                'data' => [
                    'tabs' => $tabs,
                    'fields' => $fieldsConfig
                ]
            ];
        } catch (Exception $e) {
            if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
                TTDF_Debug::logApiProcess('handle_config', [
                    'stage' => 'error',
                    'reason' => 'exception',
                    'message' => $e->getMessage()
                ]);
            }
            $this->response->error('Failed to get config: ' . $e->getMessage(), HttpCode::INTERNAL_ERROR);
        }
    }

    private function handleFormData(): array
    {
        if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
            TTDF_Debug::logApiProcess('handle_form_data', ['stage' => 'start']);
        }

        if (!$this->checkAdminPermission()) {
            if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
                TTDF_Debug::logApiProcess('handle_form_data', [
                    'stage' => 'error',
                    'reason' => 'unauthorized'
                ]);
            }
            $this->response->error('Unauthorized', HttpCode::UNAUTHORIZED);
        }

        try {
            // 获取配置数据
            $setupFile = __DIR__ . '/../../../app/setup.php';
            if (!file_exists($setupFile)) {
                $setupFile = __DIR__ . '/../../../app/Setup.php';
            }

            if (!file_exists($setupFile)) {
                throw new Exception('Setup file not found');
            }

            $tabs = require $setupFile;

            // 处理配置数据，获取当前保存的值
            $formData = [];
            foreach ($tabs as $tab_id => $tab) {
                if (isset($tab['fields'])) {
                    foreach ($tab['fields'] as $field) {
                        if (isset($field['name']) && $field['type'] !== 'Html') {
                            $value = $this->getFieldValue($field);

                            // 处理特殊字段类型的值格式
                            if (in_array($field['type'], ['Checkbox', 'AddList', 'Tags'])) {
                                // 数组类型字段：Checkbox、AddList、Tags
                                if (is_string($value) && !empty($value)) {
                                    $formData[$field['name']] = explode(',', $value);
                                } else {
                                    $formData[$field['name']] = [];
                                }
                            } else if ($field['type'] === 'Switch') {
                                // Switch类型：转换为布尔值
                                $formData[$field['name']] = ($value === 'true' || $value === '1' || $value === true);
                            } else if (in_array($field['type'], ['Number', 'Slider'])) {
                                // 数字类型字段：Number、Slider
                                $formData[$field['name']] = is_numeric($value) ? (float)$value : ($field['value'] ?? 0);
                            } else {
                                // 其他类型字段保持原样
                                $formData[$field['name']] = $value;
                            }
                        }
                    }
                }
            }

            if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
                TTDF_Debug::logApiProcess('handle_form_data', [
                    'stage' => 'completed',
                    'form_data_count' => count($formData)
                ]);
            }

            return [
                'data' => $formData
            ];
        } catch (Exception $e) {
            if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
                TTDF_Debug::logApiProcess('handle_form_data', [
                    'stage' => 'error',
                    'reason' => 'exception',
                    'message' => $e->getMessage()
                ]);
            }
            $this->response->error('Failed to get form data: ' . $e->getMessage(), HttpCode::INTERNAL_ERROR);
        }
    }

    private function handleThemeInfo(): array
    {
        if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
            TTDF_Debug::logApiProcess('handle_theme_info', ['stage' => 'start']);
        }

        if (!$this->checkAdminPermission()) {
            if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
                TTDF_Debug::logApiProcess('handle_theme_info', [
                    'stage' => 'error',
                    'reason' => 'unauthorized'
                ]);
            }
            $this->response->error('Unauthorized', HttpCode::UNAUTHORIZED);
        }

        try {
            // 获取主题信息
            $themeInfo = [
                'themeName' => get_theme_name(false),
                'themeVersion' => get_theme_version(false),
                'ttdfVersion' => get_framework_version(false),
                'apiUrl' => get_site_url(false) . __TTDF_RESTAPI_ROUTE__ . '/ttdf',
            ];

            if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
                TTDF_Debug::logApiProcess('handle_theme_info', [
                    'stage' => 'completed',
                    'theme_name' => $themeInfo['themeName']
                ]);
            }

            return [
                'data' => $themeInfo
            ];
        } catch (Exception $e) {
            if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
                TTDF_Debug::logApiProcess('handle_theme_info', [
                    'stage' => 'error',
                    'reason' => 'exception',
                    'message' => $e->getMessage()
                ]);
            }
            $this->response->error('Failed to get theme info: ' . $e->getMessage(), HttpCode::INTERNAL_ERROR);
        }
    }

    /**
     * 获取字段的当前值
     */
    private function getFieldValue($field)
    {
        // 检查字段是否有name属性
        if (!isset($field['name']) || empty($field['name'])) {
            return $field['value'] ?? '';
        }

        $dbValue = TTDF_Db::getTtdf($field['name']);

        if ($dbValue !== null) {
            // 对于复选框、Tags、AddList和DialogSelect，需要特殊处理比较
            if (in_array($field['type'], ['Checkbox', 'Tags', 'AddList', 'DialogSelect'])) {
                $setupDefault = is_array($field['value']) ? implode(',', $field['value']) : $field['value'];
                $dbValueForCompare = $dbValue;

                // 标准化比较去除空格并排序
                $setupNormalized = $setupDefault;
                $dbNormalized = $dbValueForCompare;

                if (!empty($setupNormalized)) {
                    $setupArray = explode(',', $setupNormalized);
                    $setupArray = array_map('trim', $setupArray);
                    sort($setupArray);
                    $setupNormalized = implode(',', $setupArray);
                }

                if (!empty($dbNormalized)) {
                    $dbArray = explode(',', $dbNormalized);
                    $dbArray = array_map('trim', $dbArray);
                    sort($dbArray);
                    $dbNormalized = implode(',', $dbArray);
                }

                if ($dbNormalized !== $setupNormalized) {
                    return $dbValue;
                }
            }
            // 对于Switch类型，需要特殊处理布尔值比较
            else if ($field['type'] === 'Switch') {
                $setupDefault = $field['value'] ?? false;
                // 将数据库中的字符串值转换为布尔值进行比较
                $dbBoolValue = ($dbValue === 'true' || $dbValue === '1' || $dbValue === true);
                $setupBoolValue = ($setupDefault === true || $setupDefault === 'true' || $setupDefault === '1');

                if ($dbBoolValue !== $setupBoolValue) {
                    return $dbValue;
                }
            }
            // 对于Number和Slider类型，需要特殊处理数字比较
            else if (in_array($field['type'], ['Number', 'Slider'])) {
                $setupDefault = $field['value'] ?? 0;
                // 将数据库中的字符串值转换为数字进行比较
                $dbNumValue = is_numeric($dbValue) ? (float)$dbValue : 0;
                $setupNumValue = is_numeric($setupDefault) ? (float)$setupDefault : 0;

                if ($dbNumValue !== $setupNumValue) {
                    return $dbValue;
                }
            } else {
                if ($dbValue !== $field['value']) {
                    return $dbValue;
                }
            }
        }

        return $field['value'] ?? '';
    }
}
