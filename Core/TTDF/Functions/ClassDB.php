<?php

/**
 * DB Class
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class DB
{
    private static $instance = null;
    private $db;

    private function __construct()
    {
        $this->db = Typecho_Db::get();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }


    /**
     * 获取文章字数
     * @param int $cid 文章cid
     * @return int
     */
    public function getArticleText($cid)
    {
        $rs = $this->db->fetchRow($this->db->select('table.contents.text')
            ->from('table.contents')
            ->where('table.contents.cid = ?', $cid)
            ->order('table.contents.cid', Typecho_Db::SORT_ASC)
            ->limit(1));
        return $rs['text'] ?? '';
    }

    /**
     * 获取文章标题
     * @param int $cid 文章cid
     * @return string
     * @throws Typecho_Db_Exception
     */
    public function getArticleTitle($cid)
    {
        $rs = $this->db->fetchRow($this->db->select('table.contents.title')
            ->from('table.contents')
            ->where('table.contents.cid = ?', $cid)
            ->order('table.contents.cid', Typecho_Db::SORT_ASC)
            ->limit(1));
        return $rs['title'] ?? '';
    }

    /**
     * 获取文章内容
     * @param int $cid 文章cid
     * @return string
     * @throws Typecho_Db_Exception
     */
    public function getArticleContent($cid)
    {
        $rs = $this->db->fetchRow($this->db->select('table.contents.text')
            ->from('table.contents')
            ->where('table.contents.cid = ?', $cid)
            ->order('table.contents.cid', Typecho_Db::SORT_ASC)
            ->limit(1));
        return $rs['text'] ?? '';
    }

    /**
     * 获取文章分类
     * @param int $cid 文章ID
     * @return string 返回分类名称，多个分类用逗号分隔
     */
    public function getPostCategories($cid)
    {
        $query = $this->db->select()->from('table.metas')
            ->join('table.relationships', 'table.metas.mid = table.relationships.mid')
            ->where('table.relationships.cid = ?', $cid)
            ->where('table.metas.type = ?', 'category');

        $categories = $this->db->fetchAll($query);

        // 提取分类名称并拼接成字符串
        $categoryNames = array_map(function ($category) {
            return $category['name'];
        }, $categories);

        return implode(', ', $categoryNames);
    }

    /**
     * 获取文章数量
     */
    public function getArticleCount()
    {
        $rs = $this->db->fetchRow($this->db->select('COUNT(*)')
            ->from('table.contents')
            ->where('table.contents.type = ?', 'post')
            ->order('table.contents.cid', Typecho_Db::SORT_ASC)
            ->limit(1));
        return $rs['COUNT(*)'] ?? 0;
    }

    /**
     * 获取文章列表
     * @param int $pageSize 每页数量
     * @param int $currentPage 当前页码
     * @returnarray
     */
    public function getPostList($pageSize, $currentPage)
    {
        $query = $this->db->select()->from('table.contents')
            ->where('status = ?', 'publish')
            ->where('type = ?', 'post')
            ->order('created', Typecho_Db::SORT_DESC)
            ->page($currentPage, $pageSize);

        return $this->db->fetchAll($query);
    }

    /**
     * 获取随机文章列表
     * @param int $pageSize 随机文章数量
     * @return array 返回随机文章数组
     */
    public function getRandomPosts($pageSize)
    {
        $query = $this->db->select()->from('table.contents')
            ->where("table.contents.password IS NULL OR table.contents.password = ''")
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.created <= ?', time())
            ->where('table.contents.type = ?', 'post')
            ->limit($pageSize)
            ->order('RAND()');

        $posts = $this->db->fetchAll($query);

        // 将数组转换为对象并提取所需字段
        return array_map(function ($post) {
            return [
                'cid' => $post['cid'],
                'title' => $post['title'],
                'permalink' => Typecho_Router::url('post', ['cid' => $post['cid']], Typecho_Common::url('', Helper::options()->index)),
                'created' => $post['created'],
                'category' => $this->getPostCategories($post['cid']),
            ];
        }, $posts);
    }
}

class DB_API
{
    private $db;

    public function __construct()
    {
        $this->db = Typecho_Db::get();
    }

    /**
     * 获取文章字数
     * @param int $cid 文章cid
     * @return int
     */
    public function getArticleText($cid)
    {
        $rs = $this->db->fetchRow($this->db->select('table.contents.text')
            ->from('table.contents')
            ->where('table.contents.cid = ?', $cid)
            ->order('table.contents.cid', Typecho_Db::SORT_ASC)
            ->limit(1));
        return $rs['text'] ?? '';
    }

    /**
     * 获取文章标题
     * @param int $cid 文章cid
     * @return string
     */
    public function getArticleTitle($cid)
    {
        $rs = $this->db->fetchRow($this->db->select('table.contents.title')
            ->from('table.contents')
            ->where('table.contents.cid = ?', $cid)
            ->order('table.contents.cid', Typecho_Db::SORT_ASC)
            ->limit(1));
        return $rs['title'] ?? '';
    }

    /**
     * 获取文章内容
     * @param int $cid 文章cid
     * @return string
     */
    public function getArticleContent($cid)
    {
        $rs = $this->db->fetchRow($this->db->select('table.contents.text')
            ->from('table.contents')
            ->where('table.contents.cid = ?', $cid)
            ->order('table.contents.cid', Typecho_Db::SORT_ASC)
            ->limit(1));
        return $rs['text'] ?? '';
    }

