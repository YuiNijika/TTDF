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
    protected DB_API $db;
    protected ApiFormatter $formatter;

    public function __construct(
        ApiRequest $request,
        ApiResponse $response,
        DB_API $db,
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
                'api' => [
                    'index' => [
                        'method' => 'GET',
                        'path' => '/',
                        'description' => '获取站点信息',
                        'parameters' => [],
                    ],
                    'posts' => [
                        'method' => 'GET',
                        'path' => '/posts',
                        'description' => '获取文章列表',
                        'parameters' => [
                            'page' => [
                                'type' => 'integer',
                                'description' => '页码',
                                'required' => false,
                                'default' => 1,
                            ],
                            'pageSize' => [
                                'type' => 'integer',
                                'description' => '每页数量',
                                'required' => false,
                                'default' => 10,
                            ]
                        ],
                        'response' => [
                            'type' => 'object',
                            'properties' => [
                                'data' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'cid' => [
                                                'type' => 'integer',
                                                'description' => '文章ID'
                                            ],
                                            'title' => [
                                                'type' => 'string',
                                                'description' => '文章标题'
                                            ],
                                            'slug' => [
                                                'type' => 'string',
                                                'description' => '文章别名'
                                            ],
                                            'type' => [
                                                'type' => 'string',
                                                'description' => '内容类型'
                                            ],
                                            'created' => [
                                                'type' => 'string',
                                                'format' => 'date-time',
                                                'description' => '创建时间'
                                            ],
                                            'modified' => [
                                                'type' => 'string',
                                                'format' => 'date-time',
                                                'description' => '修改时间'
                                            ],
                                            'commentsNum' => [
                                                'type' => 'integer',
                                                'description' => '评论数'
                                            ],
                                            'authorId' => [
                                                'type' => 'integer',
                                                'description' => '作者ID'
                                            ],
                                            'status' => [
                                                'type' => 'string',
                                                'description' => '文章状态'
                                            ],
                                            'contentType' => [
                                                'type' => 'string',
                                                'description' => '内容类型'
                                            ],
                                            'fields' => [
                                                'type' => 'array',
                                                'description' => '自定义字段'
                                            ],
                                            'content' => [
                                                'type' => 'string',
                                                'description' => '文章内容'
                                            ],
                                            'excerpt' => [
                                                'type' => 'string',
                                                'description' => '文章摘要'
                                            ],
                                            'categories' => [
                                                'type' => 'array',
                                                'items' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'mid' => ['type' => 'integer'],
                                                        'name' => ['type' => 'string'],
                                                        'slug' => ['type' => 'string'],
                                                        'type' => ['type' => 'string'],
                                                        'description' => ['type' => 'string'],
                                                        'count' => ['type' => 'integer'],
                                                        'order' => ['type' => 'integer'],
                                                        'parent' => ['type' => 'integer'],
                                                        'cid' => ['type' => 'integer']
                                                    ]
                                                ]
                                            ],
                                            'tags' => [
                                                'type' => 'array',
                                                'items' => [
                                                    'type' => 'object'
                                                ]
                                            ]
                                        ]
                                    ]
                                ],
                                'meta' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'pagination' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'total' => ['type' => 'integer'],
                                                'pageSize' => ['type' => 'integer'],
                                                'currentPage' => ['type' => 'integer'],
                                                'totalPages' => ['type' => 'integer']
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'post' => [
                        'method' => 'GET',
                        'path' => '/posts/{id|slug}',
                        'description' => '获取文章详情',
                        'parameters' => [
                            'id|slug' => [
                                'type' => 'string|integer',
                                'description' => '文章ID或别名',
                                'required' => true,
                            ]
                        ],
                        'response' => [
                            'type' => 'object',
                            'properties' => [
                                'data' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'cid' => ['type' => 'integer', 'description' => '文章ID'],
                                        'title' => ['type' => 'string', 'description' => '文章标题'],
                                        'slug' => ['type' => 'string', 'description' => '文章别名'],
                                        'type' => ['type' => 'string', 'description' => '内容类型'],
                                        'created' => ['type' => 'string', 'format' => 'date-time', 'description' => '创建时间'],
                                        'modified' => ['type' => 'string', 'format' => 'date-time', 'description' => '修改时间'],
                                        'commentsNum' => ['type' => 'integer', 'description' => '评论数'],
                                        'authorId' => ['type' => 'integer', 'description' => '作者ID'],
                                        'status' => ['type' => 'string', 'description' => '文章状态'],
                                        'contentType' => ['type' => 'string', 'description' => '内容类型'],
                                        'fields' => ['type' => 'array', 'description' => '自定义字段'],
                                        'content' => ['type' => 'string', 'description' => '文章内容'],
                                        'excerpt' => ['type' => 'string', 'description' => '文章摘要'],
                                        'categories' => [
                                            'type' => 'array',
                                            'items' => [
                                                'type' => 'object',
                                                'properties' => [
                                                    'mid' => ['type' => 'integer'],
                                                    'name' => ['type' => 'string'],
                                                    'slug' => ['type' => 'string'],
                                                    'type' => ['type' => 'string'],
                                                    'description' => ['type' => 'string'],
                                                    'count' => ['type' => 'integer'],
                                                    'order' => ['type' => 'integer'],
                                                    'parent' => ['type' => 'integer'],
                                                    'cid' => ['type' => 'integer']
                                                ]
                                            ]
                                        ],
                                        'tags' => [
                                            'type' => 'array',
                                            'items' => [
                                                'type' => 'object'
                                            ]
                                        ]
                                    ]
                                ],
                                'meta' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'format' => ['type' => 'string'],
                                        'timestamp' => ['type' => 'integer']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'content' => [
                        'type' => 'object',
                        'description' => '文章内容',
                        'properties' => [
                            'title' => [
                                'type' => 'string',
                                'description' => '标题',
                                'required' => true,
                            ],
                            'content' => [
                                'type' => 'string',
                                'description' => '内容',
                                'required' => true,
                            ],
                            'status' => [
                                'type' => 'string',
                                'description' => '状态',
                                'required' => true,
                            ],
                            'slug' => [
                                'type' => 'string',
                                'description' => '别名',
                            ]
                        ]
                    ],
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
