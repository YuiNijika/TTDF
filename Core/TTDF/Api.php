<?php

/**
 * TTDF REST API
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class TTDF_API
{
    private static $dbApi;

    public static function handleRequest()
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        try {
            // 初始化 DB_API
            self::$dbApi = new DB_API();

            // 获取请求路径和方法
            $requestUri = $_SERVER['REQUEST_URI'];
            $basePath = '/' . __TTDF_RESTAPI_ROUTE__;

            // 使用 parse_url 提取路径部分，忽略查询参数
            $path = parse_url($requestUri, PHP_URL_PATH);
            $path = substr($path, strlen($basePath));

            $method = $_SERVER['REQUEST_METHOD'];

            // 初始化响应数据
            $response = [
                'code' => 200,
                'message' => 'success',
                'data' => null
            ];

            // 根据路由分发请求
            switch ($path) {
                case '/PostList':
                    self::getPostList($response);
                    break;
                case '/Category':
                    self::getCategory($response);
                    break;
                case '/Tag':
                    self::getTag($response);
                    break;
                case '/PostCommon':
                    self::getPostCommon($response);
                    break;
                default:
                    $response['code'] = 404;
                    $response['message'] = 'Not Found';
            }
        } catch (Exception $e) {
            $response = [
                'code' => 500,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ];
        }

        // 输出 JSON 响应
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    private static function getPostList(&$response)
    {
        $pageSize = isset($_GET['pageSize']) ? intval($_GET['pageSize']) : 10;
        $currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

        // 使用 DB_API 获取文章列表
        $posts = self::$dbApi->getPostList($pageSize, $currentPage);
        $total = self::$dbApi->getTotalPosts();
        $postList = [];

        foreach ($posts as $post) {
            $postList[] = self::formatPost($post);
        }

        $response['data'] = [
            'list' => $postList,
            'pagination' => [
                'total' => (int)$total,
                'pageSize' => $pageSize,
                'currentPage' => $currentPage,
                'totalPages' => ceil($total / $pageSize)
            ],
            'site' => self::getSiteInfo()
        ];
    }

    private static function getCategory(&$response)
    {
        $cid = isset($_GET['cid']) ? intval($_GET['cid']) : null;

        if ($cid) {
            // 获取特定分类下的文章
            $posts = self::$dbApi->getPostsInCategory($cid);
            $total = self::$dbApi->getTotalPostsInCategory($cid);
            $postList = [];

            foreach ($posts as $post) {
                $postList[] = [
                    'id' => $post['cid'],
                    'title' => $post['title'],
                    'created' => date('Y-m-d H:i:s', $post['created']),
                ];
            }

            $response['data'] = [
                'list' => $postList,
                'pagination' => [
                    'total' => (int)$total,
                    'pageSize' => 10,
                    'currentPage' => 1,
                    'totalPages' => ceil($total / 10)
                ]
            ];
        } else {
            // 获取所有分类
            $response['data'] = self::$dbApi->getAllCategories();
        }
    }

    private static function getTag(&$response)
    {
        $tid = isset($_GET['tid']) ? intval($_GET['tid']) : null;

        if ($tid) {
            // 获取特定标签下的文章
            $posts = self::$dbApi->getPostsInTag($tid);
            $total = self::$dbApi->getTotalPostsInTag($tid);
            $postList = [];

            foreach ($posts as $post) {
                $postList[] = [
                    'id' => $post['cid'],
                    'title' => $post['title'],
                    'created' => date('Y-m-d H:i:s', $post['created']),
                    'url' => Typecho_Common::url($post['slug'], Helper::options()->siteUrl),
                    'api' => Helper::options()->siteUrl . '/Common?cid=' . $post['cid']
                ];
            }

            $response['data'] = [
                'list' => $postList,
                'pagination' => [
                    'total' => (int)$total,
                    'pageSize' => 10,
                    'currentPage' => 1,
                    'totalPages' => ceil($total / 10)
                ]
            ];
        } else {
            // 获取所有标签
            $response['data'] = self::$dbApi->getAllTags();
        }
    }

    private static function getPostCommon(&$response)
    {
        $cid = isset($_GET['cid']) ? intval($_GET['cid']) : null;

        if (!$cid) {
            $response['code'] = 400;
            $response['message'] = 'Missing cid parameter';
            return;
        }

        // 获取文章详情
        $post = self::$dbApi->getPostDetail($cid);

        if ($post) {
            $response['data'] = self::formatPost($post, true);
        } else {
            $response['code'] = 404;
            $response['message'] = 'Post not found';
        }
    }

    private static function formatPost($post, $includeContent = false)
    {
        $categories = self::$dbApi->getPostCategories($post['cid']);
        $tags = self::$dbApi->getPostTags($post['cid']);
        $thumb = self::$dbApi->getThumbnail($post['text']);
        $excerpt = Typecho_Common::subStr(strip_tags($post['text']), 0, 150, '...');

        $formattedPost = [
            'id' => $post['cid'],
            'title' => $post['title'],
            'slug' => $post['slug'],
            'created' => date('Y-m-d H:i:s', $post['created']),
            'modified' => date('Y-m-d H:i:s', $post['modified']),
            'thumb' => $thumb,
            'excerpt' => $excerpt,
            'commentsNum' => intval($post['commentsNum']),
            'categories' => $categories,
            'tags' => $tags,
        ];

        if ($includeContent) {
            $formattedPost['content'] = $post['text'];
        }

        return $formattedPost;
    }
    
    private static function getSiteInfo()
    {
        return [
            'theme' => Get::Options('theme'),
            'title' => Get::Options('title'),
            'description' => Get::Options('description'),
            'keywords' => Get::Options('keywords'),
            'siteUrl' => Get::Options('siteUrl'),
            'timezone' => Get::Options('timezone'),
            'lang' => Get::Options('lang', true) ? Get::Options('lang', true) : 'zh-CN',
        ];
    }
}

// 注册路由
$requestUri = $_SERVER['REQUEST_URI'];
$basePath = '/' . __TTDF_RESTAPI_ROUTE__;
if (strpos($requestUri, $basePath) === 0) {
    TTDF_API::handleRequest();
}
