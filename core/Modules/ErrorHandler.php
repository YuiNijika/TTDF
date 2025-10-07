<?php

/**
 * TTDF 统一错误处理系统
 * Unified Error Handler System for TTDF Framework
 * 
 * @author TTDF Framework
 * @version 1.0.0
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 统一错误处理类
 */
class TTDF_ErrorHandler
{
    // 错误级别常量
    const LEVEL_DEBUG = 'DEBUG';
    const LEVEL_INFO = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR = 'ERROR';
    const LEVEL_FATAL = 'FATAL';

    // 错误级别映射到PHP错误常量
    const LEVEL_MAP = [
        self::LEVEL_DEBUG => E_USER_NOTICE,
        self::LEVEL_INFO => E_USER_NOTICE,
        self::LEVEL_WARNING => E_USER_WARNING,
        self::LEVEL_ERROR => E_USER_ERROR,
        self::LEVEL_FATAL => E_ERROR
    ];

    /** @var self|null 单例实例 */
    private static $instance = null;

    /** @var string 日志文件路径 */
    private $logFile;

    /** @var bool 是否启用调试模式 */
    private $debugEnabled = false;

    /** @var bool 是否已初始化 */
    private $initialized = false;

    /** @var array 错误统计 */
    private $errorStats = [
        self::LEVEL_DEBUG => 0,
        self::LEVEL_INFO => 0,
        self::LEVEL_WARNING => 0,
        self::LEVEL_ERROR => 0,
        self::LEVEL_FATAL => 0
    ];

    /** @var float 开始时间 */
    private $startTime;

    /** @var array 上下文信息 */
    private $context = [];

    /**
     * 私有构造函数
     */
    private function __construct()
    {
        $this->startTime = microtime(true);
        $this->logFile = dirname(__DIR__, 2) . '/logs/error.log';
    }