    /**
     * 获取文章数量
     * @return int
     */
    public function getArticleCount()
    {
        $rs = $this->db->fetchRow($this->db->select('COUNT(*)')
            ->from('table.contents')
            ->where('table.contents.type = ?', 'post')
            ->order('table.contents.cid', Typecho_Db::SORT_ASC)
            ->limit(1));
        return $rs['COUNT(*)'] ?? 0;
    }

    /**
     * 获取文章列表
     * @param int $pageSize 每页数量
     * @param int $currentPage 当前页码
     * @return array
     */
    public function getPostList($pageSize, $currentPage)
    {
        $query = $this->db->select()->from('table.contents')
            ->where('status = ?', 'publish')
            ->where('type = ?', 'post')
            ->order('created', Typecho_Db::SORT_DESC)
            ->page($currentPage, $pageSize);

        return $this->db->fetchAll($query);
    }

    /**
     * 获取总文章数
     * @return int
     */
    public function getTotalPosts()
    {
        $rs = $this->db->fetchRow($this->db->select('COUNT(*)')
            ->from('table.contents')
            ->where('status = ?', 'publish')
            ->where('type = ?', 'post'));
        return $rs['COUNT(*)'] ?? 0;
    }

    /**
     * 获取分类下的文章列表
     * @param int $cid 分类ID
     * @return array
     */
    public function getPostsInCategory($cid)
    {
        $query = $this->db->select()->from('table.contents')
            ->join('table.relationships', 'table.contents.cid = table.relationships.cid')
            ->where('table.relationships.mid = ?', $cid)
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.type = ?', 'post')
            ->order('table.contents.created', Typecho_Db::SORT_DESC);

        return $this->db->fetchAll($query);
    }

    /**
     * 获取分类下的文章总数
     * @param int $cid 分类ID
     * @return int
     */
    public function getTotalPostsInCategory($cid)
    {
        $rs = $this->db->fetchRow($this->db->select('COUNT(*)')
            ->from('table.contents')
            ->join('table.relationships', 'table.contents.cid = table.relationships.cid')
            ->where('table.relationships.mid = ?', $cid)
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.type = ?', 'post'));
        return $rs['COUNT(*)'] ?? 0;
    }

    /**
     * 获取标签下的文章列表
     * @param int $tid 标签ID
     * @return array
     */
    public function getPostsInTag($tid)
    {
        $query = $this->db->select()->from('table.contents')
            ->join('table.relationships', 'table.contents.cid = table.relationships.cid')
            ->where('table.relationships.mid = ?', $tid)
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.type = ?', 'post')
            ->order('table.contents.created', Typecho_Db::SORT_DESC);

        return $this->db->fetchAll($query);
    }

    /**
     * 获取标签下的文章总数
     * @param int $tid 标签ID
     * @return int
     */
    public function getTotalPostsInTag($tid)
    {
        $rs = $this->db->fetchRow($this->db->select('COUNT(*)')
            ->from('table.contents')
            ->join('table.relationships', 'table.contents.cid = table.relationships.cid')
            ->where('table.relationships.mid = ?', $tid)
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.type = ?', 'post'));
        return $rs['COUNT(*)'] ?? 0;
    }

    /**
     * 获取所有分类
     * @return array
     */
    public function getAllCategories()
    {
        $query = $this->db->select()->from('table.metas')
            ->where('type = ?', 'category')
            ->order('order', Typecho_Db::SORT_ASC);

        return $this->db->fetchAll($query);
    }

    /**
     * 获取所有标签
     * @return array
     */
    public function getAllTags()
    {
        $query = $this->db->select()->from('table.metas')
            ->where('type = ?', 'tag')
            ->order('count', Typecho_Db::SORT_DESC);

        return $this->db->fetchAll($query);
    }

    /**
     * 获取文章详情
     * @param int $cid 文章ID
     * @return array|null
     */
    public function getPostDetail($cid)
    {
        return $this->db->fetchRow($this->db->select()->from('table.contents')->where('cid = ?', $cid)->limit(1));
    }

    /**
     * 获取文章分类
     * @param int $cid 文章ID
     * @return array
     */
    public function getPostCategories($cid)
    {
        $query = $this->db->select()->from('table.metas')
            ->join('table.relationships', 'table.metas.mid = table.relationships.mid')
            ->where('table.relationships.cid = ?', $cid)
            ->where('table.metas.type = ?', 'category');

        return $this->db->fetchAll($query);
    }

    /**
     * 获取文章标签
     * @param int $cid 文章ID
     * @return array
     */
    public function getPostTags($cid)
    {
        $query = $this->db->select()->from('table.metas')
            ->join('table.relationships', 'table.metas.mid = table.relationships.mid')
            ->where('table.relationships.cid = ?', $cid)
            ->where('table.metas.type = ?', 'tag');

        return $this->db->fetchAll($query);
    }

    /**
     * 获取文章缩略图
     * @param string $content 文章内容
     * @return string
     */
    public function getThumbnail($content)
    {
        if (preg_match('/\[thumb\](.*?)\[\/thumb\]/', $content, $matches)) {
            return $matches[1];
        } elseif (preg_match('/<img.*?src="(.*?)"/', $content, $matches)) {
            return $matches[1];
        }
        return '';
    }
}
