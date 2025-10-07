<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 统一缓存管理器
 * 为所有Widget类提供统一的缓存机制
 */
class TTDF_CacheManager
{
    /** @var array 全局缓存存储 */
    private static $cache = [];
    
    /** @var array 缓存过期时间 */
    private static $expiry = [];
    
    /** @var int 默认缓存时间（秒） */
    private const DEFAULT_TTL = 300; // 5分钟
    
    /** @var int 最大缓存条目数 */
    private const MAX_CACHE_SIZE = 1000;
    
    /** @var TTDF_ErrorHandler 错误处理器 */
    private static $errorHandler;
    
    /**
     * 初始化缓存管理器
     */
    public static function init(): void
    {
        if (!self::$errorHandler) {
            self::$errorHandler = TTDF_ErrorHandler::getInstance();
        }
    }
    
    /**
     * 设置缓存
     * @param string $key 缓存键
     * @param mixed $value 缓存值
     * @param int $ttl 过期时间（秒）
     * @return bool
     */
    public static function set(string $key, $value, int $ttl = self::DEFAULT_TTL): bool
    {
        try {
            self::init();
            
            // 检查缓存大小，如果超过限制则清理
            if (count(self::$cache) >= self::MAX_CACHE_SIZE) {
                self::cleanup();
            }
            
            self::$cache[$key] = $value;
            self::$expiry[$key] = time() + $ttl;
            
            return true;
        } catch (Exception $e) {
            if (self::$errorHandler) {
                self::$errorHandler->warning('缓存设置失败', ['key' => $key], $e);
            }
            return false;
        }
    }
    
    /**
     * 获取缓存
     * @param string $key 缓存键
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        try {
            self::init();
            
            // 检查缓存是否存在
            if (!isset(self::$cache[$key])) {
                return $default;
            }
            
            // 检查是否过期
            if (isset(self::$expiry[$key]) && time() > self::$expiry[$key]) {
                self::delete($key);
                return $default;
            }
            
            return self::$cache[$key];
        } catch (Exception $e) {
            if (self::$errorHandler) {
                self::$errorHandler->warning('缓存获取失败', ['key' => $key], $e);
            }
            return $default;
        }
    }
    
    /**
     * 检查缓存是否存在且未过期
     * @param string $key 缓存键
     * @return bool
     */
    public static function has(string $key): bool
    {
        if (!isset(self::$cache[$key])) {
            return false;
        }
        
        // 检查是否过期
        if (isset(self::$expiry[$key]) && time() > self::$expiry[$key]) {
            self::delete($key);
            return false;
        }
        
        return true;
    }
    
    /**
     * 删除缓存
     * @param string $key 缓存键
     * @return bool
     */
    public static function delete(string $key): bool
    {
        unset(self::$cache[$key], self::$expiry[$key]);
        return true;
    }
    
    /**
     * 清空所有缓存
     * @return bool
     */
    public static function clear(): bool
    {
        self::$cache = [];
        self::$expiry = [];
        return true;
    }
    
    /**
     * 清理过期缓存
     * @return int 清理的条目数
     */
    public static function cleanup(): int
    {
        $cleaned = 0;
        $now = time();
        
        foreach (self::$expiry as $key => $expiry) {
            if ($now > $expiry) {
                unset(self::$cache[$key], self::$expiry[$key]);
                $cleaned++;
            }
        }
        
        // 如果清理后仍然超过限制，删除最老的条目
        if (count(self::$cache) >= self::MAX_CACHE_SIZE) {
            $toRemove = count(self::$cache) - self::MAX_CACHE_SIZE + 100; // 多删除一些
            $keys = array_keys(self::$cache);
            for ($i = 0; $i < $toRemove && $i < count($keys); $i++) {
                $key = $keys[$i];
                unset(self::$cache[$key], self::$expiry[$key]);
                $cleaned++;
            }
        }
        
        return $cleaned;
    }
    
    /**
     * 获取缓存统计信息
     * @return array
     */
    public static function getStats(): array
    {
        $now = time();
        $expired = 0;
        
        foreach (self::$expiry as $expiry) {
            if ($now > $expiry) {
                $expired++;
            }
        }
        
        return [
            'total' => count(self::$cache),
            'expired' => $expired,
            'active' => count(self::$cache) - $expired,
            'memory_usage' => memory_get_usage(true)
        ];
    }
    
    /**
     * 生成缓存键
     * @param string $prefix 前缀
     * @param array $params 参数
     * @return string
     */
    public static function generateKey(string $prefix, array $params = []): string
    {
        if (empty($params)) {
            return $prefix;
        }
        
        return $prefix . '_' . md5(serialize($params));
    }
}