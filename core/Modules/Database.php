<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// 确保错误处理已加载
if (!class_exists('TTDF_ErrorHandler')) {
    require_once __DIR__ . '/ErrorHandler.php';
}

/**
 * 数据库操作类
 * 提供安全的数据库操作接口，包含输入验证、缓存机制和错误处理
 */
class TTDF_Db
{
    private static ?self $instance = null;
    private Typecho_Db $db;

    /** @var TTDF_ErrorHandler 错误处理器实例 */
    private static $errorHandler;

    /** @var array 缓存数组 */
    private static array $cache = [];

    /** @var int 缓存过期时间（秒） */
    private const CACHE_TTL = 300; // 5分钟

    /** @var int 最大缓存条目数 */
    private const MAX_CACHE_SIZE = 100;

    private function __construct()
    {
        $this->db = Typecho_Db::get();
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * 验证字段名是否安全
     * @param string $name 字段名
     * @return bool
     */
    private static function validateFieldName(string $name): bool
    {
        // 只允许字母、数字、下划线，长度限制在1-100字符
        return preg_match('/^[a-zA-Z0-9_]{1,100}$/', $name) === 1;
    }

    /**
     * 清理缓存（当缓存过大时）
     */
    private static function cleanCache(): void
    {
        if (count(self::$cache) > self::MAX_CACHE_SIZE) {
            // 清理过期的缓存项
            $now = time();
            foreach (self::$cache as $key => $item) {
                if ($now - $item['timestamp'] > self::CACHE_TTL) {
                    unset(self::$cache[$key]);
                }
            }

            // 如果还是太多，清理最老的一半
            if (count(self::$cache) > self::MAX_CACHE_SIZE) {
                $keys = array_keys(self::$cache);
                $toRemove = array_slice($keys, 0, count($keys) / 2);
                foreach ($toRemove as $key) {
                    unset(self::$cache[$key]);
                }
            }
        }
    }

    /**
     * 获取缓存
     * @param string $key 缓存键
     * @return mixed|null
     */
    private static function getCache(string $key)
    {
        if (!isset(self::$cache[$key])) {
            return null;
        }

        $item = self::$cache[$key];
        if (time() - $item['timestamp'] > self::CACHE_TTL) {
            unset(self::$cache[$key]);
            return null;
        }

        return $item['value'];
    }

    /**
     * 设置缓存
     * @param string $key 缓存键
     * @param mixed $value 缓存值
     */
    private static function setCache(string $key, $value): void
    {
        self::cleanCache();
        self::$cache[$key] = [
            'value' => $value,
            'timestamp' => time()
        ];
    }

    /**
     * 清除指定缓存
     * @param string $key 缓存键
     */
    private static function clearCache(string $key): void
    {
        unset(self::$cache[$key]);
    }

    /**
     * 获取完整的字段名（主题名_字段名）
     * @param string $name 原字段名
     * @return string 完整字段名
     * @throws InvalidArgumentException
     */
    private static function getFullFieldName(string $name): string
    {
        if (!self::validateFieldName($name)) {
            throw new InvalidArgumentException("Invalid field name: $name");
        }

        try {
            // 获取主题名
            $themeName = Helper::options()->theme;

            // 如果主题名存在且不为空，则拼接
            if (!empty($themeName) && self::validateFieldName($themeName)) {
                return $themeName . '_' . $name;
            }

            // 如果没有主题名，直接返回原字段名
            return $name;
        } catch (Exception $e) {
            if (self::$errorHandler) {
                self::$errorHandler->warning('Failed to get theme name, using original field name', ['name' => $name], $e);
            }
            // 如果获取主题名失败，直接返回原字段名
            return $name;
        }
    }

    public static function init()
    {
        try {
            self::$errorHandler = TTDF_ErrorHandler::getInstance();
            $db = Typecho_Db::get();
            $prefix = $db->getPrefix();

            // 确保表存在
            self::ensureTableExists($db, $prefix);

            // 插入当前主题的默认设置项（每次都执行）
            self::insertThemeDefaultSettings($db);

            if (self::$errorHandler) {
                self::$errorHandler->info('Database initialization completed successfully');
            }
        } catch (Exception $e) {
            if (self::$errorHandler) {
                self::$errorHandler->fatal('Database initialization failed', [], $e);
            } else {
                error_log('TTDF Database init failed: ' . $e->getMessage());
            }
            throw $e;
        }
    }

    /**
     * 获取数据库类型
     * @param Typecho_Db $db 数据库实例
     * @return string 数据库类型 (mysql, sqlite, pgsql)
     */
    private static function getDatabaseType($db): string
    {
        $adapter = $db->getAdapter();
        $adapterName = get_class($adapter);

        if (strpos($adapterName, 'Mysql') !== false || strpos($adapterName, 'MySQL') !== false) {
            return 'mysql';
        } elseif (strpos($adapterName, 'SQLite') !== false || strpos($adapterName, 'Sqlite') !== false) {
            return 'sqlite';
        } elseif (strpos($adapterName, 'Pgsql') !== false || strpos($adapterName, 'PostgreSQL') !== false) {
            return 'pgsql';
        }

        // 默认返回mysql以保持向后兼容性
        return 'mysql';
    }

    /**
     * 根据数据库类型生成CREATE TABLE语句
     * @param string $dbType 数据库类型
     * @param string $prefix 表前缀
     * @return string SQL语句
     */
    private static function generateCreateTableSql(string $dbType, string $prefix): string
    {
        $tableName = $prefix . 'ttdf';

        switch ($dbType) {
            case 'sqlite':
                return "CREATE TABLE `{$tableName}` (
                    `tid` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `name` VARCHAR(200) NOT NULL UNIQUE,
                    `value` TEXT
                )";

            case 'pgsql':
                return "CREATE TABLE \"{$tableName}\" (
                    \"tid\" SERIAL PRIMARY KEY,
                    \"name\" VARCHAR(200) NOT NULL UNIQUE,
                    \"value\" TEXT
                )";

            case 'mysql':
            default:
                return "CREATE TABLE `{$tableName}` (
                    `tid` int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `name` varchar(200) NOT NULL,
                    `value` text,
                    PRIMARY KEY (`tid`),
                    UNIQUE KEY `name` (`name`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
        }
    }

