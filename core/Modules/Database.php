<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class DB
{
    private static ?self $instance = null;
    private Typecho_Db $db;

    private function __construct()
    {
        $this->db = Typecho_Db::get();
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public static function init()
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();

        try {
            // 尝试查询表，如果失败则创建
            $db->fetchRow($db->select()->from('table.ttdf')->limit(1));
        } catch (Exception $e) {
            // 表不存在，创建表
            $sql = "CREATE TABLE `{$prefix}ttdf` (
            `tid` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `name` varchar(200) NOT NULL,
            `value` text,
            PRIMARY KEY (`tid`),
            UNIQUE KEY `name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8";

            $db->query($sql);

            // 插入默认数据
            $siteUrl = Helper::options()->siteUrl;
            self::setTtdf('siteUrl', $siteUrl);
        }
    }

    // 添加或更新数据
    public static function setTtdf($name, $value)
    {
        $db = Typecho_Db::get();

        // 检查是否已存在
        $exists = $db->fetchRow($db->select()->from('table.ttdf')->where('name = ?', $name));

        if ($exists) {
            // 更新
            $db->query($db->update('table.ttdf')->rows(array('value' => $value))->where('name = ?', $name));
        } else {
            // 新增
            $db->query($db->insert('table.ttdf')->rows(array(
                'name' => $name,
                'value' => $value
            )));
        }
    }

    // 获取数据
    public static function getTtdf($name, $default = null)
    {
        $db = Typecho_Db::get();
        $row = $db->fetchRow($db->select('value')->from('table.ttdf')->where('name = ?', $name));
        return $row ? $row['value'] : $default;
    }

    // 删除数据
    public static function deleteTtdf($name)
    {
        $db = Typecho_Db::get();
        $db->query($db->delete('table.ttdf')->where('name = ?', $name));
    }

    // 获取所有数据
    public static function getAllTtdf()
    {
        $db = Typecho_Db::get();
        $rows = $db->fetchAll($db->select()->from('table.ttdf'));
        $result = array();
        foreach ($rows as $row) {
            $result[$row['name']] = $row['value'];
        }
        return $result;
    }

    /**
     * 获取文章内容/字数
     */
    public function getArticleContent(int $cid): string
    {
        $rs = $this->db->fetchRow($this->db->select('text')
            ->from('table.contents')
            ->where('cid = ?', $cid)
            ->limit(1));
        return $rs['text'] ?? '';
    }

    /**
     * 获取文章标题
     */
    public function getArticleTitle(int $cid): string
    {
        $rs = $this->db->fetchRow($this->db->select('title')
            ->from('table.contents')
            ->where('cid = ?', $cid)
            ->limit(1));
        return $rs['title'] ?? '';
    }

    /**
     * 获取文章分类
     */
    public function getPostCategories(int $cid): string
    {
        $categories = $this->db->fetchAll($this->db->select('name')
            ->from('table.metas')
            ->join('table.relationships', 'table.metas.mid = table.relationships.mid')
            ->where('table.relationships.cid = ? AND table.metas.type = ?', $cid, 'category'));

        return implode(', ', array_column($categories, 'name'));
    }

    /**
     * 获取文章数量
     */
    public function getArticleCount(): int
    {
        $rs = $this->db->fetchRow($this->db->select(['COUNT(*)' => 'count'])
            ->from('table.contents')
            ->where('type = ?', 'post'));
        return (int)($rs['count'] ?? 0);
    }

    /**
     * 获取文章列表
     */
    public function getPostList(int $pageSize, int $currentPage): array
    {
        return $this->db->fetchAll($this->db->select()
            ->from('table.contents')
            ->where('status = ? AND type = ?', 'publish', 'post')
            ->order('created', Typecho_Db::SORT_DESC)
            ->page($currentPage, $pageSize));
    }

    /**
     * 获取随机文章列表
     */
    public function getRandomPosts(int $limit): array
    {
        $posts = $this->db->fetchAll($this->db->select()
            ->from('table.contents')
            ->where("password IS NULL OR password = ''")
            ->where('status = ? AND created <= ? AND type = ?', 'publish', time(), 'post')
            ->limit($limit)
            ->order('RAND()'));

        return array_map(fn($post) => [
            'cid' => $post['cid'],
            'title' => $post['title'],
            'permalink' => Typecho_Router::url('post', ['cid' => $post['cid']], Typecho_Common::url('', Helper::options()->index)),
            'created' => $post['created'],
            'category' => $this->getPostCategories($post['cid']),
        ], $posts);
    }
}

class DB_API
{
    private Typecho_Db $db;

    public function __construct()
    {
        $this->db = Typecho_Db::get();
    }

    /**
     * 获取内容通用方法
     */
    private function getContent(string $field, int $cid): string
    {
        $rs = $this->db->fetchRow($this->db->select($field)
            ->from('table.contents')
            ->where('cid = ?', $cid)
            ->limit(1));
        return $rs[$field] ?? '';
    }

    public function getArticleText(int $cid): string
    {
        return $this->getContent('text', $cid);
    }

    public function getArticleTitle(int $cid): string
    {
        return $this->getContent('title', $cid);
    }

    public function getArticleContent(int $cid): string
    {
        return $this->getContent('text', $cid);
    }

    /**
     * 获取数量通用方法
     */
    private function getCount(string $table, ?string $where = null, ...$args): int
    {
        $query = $this->db->select(['COUNT(*)' => 'count'])->from($table);
        if ($where) {
            $query->where($where, ...$args);
        }
        $rs = $this->db->fetchRow($query);
        return (int)($rs['count'] ?? 0);
    }

    public function getArticleCount(): int
    {
        return $this->getCount('table.contents', 'type = ?', 'post');
    }

    public function getTotalPages(): int
    {
        return $this->getCount('table.contents', 'type = ?', 'page');
    }

    public function getTotalPosts(): int
    {
        return $this->getCount('table.contents', 'status = ? AND type = ?', 'publish', 'post');
    }

    public function getTotalPostsInCategory(int $mid): int
    {
        $query = $this->db->select(['COUNT(*)' => 'count'])
            ->from('table.contents')
            ->join('table.relationships', 'table.contents.cid = table.relationships.cid')
            ->where(
                'table.relationships.mid = ? AND table.contents.status = ? AND table.contents.type = ?',
                $mid,
                'publish',
                'post'
            );
        $rs = $this->db->fetchRow($query);
        return (int)($rs['count'] ?? 0);
    }

    public function getTotalPostsInTag(int $mid): int
    {
        return $this->getTotalPostsInCategory($mid); // 逻辑相同
    }

    /**
     * 获取列表通用方法
     */
    private function getList(string $table, array $conditions, int $pageSize, int $currentPage, string $order = 'created', string $sort = Typecho_Db::SORT_DESC): array
    {
        $query = $this->db->select()->from($table);

        foreach (array_chunk($conditions, 3) as [$field, $op, $value]) {
            $query->where("{$field} {$op} ?", $value);
        }

        return $this->db->fetchAll($query->order($order, $sort)->page($currentPage, $pageSize));
    }

    public function getPostList(int $pageSize, int $currentPage): array
    {
        return $this->getList('table.contents', [
            'status',
            '=',
            'publish',
            'type',
            '=',
            'post'
        ], $pageSize, $currentPage);
    }

    public function getAllPages(int $pageSize, int $currentPage): array
    {
        return $this->getList('table.contents', [
            'type',
            '=',
            'page'
        ], $pageSize, $currentPage);
    }

    public function getPostsInCategory(int $mid, int $pageSize = 10, int $currentPage = 1): array
    {
        return $this->db->fetchAll($this->db->select()
            ->from('table.contents')
            ->join('table.relationships', 'table.contents.cid = table.relationships.cid')
            ->where(
                'table.relationships.mid = ? AND table.contents.status = ? AND table.contents.type = ?',
                $mid,
                'publish',
                'post'
            )
            ->order('table.contents.created', Typecho_Db::SORT_DESC)
            ->page($currentPage, $pageSize));
    }

    public function getPostsInTag(int $mid, int $pageSize = 10, int $currentPage = 1): array
    {
        return $this->getPostsInCategory($mid, $pageSize, $currentPage);
    }

    /**
     * 获取分类/标签通用方法
     */
    public function getAllCategories(): array
    {
        return $this->db->fetchAll($this->db->select()
            ->from('table.metas')
            ->where('type = ?', 'category')
            ->order('order', Typecho_Db::SORT_ASC));
    }

    public function getAllTags(): array
    {
        return $this->db->fetchAll($this->db->select()
            ->from('table.metas')
            ->where('type = ?', 'tag')
            ->order('count', Typecho_Db::SORT_DESC));
    }

    private function getMetaBy(string $field, $value, string $type): ?array
    {
        return $this->db->fetchRow($this->db->select()
            ->from('table.metas')
            ->where("{$field} = ? AND type = ?", $value, $type)
            ->limit(1));
    }

    public function getCategoryBySlug(string $slug): ?array
    {
        return $this->getMetaBy('slug', $slug, 'category');
    }

    public function getCategoryByMid(int $mid): ?array
    {
        return $this->getMetaBy('mid', $mid, 'category');
    }

    public function getTagBySlug(string $slug): ?array
    {
        return $this->getMetaBy('slug', $slug, 'tag');
    }

    public function getTagByMid(int $mid): ?array
    {
        return $this->getMetaBy('mid', $mid, 'tag');
    }

    /**
     * 获取文章详情
     */
    public function getPostDetail(int $cid): ?array
    {
        return $this->db->fetchRow($this->db->select()
            ->from('table.contents')
            ->where('cid = ?', $cid)
            ->limit(1));
    }

    public function getPostDetailBySlug(string $slug): ?array
    {
        return $this->db->fetchRow($this->db->select()
            ->from('table.contents')
            ->where('slug = ?', $slug)
            ->limit(1));
    }

    /**
     * 获取文章关联数据
     */
    private function getPostRelations(int $cid, string $type): array
    {
        return $this->db->fetchAll($this->db->select()
            ->from('table.metas')
            ->join('table.relationships', 'table.metas.mid = table.relationships.mid')
            ->where('table.relationships.cid = ? AND table.metas.type = ?', $cid, $type));
    }

    public function getPostCategories(int $cid): array
    {
        return $this->getPostRelations($cid, 'category');
    }

    public function getPostTags(int $cid): array
    {
        return $this->getPostRelations($cid, 'tag');
    }

    /**
     * 获取文章自定义字段
     */
    public function getPostFields(int $cid): array
    {
        $fields = $this->db->fetchAll($this->db->select()
            ->from('table.fields')
            ->where('cid = ?', $cid));

        $result = [];
        foreach ($fields as $field) {
            $valueField = $field['type'] . '_value';
            $result[$field['name']] = $field[$valueField] ?? null;
        }

        return $result;
    }

    /**
     * 高级查询方法
     */
    public function getPostsByField(string $fieldName, $fieldValue, int $pageSize, int $currentPage): array
    {
        return $this->db->fetchAll($this->db->select('DISTINCT table.contents.*')
            ->from('table.contents')
            ->join('table.fields', 'table.contents.cid = table.fields.cid')
            ->where(
                'table.fields.name = ? AND table.fields.str_value = ? AND table.contents.status = ? AND table.contents.type = ?',
                $fieldName,
                $fieldValue,
                'publish',
                'post'
            )
            ->order('table.contents.created', Typecho_Db::SORT_DESC)
            ->page($currentPage, $pageSize));
    }

    public function getPostsCountByField(string $fieldName, $fieldValue): int
    {
        $query = $this->db->select(['COUNT(DISTINCT table.contents.cid)' => 'count'])
            ->from('table.contents')
            ->join('table.fields', 'table.contents.cid = table.fields.cid')
            ->where(
                'table.fields.name = ? AND table.fields.str_value = ? AND table.contents.status = ? AND table.contents.type = ?',
                $fieldName,
                $fieldValue,
                'publish',
                'post'
            );
        $rs = $this->db->fetchRow($query);
        return (int)($rs['count'] ?? 0);
    }

    public function getPostsByAdvancedFields(array $conditions, int $pageSize, int $currentPage): array
    {
        $query = $this->db->select('DISTINCT table.contents.*')
            ->from('table.contents')
            ->join('table.fields', 'table.contents.cid = table.fields.cid')
            ->where('status = ? AND type = ?', 'publish', 'post');

        foreach ($conditions as $condition) {
            $fieldName = $condition['name'] ?? '';
            $operator = $condition['operator'] ?? '=';
            $value = $condition['value'] ?? '';
            $valueType = $condition['value_type'] ?? 'str';

            if (!in_array($operator, ['=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN'])) {
                continue;
            }

            $valueField = $valueType . '_value';
            $where = "table.fields.name = ? AND table.fields.{$valueField} {$operator} ?";

            if (in_array($operator, ['IN', 'NOT IN'])) {
                $value = is_array($value) ? $value : explode(',', $value);
                $placeholders = implode(',', array_fill(0, count($value), '?'));
                $where = "table.fields.name = ? AND table.fields.{$valueField} {$operator} ({$placeholders})";
            }

            $query->where($where, $fieldName, ...(array)$value);
        }

        return $this->db->fetchAll($query->order('created', Typecho_Db::SORT_DESC)->page($currentPage, $pageSize));
    }

    public function getPostsCountByAdvancedFields(array $conditions): int
    {
        $query = $this->db->select(['COUNT(DISTINCT table.contents.cid)' => 'count'])
            ->from('table.contents')
            ->join('table.fields', 'table.contents.cid = table.fields.cid')
            ->where('status = ? AND type = ?', 'publish', 'post');

        foreach ($conditions as $condition) {
            $fieldName = $condition['name'] ?? '';
            $operator = $condition['operator'] ?? '=';
            $value = $condition['value'] ?? '';
            $valueType = $condition['value_type'] ?? 'str';

            if (!in_array($operator, ['=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN'])) {
                continue;
            }

            $valueField = $valueType . '_value';
            $where = "table.fields.name = ? AND table.fields.{$valueField} {$operator} ?";

            if (in_array($operator, ['IN', 'NOT IN'])) {
                $value = is_array($value) ? $value : explode(',', $value);
                $placeholders = implode(',', array_fill(0, count($value), '?'));
                $where = "table.fields.name = ? AND table.fields.{$valueField} {$operator} ({$placeholders})";
            }

            $query->where($where, $fieldName, ...(array)$value);
        }

        $rs = $this->db->fetchRow($query);
        return (int)($rs['count'] ?? 0);
    }

    /**
     * 搜索文章
     */
    public function searchPosts(string $keyword, int $pageSize, int $currentPage): array
    {
        try {
            $searchKeyword = '%' . str_replace(' ', '%', Typecho_Common::filterSearchQuery($keyword)) . '%';

            return $this->db->fetchAll($this->db->select()
                ->from('table.contents')
                ->where('status = ? AND type = ? AND (title LIKE ? OR text LIKE ?)', 'publish', 'post', $searchKeyword, $searchKeyword)
                ->order('created', Typecho_Db::SORT_DESC)
                ->page($currentPage, $pageSize));
        } catch (Exception $e) {
            error_log("Database search error: " . $e->getMessage());
            return [];
        }
    }

    public function getSearchPostsCount(string $keyword): int
    {
        try {
            $searchKeyword = '%' . str_replace(' ', '%', Typecho_Common::filterSearchQuery($keyword)) . '%';
            return $this->getCount(
                'table.contents',
                'status = ? AND type = ? AND (title LIKE ? OR text LIKE ?)',
                'publish',
                'post',
                $searchKeyword,
                $searchKeyword
            );
        } catch (Exception $e) {
            error_log("Count search error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * 评论相关方法
     */
    public function getAllComments(int $pageSize, int $currentPage): array
    {
        return $this->getList('table.comments', [], $pageSize, $currentPage);
    }

    public function getTotalComments(): int
    {
        return $this->getCount('table.comments');
    }

    public function getPostComments(int $cid, int $pageSize, int $currentPage): array
    {
        return $this->getList('table.comments', [
            'cid',
            '=',
            $cid
        ], $pageSize, $currentPage, 'created', Typecho_Db::SORT_ASC);
    }

    public function getTotalPostComments(int $cid): int
    {
        return $this->getCount('table.comments', 'cid = ?', $cid);
    }

    /**
     * 获取评论详情
     *
     * @param int $commentId
     * @return array|null
     */
    public function getCommentById(int $commentId): ?array
    {
        try {
            return $this->db->fetchRow($this->db->select()
                ->from('table.comments')
                ->where('coid = ?', $commentId)
                ->limit(1));
        } catch (Exception $e) {
            error_log("Database error in getCommentById: " . $e->getMessage());
            return null;
        }
    }

    /**
     * 插入评论
     */
    public function insertComment(array $commentData): int
    {
        try {
            return $this->db->query($this->db->insert('table.comments')->rows($commentData));
        } catch (Exception $e) {
            error_log("插入评论失败: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 附件相关方法
     */
    public function getAllAttachments(int $pageSize, int $currentPage): array
    {
        return $this->getList('table.contents', [
            'type',
            '=',
            'attachment'
        ], $pageSize, $currentPage);
    }

    public function getTotalAttachments(): int
    {
        return $this->getCount('table.contents', 'type = ?', 'attachment');
    }
}
