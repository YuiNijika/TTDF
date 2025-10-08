<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 统一错误处理系统
 * 提供全局错误处理、异常捕获、日志记录和错误恢复机制
 */
class TTDF_UnifiedErrorHandler
{
    // 错误级别常量
    const LEVEL_DEBUG = 'DEBUG';
    const LEVEL_INFO = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR = 'ERROR';
    const LEVEL_FATAL = 'FATAL';

    // 错误类型映射
    const ERROR_TYPE_MAP = [
        E_ERROR => self::LEVEL_FATAL,
        E_WARNING => self::LEVEL_WARNING,
        E_PARSE => self::LEVEL_FATAL,
        E_NOTICE => self::LEVEL_INFO,
        E_CORE_ERROR => self::LEVEL_FATAL,
        E_CORE_WARNING => self::LEVEL_WARNING,
        E_COMPILE_ERROR => self::LEVEL_FATAL,
        E_COMPILE_WARNING => self::LEVEL_WARNING,
        E_USER_ERROR => self::LEVEL_ERROR,
        E_USER_WARNING => self::LEVEL_WARNING,
        E_USER_NOTICE => self::LEVEL_INFO,
        E_STRICT => self::LEVEL_INFO,
        E_RECOVERABLE_ERROR => self::LEVEL_ERROR,
        E_DEPRECATED => self::LEVEL_WARNING,
        E_USER_DEPRECATED => self::LEVEL_WARNING
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

    /** @var array 错误处理器回调 */
    private $handlers = [];

    /** @var array 错误过滤器 */
    private $filters = [];

    /** @var int 最大日志文件大小（字节） */
    private const MAX_LOG_SIZE = 10 * 1024 * 1024; // 10MB

    /** @var int 保留的日志文件数量 */
    private const MAX_LOG_FILES = 5;

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
        $this->logFile = dirname(__DIR__, 2) . '/logs/unified_error.log';
    }

    /**
     * 获取单例实例
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
            $this->setupErrorHandlers();

            // 记录初始化信息
            $this->logSystemInfo();

            $this->initialized = true;
            return true;
        } catch (Exception $e) {
            error_log('TTDF_UnifiedErrorHandler init failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 注册错误处理器
     */
    public function register(): void
    {
        if (!$this->initialized) {
            $this->init();
        }
    }

    /**
     * 添加错误处理器回调
     */
    public function addHandler(string $level, callable $handler): void
    {
        if (!isset($this->handlers[$level])) {
            $this->handlers[$level] = [];
        }
        $this->handlers[$level][] = $handler;
    }

    /**
     * 添加错误过滤器
     */
    public function addFilter(callable $filter): void
    {
        $this->filters[] = $filter;
    }

    /**
     * 设置日志文件
     */
    public function setLogFile(string $logFile): void
    {
        $this->logFile = $logFile;
        $this->ensureLogDirectory();
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
        ini_set('display_errors', $this->debugEnabled ? '1' : '0');
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
        $this->writeLog("=== TTDF Unified ErrorHandler " . date('Y-m-d H:i:s') . " ===");
        $this->writeLog("PID: " . getmypid() . " | PHP: " . PHP_VERSION);
        $this->writeLog("TTDF版本: " . (defined('__FRAMEWORK_VER__') ? __FRAMEWORK_VER__ : 'unknown'));
        $this->writeLog("调试模式: " . ($this->debugEnabled ? '启用' : '禁用'));
        $this->writeLog("内存限制: " . ini_get('memory_limit'));
        $this->writeLog("最大执行时间: " . ini_get('max_execution_time') . "s");
    }

