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

    /** @var bool 是否在页面显示错误 */
    private $displayErrors = false;

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
            $this->displayErrors = $config['display_errors'] ?? $this->debugEnabled;
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

        // 捕获 Typecho 的错误
        $this->captureTypechoErrors();
    }

    /**
     * 捕获 Typecho 系统错误
     */
    private function captureTypechoErrors(): void
    {
        // 如果 Typecho 已经定义了错误处理函数，我们需要包装它们
        if (function_exists('Typecho_Exception_Handler')) {
            // 保存原始的 Typecho 异常处理器
            $originalHandler = set_exception_handler(function(Throwable $exception) {
                // 先用我们的处理器记录和显示
                $this->handleException($exception);
                
                // 然后调用 Typecho 的原始处理器（如果需要）
                if (function_exists('Typecho_Exception_Handler')) {
                    Typecho_Exception_Handler($exception);
                }
            });
        }

        // 检查是否存在 Typecho 的错误常量和函数
        if (defined('__TYPECHO_ROOT_DIR__')) {
            // Typecho 环境已加载，设置额外的错误捕获
            $this->setupTypechoIntegration();
        }
    }

    /**
     * 设置与 Typecho 的集成
     */
    private function setupTypechoIntegration(): void
    {
        // 如果存在 Typecho 的数据库类，监听数据库错误
        if (class_exists('Typecho_Db_Exception')) {
            // 这里可以添加数据库错误的特殊处理
        }

        // 如果存在 Typecho 的插件系统，监听插件错误
        if (class_exists('Typecho_Plugin_Exception')) {
            // 这里可以添加插件错误的特殊处理
        }

        // 监听 Typecho 的路由错误
        if (class_exists('Typecho_Router_Exception')) {
            // 这里可以添加路由错误的特殊处理
        }
    }

    /**
     * 注册错误处理器
     * 
     * @return void
     */
    public function register(): void
    {
        if (!$this->initialized) {
            $this->init();
        }
    }

    /**
     * 设置日志文件
     * 
     * @param string $logFile 日志文件路径
     * @return void
     */
    public function setLogFile(string $logFile): void
    {
        $this->logFile = $logFile;
        $this->ensureLogDirectory();
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
     * 记录日志
     * 
     * @param string $level 错误级别
     * @param string $message 错误消息
     * @param array $context 上下文信息
     * @param Throwable|null $exception 异常对象
     * @return bool
     */
    public function log(string $level, string $message, array $context = [], ?Throwable $exception = null): bool
    {
        if (!$this->initialized) {
            return false;
        }

        // 验证错误级别
        if (!array_key_exists($level, $this->errorStats)) {
            $level = self::LEVEL_ERROR;
        }

        // 更新统计
        $this->errorStats[$level]++;

        // 合并全局上下文
        $context = array_merge($this->context, $context);

        // 格式化错误消息
        $formattedMessage = $this->formatMessage($level, $message, $context, $exception);

        // 写入日志
        $this->writeLog($formattedMessage);

        // 在调试模式下只显示重要错误到页面（不显示 INFO 级别）
        if ($this->displayErrors && in_array($level, [self::LEVEL_ERROR, self::LEVEL_WARNING, self::LEVEL_FATAL])) {
            $this->displayError($level, $message, $context, $exception);
        }

        // 如果是致命错误，触发PHP错误
        if ($level === self::LEVEL_FATAL && $this->debugEnabled) {
            trigger_error($message, E_USER_ERROR);
        }

        return true;
    }

    /**
     * 过滤敏感数据
     * 
     * @param array $data 原始数据
     * @return array 过滤后的数据
     */
    private function filterSensitiveData(array $data): array
    {
        $sensitiveKeys = ['password', 'passwd', 'pwd', 'secret', 'key', 'token', 'auth', 'session', 'cookie'];
        
        $filtered = [];
        foreach ($data as $key => $value) {
            $lowerKey = strtolower($key);
            $isSensitive = false;
            
            foreach ($sensitiveKeys as $sensitiveKey) {
                if (strpos($lowerKey, $sensitiveKey) !== false) {
                    $isSensitive = true;
                    break;
                }
            }
            
            if ($isSensitive) {
                $filtered[$key] = '[FILTERED]';
            } elseif (is_array($value)) {
                $filtered[$key] = $this->filterSensitiveData($value);
            } else {
                $filtered[$key] = $value;
            }
        }
        
        return $filtered;
    }

    /**
     * 日志轮转
     */
    private function rotateLogIfNeeded(): void
    {
        if (!file_exists($this->logFile)) {
            return;
        }
        
        $maxSize = 10 * 1024 * 1024; // 10MB
        if (filesize($this->logFile) < $maxSize) {
            return;
        }

        $maxFiles = 5;
        
        // 删除最老的日志文件
        $oldestFile = $this->logFile . '.' . $maxFiles;
        if (file_exists($oldestFile)) {
            @unlink($oldestFile);
        }
        
        // 轮转现有日志文件
        for ($i = $maxFiles - 1; $i >= 1; $i--) {
            $oldFile = $this->logFile . '.' . $i;
            $newFile = $this->logFile . '.' . ($i + 1);
            
            if (file_exists($oldFile)) {
                @rename($oldFile, $newFile);
            }
        }

        // 重命名当前日志文件
        @rename($this->logFile, $this->logFile . '.1');
        
        // 创建新的日志文件
        @touch($this->logFile);
        @chmod($this->logFile, 0666);
    }

    /**
     * 格式化错误消息
     * 
     * @param string $level 错误级别
     * @param string $message 错误消息
     * @param array $context 上下文信息
     * @param Throwable|null $exception 异常对象
     * @return string
     */
    private function formatMessage(string $level, string $message, array $context = [], ?Throwable $exception = null): string
    {
        $timestamp = date('Y-m-d H:i:s');
        $pid = getmypid();
        $memory = $this->formatMemory(memory_get_usage(true));
        $elapsed = number_format((microtime(true) - $this->startTime) * 1000, 2);

        $formatted = "[{$timestamp}] [{$level}] [PID:{$pid}] [MEM:{$memory}] [TIME:{$elapsed}ms] {$message}";

        // 添加上下文信息
        if (!empty($context)) {
            // 过滤敏感信息
            $safeContext = $this->filterSensitiveData($context);
            $formatted .= " | Context: " . json_encode($safeContext, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
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

        // 添加用户代理信息（仅调试模式）
        if ($this->debugEnabled && isset($_SERVER['HTTP_USER_AGENT'])) {
            $formatted .= " | UA: " . substr($_SERVER['HTTP_USER_AGENT'], 0, 100);
        }

        return $formatted;
    }

    /**
     * 写入日志文件方法
     * 
     * @param string $message 消息内容
     */
    private function writeLog(string $message): void
    {
        try {
            // 检查日志文件大小，必要时轮转
            $this->rotateLogIfNeeded();
            
            $result = @file_put_contents(
                $this->logFile,
                $message . "\n",
                FILE_APPEND | LOCK_EX
            );
            
            if ($result === false) {
                // 如果写入失败，尝试使用系统日志
                error_log("TTDF ErrorHandler: Failed to write to log file, using system log: " . $message);
            }
        } catch (Exception $e) {
            error_log("TTDF ErrorHandler: Exception in writeLog: " . $e->getMessage());
        }
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
            'php_error_level' => $level,
            'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
        ];

        // 创建一个模拟的异常对象来提供更多信息
        $exception = new ErrorException($message, 0, $level, $file, $line);

        $this->log($errorLevel, $message, $context, $exception);

        // 对于致命错误，不继续执行PHP的内置错误处理器
        if (in_array($level, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
            exit(1);
        }

        return true;
    }

    /**
     * 异常处理器
     * 
     * @param Throwable $exception 异常对象
     */
    public function handleException(Throwable $exception): void
    {
        $context = [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'code' => $exception->getCode(),
            'trace' => $exception->getTraceAsString()
        ];

        $this->log(self::LEVEL_FATAL, $exception->getMessage(), $context, $exception);

        exit(1);
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

    public function error(string $message, array $context = [], ?Throwable $exception = null): bool
    {
        return $this->log(self::LEVEL_ERROR, $message, $context, $exception);
    }

    public function fatal(string $message, array $context = [], ?Throwable $exception = null): bool
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

    /**
     * 设置是否在页面显示错误
     * 
     * @param bool $display 是否显示错误
     */
    public function setDisplayErrors(bool $display): void
    {
        $this->displayErrors = $display;
    }

    /**
     * 获取当前错误显示状态
     * 
     * @return bool
     */
    public function getDisplayErrors(): bool
    {
        return $this->displayErrors;
    }

    /**
     * 在页面显示错误信息
     * 
     * @param string $level 错误级别
     * @param string $message 错误消息
     * @param array $context 上下文信息
     * @param Throwable|null $exception 异常对象
     */
    private function displayError(string $level, string $message, array $context = [], ?Throwable $exception = null): void
    {
        if (!$this->displayErrors) {
            return;
        }

        // 确保输出缓冲区被清理
        if (ob_get_level()) {
            ob_end_clean();
        }

        // 设置内容类型为 HTML
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }

        $errorHtml = $this->formatErrorForDisplay($level, $message, $context, $exception);
        echo $errorHtml;
        
        // 如果是致命错误，停止执行
        if (in_array($level, [self::LEVEL_FATAL, self::LEVEL_ERROR])) {
            exit(1);
        }
    }

    /**
     * 格式化错误信息用于页面显示
     * 
     * @param string $level 错误级别
     * @param string $message 错误消息
     * @param array $context 上下文信息
     * @param Throwable|null $exception 异常对象
     * @return string
     */
    private function formatErrorForDisplay(string $level, string $message, array $context = [], ?Throwable $exception = null): string
    {
        $timestamp = date('Y-m-d H:i:s');
        $levelColor = $this->getLevelColor($level);
        
        $html = "
        <div style='
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(0,0,0,0.8); 
            z-index: 9999; 
            font-family: monospace; 
            color: #fff; 
            overflow: auto;
            padding: 20px;
            box-sizing: border-box;
        '>
            <div style='
                background: #1a1a1a; 
                border: 2px solid {$levelColor}; 
                border-radius: 8px; 
                padding: 20px; 
                max-width: 1200px; 
                margin: 0 auto;
            '>
                <h2 style='color: {$levelColor}; margin: 0 0 15px 0; font-size: 24px;'>
                    🚨 TTDF Error Handler - {$level}
                </h2>
                
                <div style='background: #2a2a2a; padding: 15px; border-radius: 4px; margin-bottom: 15px;'>
                    <strong style='color: #ff6b6b;'>时间:</strong> {$timestamp}<br>
                    <strong style='color: #ff6b6b;'>级别:</strong> <span style='color: {$levelColor};'>{$level}</span><br>
                    <strong style='color: #ff6b6b;'>消息:</strong> " . htmlspecialchars($message) . "
                </div>";

        if ($exception) {
            $html .= "
                <div style='background: #2a2a2a; padding: 15px; border-radius: 4px; margin-bottom: 15px;'>
                    <strong style='color: #ff6b6b;'>异常类型:</strong> " . get_class($exception) . "<br>
                    <strong style='color: #ff6b6b;'>文件:</strong> " . htmlspecialchars($exception->getFile()) . "<br>
                    <strong style='color: #ff6b6b;'>行号:</strong> " . $exception->getLine() . "<br>
                    <strong style='color: #ff6b6b;'>代码:</strong> " . $exception->getCode() . "
                </div>
                
                <div style='background: #2a2a2a; padding: 15px; border-radius: 4px; margin-bottom: 15px;'>
                    <strong style='color: #ff6b6b;'>堆栈跟踪:</strong><br>
                    <pre style='color: #ccc; margin: 10px 0 0 0; white-space: pre-wrap; font-size: 12px;'>" . 
                    htmlspecialchars($exception->getTraceAsString()) . "</pre>
                </div>";
        }

        if (!empty($context)) {
            $html .= "
                <div style='background: #2a2a2a; padding: 15px; border-radius: 4px; margin-bottom: 15px;'>
                    <strong style='color: #ff6b6b;'>上下文信息:</strong><br>
                    <pre style='color: #ccc; margin: 10px 0 0 0; white-space: pre-wrap; font-size: 12px;'>" . 
                    htmlspecialchars(print_r($context, true)) . "</pre>
                </div>";
        }

        // 添加请求信息
        $requestInfo = [
            'URI' => $_SERVER['REQUEST_URI'] ?? 'N/A',
            'Method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A',
            'User Agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'N/A',
            'IP' => $_SERVER['REMOTE_ADDR'] ?? 'N/A'
        ];

        $html .= "
                <div style='background: #2a2a2a; padding: 15px; border-radius: 4px; margin-bottom: 15px;'>
                    <strong style='color: #ff6b6b;'>请求信息:</strong><br>";
        
        foreach ($requestInfo as $key => $value) {
            $html .= "<strong style='color: #4ecdc4;'>{$key}:</strong> " . htmlspecialchars($value) . "<br>";
        }
        
        $html .= "
                </div>
                
                <div style='text-align: center; margin-top: 20px;'>
                    <button onclick='this.parentElement.parentElement.parentElement.style.display=\"none\"' 
                            style='
                                background: {$levelColor}; 
                                color: #fff; 
                                border: none; 
                                padding: 10px 20px; 
                                border-radius: 4px; 
                                cursor: pointer; 
                                font-size: 14px;
                            '>
                        关闭错误信息
                    </button>
                </div>
            </div>
        </div>";

        return $html;
    }

    /**
     * 获取错误级别对应的颜色
     * 
     * @param string $level 错误级别
     * @return string
     */
    private function getLevelColor(string $level): string
    {
        switch ($level) {
            case self::LEVEL_DEBUG:
                return '#6c757d';
            case self::LEVEL_INFO:
                return '#17a2b8';
            case self::LEVEL_WARNING:
                return '#ffc107';
            case self::LEVEL_ERROR:
                return '#dc3545';
            case self::LEVEL_FATAL:
                return '#721c24';
            default:
                return '#6c757d';
        }
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