    /**
     * 确保ttdf表存在，如果不存在则创建
     */
    private static function ensureTableExists($db, $prefix)
    {
        try {
            // 尝试查询表，如果失败则创建
            $db->fetchRow($db->select()->from('table.ttdf')->limit(1));
        } catch (Exception $e) {
            // 表不存在，创建表
            try {
                // 检测数据库类型
                $dbType = self::getDatabaseType($db);

                // 生成相应的CREATE TABLE语句
                $sql = self::generateCreateTableSql($dbType, $prefix);

                $db->query($sql);

                // 插入默认数据
                $db->query($db->insert('table.ttdf')->rows(array(
                    'name' => 'TTDF',
                    'value' => 'NB666'
                )));

                if (self::$errorHandler) {
                    self::$errorHandler->info("TTDF table created successfully for database type: {$dbType}");
                }
            } catch (Exception $createException) {
                if (self::$errorHandler) {
                    self::$errorHandler->error('Failed to create TTDF table', [
                        'database_type' => self::getDatabaseType($db),
                        'prefix' => $prefix
                    ], $createException);
                } else {
                    error_log('Failed to create TTDF table: ' . $createException->getMessage());
                }
                throw $createException;
            }
        }
    }

    /**
     * 插入当前主题的默认设置项
     */
    private static function insertThemeDefaultSettings($db)
    {
        try {
            $setupPath = __DIR__ . '/../../app/setup.php';
            if (!file_exists($setupPath)) {
                $setupPath = __DIR__ . '/../../app/Setup.php';
            }
            if (!file_exists($setupPath)) {
                return;
            }

            $tabs = require $setupPath;
            if (!is_array($tabs)) {
                return;
            }

            // 遍历所有设置项
            foreach ($tabs as $tab) {
                // 只处理有fields字段的标签页，跳过只有html的标签页
                if (!isset($tab['fields']) || !is_array($tab['fields'])) {
                    continue;
                }

                foreach ($tab['fields'] as $field) {
                    // 跳过HTML类型的字段和没有name的字段
                    if (!isset($field['name']) || !isset($field['value']) || $field['type'] === 'Html') {
                        continue;
                    }

                    $name = $field['name'];
                    $value = $field['value'];

                    // 处理复选框的数组默认值
                    if ($field['type'] === 'Checkbox' && is_array($value)) {
                        $value = implode(',', $value);
                    }

                    // 只有当值不为null时才处理
                    if ($value !== null) {
                        // 获取完整字段名（主题名_字段名）
                        $fullName = self::getFullFieldName($name);

                        // 检查是否已存在，如果不存在才插入
                        $exists = $db->fetchRow($db->select()->from('table.ttdf')->where('name = ?', $fullName));

                        if (!$exists) {
                            // 直接插入数据库，避免递归调用
                            $db->query($db->insert('table.ttdf')->rows(array(
                                'name' => $fullName,
                                'value' => $value
                            )));
                        }
                    }
                }
            }
        } catch (Exception $setupException) {
            // 如果Setup.php有问题，记录错误但不影响初始化
            if (self::$errorHandler) {
                self::$errorHandler->warning('Failed to load default settings from Setup.php', [], $setupException);
            } else {
                error_log('TTDF Database Init: Failed to load default settings from Setup.php - ' . $setupException->getMessage());
            }
        }
    }