    /**
     * 获取单例实例
     * 
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 初始化错误处理系统
     * 
     * @param array $config 配置参数
     * @return bool
     */
    public function init(array $config = []): bool
    {
        if ($this->initialized) {
            return true;
        }

        try {
            // 设置配置
            $this->debugEnabled = $config['debug'] ?? (defined('TTDF_CONFIG') && (TTDF_CONFIG['DEBUG'] ?? false));
            $this->logFile = $config['log_file'] ?? $this->logFile;

            // 创建日志目录
            $this->ensureLogDirectory();

            // 设置错误处理器
            if ($this->debugEnabled) {
                $this->setupErrorHandlers();
            }

            // 记录初始化信息
            $this->logSystemInfo();

            $this->initialized = true;
            return true;
        } catch (Exception $e) {
            error_log('TTDF_ErrorHandler init failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 确保日志目录存在
     */
    private function ensureLogDirectory(): void
    {
        $logDir = dirname($this->logFile);
        if (!file_exists($logDir)) {
            if (!@mkdir($logDir, 0755, true) && !is_dir($logDir)) {
                throw new RuntimeException("无法创建日志目录: {$logDir}");
            }
        }

        if (!file_exists($this->logFile)) {
            if (!@touch($this->logFile)) {
                throw new RuntimeException("无法创建日志文件: {$this->logFile}");
            }
            @chmod($this->logFile, 0666);
        }

        if (!is_writable($this->logFile)) {
            throw new RuntimeException("日志文件不可写: {$this->logFile}");
        }
    }

    /**
     * 设置错误处理器
     */
    private function setupErrorHandlers(): void
    {
        // 设置错误报告级别
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        ini_set('error_log', $this->logFile);

        // 注册错误处理器
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    /**
     * 记录系统信息
     */
    private function logSystemInfo(): void
    {
        $this->writeLog("=== TTDF ErrorHandler " . date('Y-m-d H:i:s') . " ===");
        $this->writeLog("PID: " . getmypid() . " | PHP: " . PHP_VERSION);
        $this->writeLog("TTDF版本: " . (defined('__FRAMEWORK_VER__') ? __FRAMEWORK_VER__ : 'unknown'));
        $this->writeLog("调试模式: " . ($this->debugEnabled ? '启用' : '禁用'));
        $this->writeLog("内存限制: " . ini_get('memory_limit'));
    }

    /**
     * 记录错误信息
     * 
     * @param string $level 错误级别
     * @param string $message 错误消息
     * @param array $context 上下文信息
     * @param Exception|null $exception 异常对象
     * @return bool
     */
    public function log(string $level, string $message, array $context = [], Exception $exception = null): bool
    {
        if (!$this->initialized) {
            $this->init();
        }

        // 验证错误级别
        if (!array_key_exists($level, self::LEVEL_MAP)) {
            $level = self::LEVEL_ERROR;
        }

        // 更新统计
        $this->errorStats[$level]++;

        // 格式化错误消息
        $formattedMessage = $this->formatMessage($level, $message, $context, $exception);

        // 写入日志
        $this->writeLog($formattedMessage);

        // 如果是致命错误，触发PHP错误
        if ($level === self::LEVEL_FATAL && $this->debugEnabled) {
            trigger_error($message, E_USER_ERROR);
        }

        return true;
    }

    /**
     * 格式化错误消息
     * 
     * @param string $level 错误级别
     * @param string $message 错误消息
     * @param array $context 上下文信息
     * @param Exception|null $exception 异常对象
     * @return string
     */
    private function formatMessage(string $level, string $message, array $context = [], Exception $exception = null): string
    {
        $timestamp = date('Y-m-d H:i:s');
        $pid = getmypid();
        $memory = $this->formatMemory(memory_get_usage(true));
        $elapsed = number_format((microtime(true) - $this->startTime) * 1000, 2);

        $formatted = "[{$timestamp}] [{$level}] [PID:{$pid}] [MEM:{$memory}] [TIME:{$elapsed}ms] {$message}";

        // 添加上下文信息
        if (!empty($context)) {
            $formatted .= " | Context: " . json_encode($context, JSON_UNESCAPED_UNICODE);
        }

        // 添加异常信息
        if ($exception) {
            $formatted .= " | Exception: {$exception->getMessage()} in {$exception->getFile()}:{$exception->getLine()}";
            if ($this->debugEnabled) {
                $formatted .= "\nStack Trace:\n" . $exception->getTraceAsString();
            }
        }

        // 添加请求信息
        if (isset($_SERVER['REQUEST_URI'])) {
            $formatted .= " | URI: {$_SERVER['REQUEST_URI']}";
        }

        return $formatted;
    }

    /**
     * 写入日志文件
     * 
     * @param string $message 消息内容
     */
    private function writeLog(string $message): void
    {
        @file_put_contents(
            $this->logFile,
            $message . "\n",
            FILE_APPEND | LOCK_EX
        );
    }

    /**
     * 格式化内存大小
     * 
     * @param int $bytes 字节数
     * @return string
     */
    private function formatMemory(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        for ($i = 0; $bytes >= 1024 && $i < 4; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, 2) . $units[$i];
    }

    /**
     * PHP错误处理器
     * 
     * @param int $level 错误级别
     * @param string $message 错误消息
     * @param string $file 文件路径
     * @param int $line 行号
     * @return bool
     */
    public function handleError(int $level, string $message, string $file = '', int $line = 0): bool
    {
        if (!(error_reporting() & $level)) {
            return false;
        }

        $errorLevel = $this->mapPhpErrorLevel($level);
        $context = [
            'file' => $file,
            'line' => $line,
            'php_error_level' => $level
        ];

        $this->log($errorLevel, $message, $context);

        return true;
    }

    /**
     * 异常处理器
     * 
     * @param Throwable $exception 异常对象
     */
    public function handleException(Throwable $exception): void
    {
        $level = ($exception instanceof Error) ? self::LEVEL_FATAL : self::LEVEL_ERROR;
        $context = [
            'exception_class' => get_class($exception),
            'code' => $exception->getCode()
        ];

        $this->log($level, $exception->getMessage(), $context, $exception);

        // 如果是致命错误，退出程序
        if ($level === self::LEVEL_FATAL) {
            exit(1);
        }
    }

    /**
     * 关闭处理器
     */
    public function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $this->handleError(
                $error['type'],
                $error['message'],
                $error['file'],
                $error['line']
            );
        }

        // 记录统计信息
        if ($this->debugEnabled) {
            $this->logStats();
        }
    }

    /**
     * 映射PHP错误级别到自定义级别
     * 
     * @param int $phpLevel PHP错误级别
     * @return string
     */
    private function mapPhpErrorLevel(int $phpLevel): string
    {
        switch ($phpLevel) {
            case E_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
                return self::LEVEL_FATAL;
            case E_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
            case E_USER_WARNING:
                return self::LEVEL_WARNING;
            case E_NOTICE:
            case E_USER_NOTICE:
            case E_STRICT:
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                return self::LEVEL_INFO;
            default:
                return self::LEVEL_DEBUG;
        }
    }

    /**
     * 记录统计信息
     */
    private function logStats(): void
    {
        $totalErrors = array_sum($this->errorStats);
        $runtime = number_format((microtime(true) - $this->startTime) * 1000, 2);
        $peakMemory = $this->formatMemory(memory_get_peak_usage(true));

        $stats = "=== 错误统计 ===";
        $stats .= " | 运行时间: {$runtime}ms";
        $stats .= " | 峰值内存: {$peakMemory}";
        $stats .= " | 总错误数: {$totalErrors}";
        
        foreach ($this->errorStats as $level => $count) {
            if ($count > 0) {
                $stats .= " | {$level}: {$count}";
            }
        }

        $this->writeLog($stats);
    }

    // 便捷方法
    public function debug(string $message, array $context = []): bool
    {
        return $this->log(self::LEVEL_DEBUG, $message, $context);
    }

    public function info(string $message, array $context = []): bool
    {
        return $this->log(self::LEVEL_INFO, $message, $context);
    }

    public function warning(string $message, array $context = []): bool
    {
        return $this->log(self::LEVEL_WARNING, $message, $context);
    }

    public function error(string $message, array $context = [], Exception $exception = null): bool
    {
        return $this->log(self::LEVEL_ERROR, $message, $context, $exception);
    }

    public function fatal(string $message, array $context = [], Exception $exception = null): bool
    {
        return $this->log(self::LEVEL_FATAL, $message, $context, $exception);
    }

    /**
     * 获取错误统计
     * 
     * @return array
     */
    public function getStats(): array
    {
        return $this->errorStats;
    }

    /**
     * 设置上下文信息
     * 
     * @param array $context 上下文信息
     */
    public function setContext(array $context): void
    {
        $this->context = array_merge($this->context, $context);
    }

    /**
     * 清除上下文信息
     */
    public function clearContext(): void
    {
        $this->context = [];
    }
}

/**
 * 统一错误处理 Trait
 * 为了向后兼容，保留原有的 ErrorHandler trait
 */
trait ErrorHandler
{
    /**
     * 处理错误的统一方法
     * 
     * @param string $message 错误消息
     * @param Exception $exception 异常对象
     * @param mixed $defaultValue 默认返回值
     * @param string $level 错误级别
     * @return mixed
     */
    protected static function handleError(string $message, Exception $exception, $defaultValue = '', string $level = 'ERROR')
    {
        $errorHandler = TTDF_ErrorHandler::getInstance();
        $errorHandler->log($level, $message, [], $exception);
        return $defaultValue;
    }

    /**
     * 记录调试信息
     * 
     * @param string $message 消息
     * @param array $context 上下文
     */
    protected static function logDebug(string $message, array $context = []): void
    {
        TTDF_ErrorHandler::getInstance()->debug($message, $context);
    }

    /**
     * 记录信息
     * 
     * @param string $message 消息
     * @param array $context 上下文
     */
    protected static function logInfo(string $message, array $context = []): void
    {
        TTDF_ErrorHandler::getInstance()->info($message, $context);
    }

    /**
     * 记录警告
     * 
     * @param string $message 消息
     * @param array $context 上下文
     */
    protected static function logWarning(string $message, array $context = []): void
    {
        TTDF_ErrorHandler::getInstance()->warning($message, $context);
    }

    /**
     * 记录错误
     * 
     * @param string $message 消息
     * @param array $context 上下文
     * @param Exception|null $exception 异常
     */
    protected static function logError(string $message, array $context = [], Exception $exception = null): void
    {
        TTDF_ErrorHandler::getInstance()->error($message, $context, $exception);
    }
}