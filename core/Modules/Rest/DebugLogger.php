<?php

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 调试日志记录器
 * 统一管理API调试日志，减少重复代码
 */
class TTDF_DebugLogger
{
    /** @var bool 是否启用调试 */
    private static bool $debugEnabled = false;
    
    /** @var bool 是否已初始化 */
    private static bool $initialized = false;
    
    /**
     * 初始化调试记录器
     */
    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }
        
        self::$debugEnabled = (TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug');
        self::$initialized = true;
    }
    
    /**
     * 记录API处理过程
     * 
     * @param string $stage 处理阶段
     * @param array $data 相关数据
     */
    public static function logApiProcess(string $stage, array $data = []): void
    {
        if (!self::$debugEnabled) {
            return;
        }
        
        TTDF_Debug::logApiProcess($stage, $data);
    }
    
    /**
     * 记录API错误
     * 
     * @param string $message 错误消息
     * @param Throwable|null $exception 异常对象
     */
    public static function logApiError(string $message, ?Throwable $exception = null): void
    {
        if (!self::$debugEnabled) {
            return;
        }
        
        TTDF_Debug::logApiError($message, $exception);
    }
    
    /**
     * 检查是否启用调试
     * 
     * @return bool
     */
    public static function isEnabled(): bool
    {
        return self::$debugEnabled;
    }
}