    /**
     * 添加或更新数据
     * @param string $name 字段名
     * @param mixed $value 字段值
     * @throws InvalidArgumentException
     */
    public static function setTtdf(string $name, $value): void
    {
        if (!self::validateFieldName($name)) {
            throw new InvalidArgumentException("Invalid field name: $name");
        }

        try {
            $db = Typecho_Db::get();

            // 获取主题名并拼接字段名
            $fullName = self::getFullFieldName($name);

            // 清除相关缓存
            self::clearCache("ttdf_$fullName");
            self::clearCache("ttdf_$name");

            // 检查是否已存在
            $exists = $db->fetchRow($db->select()->from('table.ttdf')->where('name = ?', $fullName));

            if ($exists) {
                // 更新
                $db->query($db->update('table.ttdf')
                    ->rows(['value' => (string)$value])
                    ->where('name = ?', $fullName));
            } else {
                // 新增
                $db->query($db->insert('table.ttdf')->rows([
                    'name' => $fullName,
                    'value' => (string)$value
                ]));
            }

            if (self::$errorHandler) {
                self::$errorHandler->debug('TTDF data updated', ['name' => $fullName, 'value' => $value]);
            }
        } catch (Exception $e) {
            if (self::$errorHandler) {
                self::$errorHandler->error('Failed to set TTDF data', ['name' => $name, 'value' => $value], $e);
            }
            throw $e;
        }
    }

    /**
     * 获取数据（带缓存）
     * @param string $name 字段名
     * @param mixed $default 默认值
     * @return mixed
     * @throws InvalidArgumentException
     */
    public static function getTtdf(string $name, $default = null)
    {
        if (!self::validateFieldName($name)) {
            throw new InvalidArgumentException("Invalid field name: $name");
        }

        try {
            // 获取主题名并拼接字段名
            $fullName = self::getFullFieldName($name);

            // 检查缓存
            $cacheKey = "ttdf_$fullName";
            $cached = self::getCache($cacheKey);
            if ($cached !== null) {
                return $cached;
            }

            $db = Typecho_Db::get();

            // 首先尝试获取带主题名前缀的配置项
            $row = $db->fetchRow($db->select('value')->from('table.ttdf')->where('name = ?', $fullName));

            // 如果没有找到，则回退到原来的名称（向后兼容）
            if (!$row) {
                $row = $db->fetchRow($db->select('value')->from('table.ttdf')->where('name = ?', $name));
            }

            $result = $row ? $row['value'] : $default;

            // 缓存结果
            self::setCache($cacheKey, $result);

            return $result;
        } catch (Exception $e) {
            if (self::$errorHandler) {
                self::$errorHandler->error('Failed to get TTDF data', ['name' => $name], $e);
            }
            return $default;
        }
    }