    /**
     * 处理PHP错误
     */
    public function handleError(int $level, string $message, string $file = '', int $line = 0, array $context = []): bool
    {
        // 检查错误报告级别
        if (!(error_reporting() & $level)) {
            return false;
        }

        $errorLevel = self::ERROR_TYPE_MAP[$level] ?? self::LEVEL_ERROR;
        
        // 应用过滤器
        if ($this->shouldFilter($errorLevel, $message, $file, $line)) {
            return false;
        }

        $this->log($errorLevel, $message, [
            'file' => $file,
            'line' => $line,
            'php_error_level' => $level,
            'context' => $this->debugEnabled ? $context : []
        ]);

        // 执行自定义处理器
        $this->executeHandlers($errorLevel, $message, [
            'file' => $file,
            'line' => $line,
            'level' => $level
        ]);

        // 对于致命错误，不继续执行
        if (in_array($level, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $this->handleFatalError($message, $file, $line);
            return true;
        }

        return false; // 让PHP继续处理
    }

    /**
     * 处理异常
     */
    public function handleException(Throwable $exception): void
    {
        $level = $exception instanceof Error ? self::LEVEL_FATAL : self::LEVEL_ERROR;
        
        $this->log($level, $exception->getMessage(), [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $this->debugEnabled ? $exception->getTraceAsString() : 'Trace disabled',
            'exception_class' => get_class($exception),
            'code' => $exception->getCode()
        ], $exception);

        // 执行自定义处理器
        $this->executeHandlers($level, $exception->getMessage(), [
            'exception' => $exception
        ]);

        // 如果是致命错误，进行清理
        if ($level === self::LEVEL_FATAL) {
            $this->handleFatalError($exception->getMessage(), $exception->getFile(), $exception->getLine());
        }
    }

    /**
     * 处理脚本关闭
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

        // 记录执行统计
        $this->logExecutionStats();
    }

    /**
     * 处理致命错误
     */
    private function handleFatalError(string $message, string $file, int $line): void
    {
        // 记录致命错误
        $this->writeLog("FATAL ERROR OCCURRED - SYSTEM SHUTDOWN");
        $this->writeLog("Error: $message in $file on line $line");
        
        // 尝试清理资源
        $this->cleanup();

        // 如果不是调试模式，显示友好错误页面
        if (!$this->debugEnabled && !headers_sent()) {
            http_response_code(500);
            echo $this->getFriendlyErrorPage();
        }

        exit(1);
    }

    /**
     * 记录日志
     */
    public function log(string $level, string $message, array $context = [], Exception $exception = null): bool
    {
        if (!$this->initialized) {
            $this->init();
        }

        // 验证错误级别
        if (!array_key_exists($level, $this->errorStats)) {
            $level = self::LEVEL_ERROR;
        }

        // 更新统计
        $this->errorStats[$level]++;

        // 格式化错误消息
        $formattedMessage = $this->formatMessage($level, $message, $context, $exception);

        // 写入日志
        return $this->writeLog($formattedMessage);
    }

    /**
     * 便捷方法 - 调试信息
     */
    public function debug(string $message, array $context = []): bool
    {
        return $this->log(self::LEVEL_DEBUG, $message, $context);
    }

    /**
     * 便捷方法 - 信息
     */
    public function info(string $message, array $context = []): bool
    {
        return $this->log(self::LEVEL_INFO, $message, $context);
    }

    /**
     * 便捷方法 - 警告
     */
    public function warning(string $message, array $context = [], Exception $exception = null): bool
    {
        return $this->log(self::LEVEL_WARNING, $message, $context, $exception);
    }

    /**
     * 便捷方法 - 错误
     */
    public function error(string $message, array $context = [], Exception $exception = null): bool
    {
        return $this->log(self::LEVEL_ERROR, $message, $context, $exception);
    }

    /**
     * 便捷方法 - 致命错误
     */
    public function fatal(string $message, array $context = [], Exception $exception = null): bool
    {
        return $this->log(self::LEVEL_FATAL, $message, $context, $exception);
    }

    /**
     * 格式化错误消息
     */
    private function formatMessage(string $level, string $message, array $context, Exception $exception = null): string
    {
        $timestamp = date('Y-m-d H:i:s');
        $memory = $this->formatBytes(memory_get_usage(true));
        $peak = $this->formatBytes(memory_get_peak_usage(true));
        
        $formatted = "[{$timestamp}] [{$level}] {$message}";
        
        if (!empty($context)) {
            $formatted .= " | Context: " . json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        
        if ($exception) {
            $formatted .= " | Exception: " . get_class($exception) . " in " . 
                         $exception->getFile() . ":" . $exception->getLine();
            if ($this->debugEnabled) {
                $formatted .= "\nTrace: " . $exception->getTraceAsString();
            }
        }
        
        $formatted .= " | Memory: {$memory} (Peak: {$peak})";
        
        return $formatted;
    }

    /**
     * 写入日志文件
     */
    private function writeLog(string $message): bool
    {
        try {
            // 检查日志文件大小，必要时轮转
            $this->rotateLogIfNeeded();
            
            $logEntry = $message . "\n";
            return file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX) !== false;
        } catch (Exception $e) {
            error_log("Failed to write to log file: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 日志轮转
     */
    private function rotateLogIfNeeded(): void
    {
        if (!file_exists($this->logFile) || filesize($this->logFile) < self::MAX_LOG_SIZE) {
            return;
        }

        // 轮转现有日志文件
        for ($i = self::MAX_LOG_FILES - 1; $i > 0; $i--) {
            $oldFile = $this->logFile . '.' . $i;
            $newFile = $this->logFile . '.' . ($i + 1);
            
            if (file_exists($oldFile)) {
                if ($i === self::MAX_LOG_FILES - 1) {
                    @unlink($oldFile); // 删除最老的文件
                } else {
                    @rename($oldFile, $newFile);
                }
            }
        }

        // 重命名当前日志文件
        @rename($this->logFile, $this->logFile . '.1');
        
        // 创建新的日志文件
        @touch($this->logFile);
        @chmod($this->logFile, 0666);
    }

    /**
     * 检查是否应该过滤错误
     */
    private function shouldFilter(string $level, string $message, string $file, int $line): bool
    {
        foreach ($this->filters as $filter) {
            if (call_user_func($filter, $level, $message, $file, $line)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 执行自定义处理器
     */
    private function executeHandlers(string $level, string $message, array $context): void
    {
        if (isset($this->handlers[$level])) {
            foreach ($this->handlers[$level] as $handler) {
                try {
                    call_user_func($handler, $message, $context);
                } catch (Exception $e) {
                    error_log("Error handler failed: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * 记录执行统计
     */
    private function logExecutionStats(): void
    {
        $executionTime = microtime(true) - $this->startTime;
        $memoryUsage = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);
        
        $stats = [
            'execution_time' => round($executionTime, 4) . 's',
            'memory_usage' => $this->formatBytes($memoryUsage),
            'peak_memory' => $this->formatBytes($peakMemory),
            'error_stats' => $this->errorStats
        ];
        
        $this->writeLog("=== Execution Stats: " . json_encode($stats, JSON_UNESCAPED_UNICODE) . " ===");
    }

    /**
     * 清理资源
     */
    private function cleanup(): void
    {
        // 刷新输出缓冲区
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // 关闭数据库连接等资源
        if (class_exists('Typecho_Db')) {
            try {
                Typecho_Db::get()->close();
            } catch (Exception $e) {
                // 忽略清理错误
            }
        }
    }

    /**
     * 获取友好错误页面
     */
    private function getFriendlyErrorPage(): string
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>系统错误</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
        .error-container { max-width: 600px; margin: 0 auto; }
        .error-title { color: #e74c3c; font-size: 24px; margin-bottom: 20px; }
        .error-message { color: #7f8c8d; font-size: 16px; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-title">系统遇到了一个错误</div>
        <div class="error-message">
            抱歉，系统遇到了一个意外错误。我们已经记录了这个问题，请稍后再试。<br>
            如果问题持续存在，请联系网站管理员。
        </div>
    </div>
</body>
</html>';
    }

    /**
     * 格式化字节数
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * 获取错误统计
     */
    public function getErrorStats(): array
    {
        return $this->errorStats;
    }

    /**
     * 获取系统状态
     */
    public function getSystemStatus(): array
    {
        return [
            'initialized' => $this->initialized,
            'debug_enabled' => $this->debugEnabled,
            'log_file' => $this->logFile,
            'log_file_size' => file_exists($this->logFile) ? filesize($this->logFile) : 0,
            'error_stats' => $this->errorStats,
            'uptime' => microtime(true) - $this->startTime,
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ];
    }
}