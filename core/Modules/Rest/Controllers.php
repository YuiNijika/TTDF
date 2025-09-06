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
                    'lang' => Get::Options('lang', false) ?: 'zh-CN',
                    'title' => Get::Options('title'),
                    'description' => $this->formatter->formatCategory(['description' => Get::Options('description')])['description'],
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
                    'pages' => [
                        'method' => 'GET',
                        'path' => '/pages',
                        'description' => '获取页面列表',
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
                                            'cid' => ['type' => 'integer', 'description' => '页面ID'],
                                            'title' => ['type' => 'string', 'description' => '页面标题'],
                                            'slug' => ['type' => 'string', 'description' => '页面别名'],
                                            'type' => ['type' => 'string', 'description' => '内容类型'],
                                            'created' => ['type' => 'string', 'format' => 'date-time', 'description' => '创建时间'],
                                            'modified' => ['type' => 'string', 'format' => 'date-time', 'description' => '修改时间'],
                                            'commentsNum' => ['type' => 'integer', 'description' => '评论数'],
                                            'authorId' => ['type' => 'integer', 'description' => '作者ID'],
                                            'status' => ['type' => 'string', 'description' => '页面状态'],
                                            'contentType' => ['type' => 'string', 'description' => '内容类型'],
                                            'fields' => ['type' => 'array', 'description' => '自定义字段'],
                                            'content' => ['type' => 'string', 'description' => '页面内容'],
                                            'excerpt' => ['type' => 'string', 'description' => '页面摘要'],
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
                    'content' => [
                        'method' => 'GET',
                        'path' => '/content/{id|slug}',
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
                    'category' => [
                        'method' => 'GET',
                        'path' => '/category[/{id|slug}]',
                        'description' => '获取分类列表或分类详情',
                        'parameters' => [
                            'id|slug' => [
                                'type' => 'string|integer',
                                'description' => '分类ID或别名（获取单个分类）',
                                'required' => false,
                            ],
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
                                    'type' => 'array|object',
                                    'description' => '分类数据或分类列表',
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
                                            'parent' => ['type' => 'integer']
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
                    'tag' => [
                        'method' => 'GET',
                        'path' => '/tag[/{id|slug}]',
                        'description' => '获取标签列表或标签详情',
                        'parameters' => [
                            'id|slug' => [
                                'type' => 'string|integer',
                                'description' => '标签ID或别名（获取单个标签）',
                                'required' => false,
                            ],
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
                                    'type' => 'array|object',
                                    'description' => '标签数据或标签列表',
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
                                            'parent' => ['type' => 'integer']
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
                    'search' => [
                        'method' => 'GET',
                        'path' => '/search/{keyword}',
                        'description' => '搜索文章',
                        'parameters' => [
                            'keyword' => [
                                'type' => 'string',
                                'description' => '搜索关键词',
                                'required' => true,
                            ],
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
                                    'type' => 'object',
                                    'properties' => [
                                        'keyword' => ['type' => 'string', 'description' => '搜索关键词'],
                                        'posts' => [
                                            'type' => 'array',
                                            'items' => [
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
                    'options' => [
                        'method' => 'GET',
                        'path' => '/options[/{name}]',
                        'description' => '获取站点选项',
                        'parameters' => [
                            'name' => [
                                'type' => 'string',
                                'description' => '选项名称（获取单个选项）',
                                'required' => false,
                            ]
                        ],
                        'response' => [
                            'type' => 'object',
                            'properties' => [
                                'data' => [
                                    'type' => 'object|array',
                                    'description' => '选项数据'
                                ]
                            ]
                        ]
                    ],
                    'fields' => [
                        'method' => 'GET',
                        'path' => '/fields/{name}/{value}',
                        'description' => '根据字段值搜索文章',
                        'parameters' => [
                            'name' => [
                                'type' => 'string',
                                'description' => '字段名称',
                                'required' => true,
                            ],
                            'value' => [
                                'type' => 'string',
                                'description' => '字段值',
                                'required' => true,
                            ],
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
                                    'type' => 'object',
                                    'properties' => [
                                        'conditions' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'name' => ['type' => 'string'],
                                                'value' => ['type' => 'string']
                                            ]
                                        ],
                                        'posts' => [
                                            'type' => 'array',
                                            'items' => [
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
                    'advancedFields' => [
                        'method' => 'GET',
                        'path' => '/advancedFields',
                        'description' => '高级字段搜索',
                        'parameters' => [
                            'conditions' => [
                                'type' => 'string',
                                'description' => 'JSON格式的搜索条件',
                                'required' => true,
                            ],
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
                                    'type' => 'object',
                                    'properties' => [
                                        'conditions' => [
                                            'type' => 'array',
                                            'items' => [
                                                'type' => 'object'
                                            ]
                                        ],
                                        'posts' => [
                                            'type' => 'array',
                                            'items' => [
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
                    'comments' => [
                        'post' => [
                            'method' => 'POST',
                            'path' => '/comments',
                            'description' => '提交评论',
                            'parameters' => [
                                'cid' => [
                                    'type' => 'integer',
                                    'description' => '文章ID',
                                    'required' => true,
                                ],
                                'author' => [
                                    'type' => 'string',
                                    'description' => '评论者姓名',
                                    'required' => true,
                                ],
                                'mail' => [
                                    'type' => 'string',
                                    'description' => '评论者邮箱',
                                    'required' => true,
                                ],
                                'text' => [
                                    'type' => 'string',
                                    'description' => '评论内容',
                                    'required' => true,
                                ],
                                'url' => [
                                    'type' => 'string',
                                    'description' => '评论者网站',
                                    'required' => false,
                                ],
                                'parent' => [
                                    'type' => 'integer',
                                    'description' => '父评论ID（用于回复）',
                                    'required' => false,
                                ]
                            ],
                            'response' => [
                                'type' => 'object',
                                'properties' => [
                                    'data' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'coid' => ['type' => 'integer', 'description' => '评论ID'],
                                            'cid' => ['type' => 'integer', 'description' => '文章ID'],
                                            'created' => ['type' => 'string', 'format' => 'date-time', 'description' => '创建时间'],
                                            'author' => ['type' => 'string', 'description' => '评论者姓名'],
                                            'mail' => ['type' => 'string', 'description' => '评论者邮箱'],
                                            'url' => ['type' => 'string', 'description' => '评论者网站'],
                                            'ip' => ['type' => 'string', 'description' => '评论者IP'],
                                            'agent' => ['type' => 'string', 'description' => '用户代理'],
                                            'text' => ['type' => 'string', 'description' => '评论内容'],
                                            'type' => ['type' => 'string', 'description' => '评论类型'],
                                            'status' => ['type' => 'string', 'description' => '评论状态'],
                                            'parent' => ['type' => 'integer', 'description' => '父评论ID'],
                                        ]
                                    ],
                                    'message' => ['type' => 'string', 'description' => '操作结果信息']
                                ]
                            ]
                        ],
                        'get' => [
                            'method' => 'GET',
                            'path' => '/comments[/{id}|/cid/{cid}]',
                            'description' => '获取评论列表或详情',
                            'parameters' => [
                                'id' => [
                                    'type' => 'integer',
                                    'description' => '评论ID（获取单个评论）',
                                    'required' => false,
                                ],
                                'cid' => [
                                    'type' => 'integer',
                                    'description' => '文章ID（获取文章下的评论）',
                                    'required' => false,
                                ],
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
                                        'type' => 'array|object',
                                        'description' => '评论数据或评论列表',
                                        'items' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'coid' => ['type' => 'integer', 'description' => '评论ID'],
                                                'cid' => ['type' => 'integer', 'description' => '文章ID'],
                                                'created' => ['type' => 'string', 'format' => 'date-time', 'description' => '创建时间'],
                                                'author' => ['type' => 'string', 'description' => '评论者姓名'],
                                                'mail' => ['type' => 'string', 'description' => '评论者邮箱'],
                                                'url' => ['type' => 'string', 'description' => '评论者网站'],
                                                'ip' => ['type' => 'string', 'description' => '评论者IP'],
                                                'agent' => ['type' => 'string', 'description' => '用户代理'],
                                                'text' => ['type' => 'string', 'description' => '评论内容'],
                                                'type' => ['type' => 'string', 'description' => '评论类型'],
                                                'status' => ['type' => 'string', 'description' => '评论状态'],
                                                'parent' => ['type' => 'integer', 'description' => '父评论ID'],
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
                        ]
                    ],
                    'attachments' => [
                        'method' => 'GET',
                        'path' => '/attachments',
                        'description' => '获取附件列表',
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
                                            'cid' => ['type' => 'integer', 'description' => '附件ID'],
                                            'title' => ['type' => 'string', 'description' => '附件标题'],
                                            'slug' => ['type' => 'string', 'description' => '附件别名'],
                                            'type' => ['type' => 'string', 'description' => '内容类型'],
                                            'created' => ['type' => 'string', 'format' => 'date-time', 'description' => '创建时间'],
                                            'modified' => ['type' => 'string', 'format' => 'date-time', 'description' => '修改时间'],
                                            'commentsNum' => ['type' => 'integer', 'description' => '评论数'],
                                            'authorId' => ['type' => 'integer', 'description' => '作者ID'],
                                            'status' => ['type' => 'string', 'description' => '附件状态'],
                                            'contentType' => ['type' => 'string', 'description' => '内容类型'],
                                            'fields' => ['type' => 'array', 'description' => '自定义字段'],
                                            'content' => ['type' => 'string', 'description' => '附件内容'],
                                            'excerpt' => ['type' => 'string', 'description' => '附件摘要'],
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
                    ]
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
            $allOptions = Get::Options();
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
        $options = DB::getAllTtdf();

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

                // 保存到数据库（DB::setTtdf会自动添加主题名前缀）
                DB::setTtdf($name, $value);
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
}