    // 删除数据
    public static function deleteTtdf($name)
    {
        $db = Typecho_Db::get();

        // 获取主题名并拼接字段名
        $fullName = self::getFullFieldName($name);

        // 删除带主题名前缀的配置项
        $db->query($db->delete('table.ttdf')->where('name = ?', $fullName));

        // 同时删除原来的名称（向后兼容清理）
        $db->query($db->delete('table.ttdf')->where('name = ?', $name));
    }

    // 获取所有数据
    public static function getAllTtdf($currentThemeOnly = false)
    {
        $db = Typecho_Db::get();
        $rows = $db->fetchAll($db->select()->from('table.ttdf'));
        $result = array();

        if ($currentThemeOnly) {
            // 只获取当前主题的设置项
            try {
                $themeName = Helper::options()->theme;
                $prefix = $themeName . '_';

                foreach ($rows as $row) {
                    $name = $row['name'];
                    // 如果是当前主题的设置项，去掉前缀
                    if (strpos($name, $prefix) === 0) {
                        $shortName = substr($name, strlen($prefix));
                        $result[$shortName] = $row['value'];
                    }
                }
            } catch (Exception $e) {
                // 如果获取主题名失败，返回所有数据
                foreach ($rows as $row) {
                    $result[$row['name']] = $row['value'];
                }
            }
        } else {
            // 获取所有数据
            foreach ($rows as $row) {
                $result[$row['name']] = $row['value'];
            }
        }

        return $result;
    }

    /**
     * 获取文章内容/字数
     * @param int $cid 文章ID
     * @return string
     * @throws InvalidArgumentException
     */
    public function getArticleContent(int $cid): string
    {
        if ($cid <= 0) {
            throw new InvalidArgumentException("Invalid article ID: $cid");
        }

        try {
            $cacheKey = "article_content_$cid";
            $cached = self::getCache($cacheKey);
            if ($cached !== null) {
                return $cached;
            }

            $rs = $this->db->fetchRow($this->db->select('text')
                ->from('table.contents')
                ->where('cid = ?', $cid)
                ->limit(1));

            $result = $rs['text'] ?? '';

            // 缓存结果
            self::setCache($cacheKey, $result);

            return $result;
        } catch (Exception $e) {
            if (self::$errorHandler) {
                self::$errorHandler->error('Failed to get article content', ['cid' => $cid], $e);
            }
            return '';
        }
    }

    /**
     * 获取文章标题
     * @param int $cid 文章ID
     * @return string
     * @throws InvalidArgumentException
     */
    public function getArticleTitle(int $cid): string
    {
        if ($cid <= 0) {
            throw new InvalidArgumentException("Invalid article ID: $cid");
        }

        try {
            $cacheKey = "article_title_$cid";
            $cached = self::getCache($cacheKey);
            if ($cached !== null) {
                return $cached;
            }

            $rs = $this->db->fetchRow($this->db->select('title')
                ->from('table.contents')
                ->where('cid = ?', $cid)
                ->limit(1));

            $result = $rs['title'] ?? '';

            // 缓存结果
            self::setCache($cacheKey, $result);

            return $result;
        } catch (Exception $e) {
            if (self::$errorHandler) {
                self::$errorHandler->error('Failed to get article title', ['cid' => $cid], $e);
            }
            return '';
        }
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

class TTDF_Db_API
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
