<?php
/**
 * Json API Functions
 * @author 鼠子Tomoriゞ
 * @link https://blog.miomoe.cn/
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

function outputJsonData() {
    if (!isset($_GET['JsonData'])) {
        return;
    }

    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    
    $response = [
        'code' => 200,
        'message' => 'success',
        'data' => null
    ];

    try {
        $db = Typecho_Db::get();
        $pageSize = 10;
        $currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

        if ($_GET['JsonData'] === 'page') {
            // 获取文章列表
            $query = $db->select()->from('table.contents')
                ->where('status = ?', 'publish')
                ->where('type = ?', 'post')
                ->order('created', Typecho_Db::SORT_DESC)
                ->page($currentPage, $pageSize);

            $total = $db->fetchObject($db->select(['COUNT(*)' => 'total'])
                ->from('table.contents')
                ->where('status = ?', 'publish')
                ->where('type = ?', 'post'))->total;

            $posts = $db->fetchAll($query);
            $postList = [];

            foreach ($posts as $post) {
                // 获取分类
                $categories = [];
                $cateQuery = $db->select()->from('table.metas')
                    ->join('table.relationships', 'table.metas.mid = table.relationships.mid')
                    ->where('table.relationships.cid = ?', $post['cid'])
                    ->where('table.metas.type = ?', 'category');
                $cates = $db->fetchAll($cateQuery);
                foreach ($cates as $cate) {
                    $categories[] = [
                        'id' => $cate['mid'],
                        'name' => $cate['name'],
                        'slug' => $cate['slug']
                    ];
                }

                // 获取标签
                $tags = [];
                $tagQuery = $db->select()->from('table.metas')
                    ->join('table.relationships', 'table.metas.mid = table.relationships.mid')
                    ->where('table.relationships.cid = ?', $post['cid'])
                    ->where('table.metas.type = ?', 'tag');
                $tagResults = $db->fetchAll($tagQuery);
                foreach ($tagResults as $tag) {
                    $tags[] = [
                        'id' => $tag['mid'],
                        'name' => $tag['name'],
                        'slug' => $tag['slug']
                    ];
                }

                // 获取文章缩略图
                $thumb = '';
                if (preg_match('/\[thumb\](.*?)\[\/thumb\]/', $post['text'], $matches)) {
                    $thumb = $matches[1];
                } elseif (preg_match('/<img.*?src="(.*?)"/', $post['text'], $matches)) {
                    $thumb = $matches[1];
                }

                // 生成文章摘要
                $abstract = Typecho_Common::subStr(strip_tags($post['text']), 0, 150, '...');

                $postList[] = [
                    'id' => $post['cid'],
                    'title' => $post['title'],
                    'slug' => $post['slug'],
                    'created' => date('Y-m-d H:i:s', $post['created']),
                    'modified' => date('Y-m-d H:i:s', $post['modified']),
                    'thumb' => $thumb,
                    'abstract' => $abstract,
                    'views' => intval($post['views']),
                    'commentsNum' => intval($post['commentsNum']),
                    'categories' => $categories,
                    'tags' => $tags,
                    'url' => Typecho_Common::url($post['slug'], Helper::options()->siteUrl),
                    'api' => Helper::options()->siteUrl . '?JsonData=common&cid=' . $post['cid']
                ];
            }

            $response['data'] = [
                'list' => $postList,
                'pagination' => [
                    'total' => (int)$total,
                    'pageSize' => $pageSize,
                    'currentPage' => $currentPage,
                    'totalPages' => ceil($total / $pageSize)
                ],
                'site' => [
                    'theme' => Get::Options('theme'),
                    'title' => Get::Options('title'),
                    'description' => Get::Options('description'),
                    'keywords' => Get::Options('keywords'),
                    'favicon' => Get::Options('faviconUrl'),
                    'siteUrl' => Get::Options('siteUrl'),
                    'timezone' => Get::Options('timezone'),
                    'lang' => 'zh-CN',
                ]
            ];
        } elseif ($_GET['JsonData'] === 'common' && isset($_GET['cid'])) {
            // 获取文章详情
            $cid = intval($_GET['cid']);
            $post = $db->fetchRow($db->select()->from('table.contents')->where('cid = ?', $cid)->limit(1));

            if ($post) {
                // 获取分类和标签
                $categories = [];
                $cateQuery = $db->select()->from('table.metas')
                    ->join('table.relationships', 'table.metas.mid = table.relationships.mid')
                    ->where('table.relationships.cid = ?', $post['cid'])
                    ->where('table.metas.type = ?', 'category');
                $cates = $db->fetchAll($cateQuery);
                foreach ($cates as $cate) {
                    $categories[] = [
                        'id' => $cate['mid'],
                        'name' => $cate['name'],
                        'slug' => $cate['slug']
                    ];
                }

                $tags = [];
                $tagQuery = $db->select()->from('table.metas')
                    ->join('table.relationships', 'table.metas.mid = table.relationships.mid')
                    ->where('table.relationships.cid = ?', $post['cid'])
                    ->where('table.metas.type = ?', 'tag');
                $tagResults = $db->fetchAll($tagQuery);
                foreach ($tagResults as $tag) {
                    $tags[] = [
                        'id' => $tag['mid'],
                        'name' => $tag['name'],
                        'slug' => $tag['slug']
                    ];
                }

                $response['data'] = [
                    'id' => $post['cid'],
                    'title' => $post['title'],
                    'slug' => $post['slug'],
                    'created' => date('Y-m-d H:i:s', $post['created']),
                    'modified' => date('Y-m-d H:i:s', $post['modified']),
                    'content' => $post['text'],
                    'views' => intval($post['views']),
                    'commentsNum' => intval($post['commentsNum']),
                    'categories' => $categories,
                    'tags' => $tags,
                    'url' => Typecho_Common::url($post['slug'], Helper::options()->siteUrl)
                ];
            } else {
                $response['code'] = 404;
                $response['message'] = 'Post not found';
            }
        } elseif ($_GET['JsonData'] === 'category') {
            if (isset($_GET['cid'])) {
                // 获取特定分类下的文章
                $cid = intval($_GET['cid']);
                $query = $db->select()->from('table.contents')
                    ->join('table.relationships', 'table.contents.cid = table.relationships.cid')
                    ->where('table.relationships.mid = ?', $cid)
                    ->where('table.contents.status = ?', 'publish')
                    ->where('table.contents.type = ?', 'post')
                    ->order('table.contents.created', Typecho_Db::SORT_DESC)
                    ->page($currentPage, $pageSize);

                $total = $db->fetchObject($db->select(['count(table.contents.cid)' => 'total'])
                    ->from('table.contents')
                    ->join('table.relationships', 'table.contents.cid = table.relationships.cid')
                    ->where('table.relationships.mid = ?', $cid)
                    ->where('table.contents.status = ?', 'publish')
                    ->where('table.contents.type = ?', 'post'))->total;

                $posts = $db->fetchAll($query);
                $postList = [];

                foreach ($posts as $post) {
                    $postList[] = [
                        'id' => $post['cid'],
                        'title' => $post['title'],
                        'created' => date('Y-m-d H:i:s', $post['created']),
                        'url' => Typecho_Common::url($post['slug'], Helper::options()->siteUrl),
                        'api' => Helper::options()->siteUrl . '?JsonData=common&cid=' . $post['cid']
                    ];
                }

                $response['data'] = [
                    'list' => $postList,
                    'pagination' => [
                        'total' => (int)$total,
                        'pageSize' => $pageSize,
                        'currentPage' => $currentPage,
                        'totalPages' => ceil($total / $pageSize)
                    ]
                ];
            } else {
                // 获取所有分类
                $query = $db->select()->from('table.metas')
                    ->where('type = ?', 'category')
                    ->order('order', Typecho_Db::SORT_ASC);

                $categories = $db->fetchAll($query);
                $categoryList = [];

                foreach ($categories as $category) {
                    $categoryList[] = [
                        'id' => $category['mid'],
                        'name' => $category['name'],
                        'slug' => $category['slug'],
                        'description' => $category['description'],
                        'count' => $category['count'],
                        'url' => Helper::options()->siteUrl . '?JsonData=category&cid=' . $category['mid']
                    ];
                }

                $response['data'] = $categoryList;
            }
        } elseif ($_GET['JsonData'] === 'tag') {
            if (isset($_GET['tid'])) {
                // 获取特定标签下的文章
                $tid = intval($_GET['tid']);
                $query = $db->select()->from('table.contents')
                    ->join('table.relationships', 'table.contents.cid = table.relationships.cid')
                    ->where('table.relationships.mid = ?', $tid)
                    ->where('table.contents.status = ?', 'publish')
                    ->where('table.contents.type = ?', 'post')
                    ->order('table.contents.created', Typecho_Db::SORT_DESC)
                    ->page($currentPage, $pageSize);

                $total = $db->fetchObject($db->select(['count(table.contents.cid)' => 'total'])
                    ->from('table.contents')
                    ->join('table.relationships', 'table.contents.cid = table.relationships.cid')
                    ->where('table.relationships.mid = ?', $tid)
                    ->where('table.contents.status = ?', 'publish')
                    ->where('table.contents.type = ?', 'post'))->total;

                $posts = $db->fetchAll($query);
                $postList = [];

                foreach ($posts as $post) {
                    $postList[] = [
                        'id' => $post['cid'],
                        'title' => $post['title'],
                        'created' => date('Y-m-d H:i:s', $post['created']),
                        'url' => Typecho_Common::url($post['slug'], Helper::options()->siteUrl),
                        'api' => Helper::options()->siteUrl . '?JsonData=common&cid=' . $post['cid']
                    ];
                }

                $response['data'] = [
                    'list' => $postList,
                    'pagination' => [
                        'total' => (int)$total,
                        'pageSize' => $pageSize,
                        'currentPage' => $currentPage,
                        'totalPages' => ceil($total / $pageSize)
                    ]
                ];
            } else {
                // 获取所有标签
                $query = $db->select()->from('table.metas')
                    ->where('type = ?', 'tag')
                    ->order('count', Typecho_Db::SORT_DESC);

                $tags = $db->fetchAll($query);
                $tagList = [];

                foreach ($tags as $tag) {
                    $tagList[] = [
                        'id' => $tag['mid'],
                        'name' => $tag['name'],
                        'slug' => $tag['slug'],
                        'count' => $tag['count'],
                        'url' => Helper::options()->siteUrl . '?JsonData=tag&tid=' . $tag['mid']
                    ];
                }

                $response['data'] = $tagList;
            }
        } else {
            $response['code'] = 400;
            $response['message'] = 'Invalid JsonData parameter';
        }

    } catch (Exception $e) {
        $response['code'] = 500;
        $response['message'] = 'Internal Server Error';
        $response['error'] = $e->getMessage();
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
