<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// 确保错误处理系统已加载
if (!class_exists('TTDF_ErrorHandler')) {
    require_once __DIR__ . '/ErrorHandler.php';
}

/**
 * 路由异常类
 */
class RouteException extends Exception
{
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

/**
 * TTDF 调试类
 * 现在基于统一的错误处理系统
 */
class TTDF_Debug
{
    /** @var string 日志文件路径 */
    private static $logFile;
    
    /** @var bool 是否已启用 */
    private static $enabled = false;
    
    /** @var bool 是否已初始化 */
    private static $initialized = false;
    
    /** @var array 路由错误记录 */
    private static $routeErrors = [];
    
    /** @var float 开始时间 */
    private static $startTime;
    
    /** @var int 内存峰值 */
    private static $memoryPeak = 0;
    
    /** @var TTDF_ErrorHandler 错误处理器实例 */
    private static $errorHandler;

    /**
     * 初始化调试功能
     */
    public static function init()
    {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        self::$startTime = microtime(true);
        self::$memoryPeak = memory_get_peak_usage(true);

        if (defined('TTDF_CONFIG') && TTDF_CONFIG['DEBUG']) {
            self::$enabled = true;
            self::$logFile = dirname(__DIR__, 2) . '/logs/debug.log';
            self::$errorHandler = TTDF_ErrorHandler::getInstance();

            try {
                $logDir = dirname(self::$logFile);
                if (!file_exists($logDir)) {
                    if (!@mkdir($logDir, 0755, true) && !is_dir($logDir)) {
                        throw new RuntimeException("Failed to create log directory: {$logDir}");
                    }
                }

                if (!file_exists(self::$logFile)) {
                    if (!@touch(self::$logFile)) {
                        throw new RuntimeException("Failed to create log file: " . self::$logFile);
                    }
                    @chmod(self::$logFile, 0666);
                }

                if (!is_writable(self::$logFile)) {
                    throw new RuntimeException("Log file is not writable: " . self::$logFile);
                }

                @file_put_contents(
                    self::$logFile,
                    "=== DEBUG LOG STARTED " . date('Y-m-d H:i:s') . " ===\n" .
                        "PID: " . getmypid() . " | PHP: " . PHP_VERSION . "\n",
                    LOCK_EX
                );

                // 使用统一的错误处理系统
                if (self::$errorHandler) {
                    self::$errorHandler->setLogFile(self::$logFile);
                    self::$errorHandler->register();
                } else {
                    // 回退到原有的错误处理方式
                    error_reporting(E_ALL);
                    ini_set('display_errors', '0');
                    ini_set('log_errors', '1');
                    ini_set('error_log', self::$logFile);

                    set_error_handler([__CLASS__, 'handleError']);
                    set_exception_handler([__CLASS__, 'handleException']);
                    register_shutdown_function([__CLASS__, 'shutdownHandler']);
                }

                self::registerRouteHooks();
                self::logSystemInfo();
            } catch (Exception $e) {
                self::$enabled = false;
                error_log("TTDF_Debug init failed: " . $e->getMessage());
            }
        }
    }

    /**
     * 记录系统信息
     */
    private static function logSystemInfo()
    {
        self::log("Debug system initialized", E_USER_NOTICE);
        self::log("PHP Version: " . PHP_VERSION, E_USER_NOTICE);
        self::log("TTDF Version: " . (defined('__FRAMEWORK_VER__') ? __FRAMEWORK_VER__ : 'unknown'), E_USER_NOTICE);
        self::log("Server: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'unknown'), E_USER_NOTICE);
        self::log("OS: " . php_uname(), E_USER_NOTICE);
        self::log("Memory Limit: " . ini_get('memory_limit'), E_USER_NOTICE);
    }

    /**
     * 注册路由钩子
     */
    private static function registerRouteHooks()
    {
        if (!function_exists('add_action')) {
            return;
        }

        add_action('route_matched', function ($route) {
            self::log("Route matched: " . self::sanitizeOutput($route), E_USER_NOTICE);
        });

        add_action('route_not_found', function ($request) {
            $error = "Route not found: " . ($request->getRequestUri() ?? 'unknown');
            self::logRouteError($error);
            self::log($error, E_USER_WARNING);
        });

        add_action('route_param_invalid', function ($param, $value, $rule) {
            $error = sprintf(
                "Route parameter invalid - Param: %s, Value: %s, Rule: %s",
                $param,
                self::sanitizeOutput($value),
                self::sanitizeOutput($rule)
            );
            self::logRouteError($error);
            self::log($error, E_USER_WARNING);
        });

        add_action('route_execute_before', function ($route, $params) {
            self::log(sprintf(
                "Executing route: %s\nParams: %s",
                self::sanitizeOutput($route),
                self::sanitizeOutput($params)
            ), E_USER_NOTICE);
        });

        add_action('route_execute_after', function ($route, $response) {
            self::log(sprintf(
                "Route executed: %s\nResponse: %s",
                self::sanitizeOutput($route),
                self::sanitizeOutput($response)
            ), E_USER_NOTICE);
            self::logPerformance();
        });
    }

    /**
     * 安全输出变量内容
     */
    private static function sanitizeOutput($var)
    {
        if (is_object($var)) {
            if (method_exists($var, '__toString')) {
                return (string)$var;
            }
            return get_class($var) . " Object";
        }

        if (is_resource($var)) {
            return get_resource_type($var) . " Resource";
        }

        if (is_array($var)) {
            return "Array[" . count($var) . "]";
        }

        return print_r($var, true);
    }

    /**
     * 记录性能信息
     */
    private static function logPerformance()
    {
        $currentMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);

        self::log(sprintf(
            "Performance: %.2fms | Memory: %s | Peak: %s",
            (microtime(true) - self::$startTime) * 1000,
            self::formatMemory($currentMemory),
            self::formatMemory($peakMemory)
        ), E_USER_NOTICE);
    }

    /**
     * 记录路由错误
     */
    private static function logRouteError($error)
    {
        if (!self::$enabled) return;

        $requestInfo = [
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'time' => date('Y-m-d H:i:s')
        ];

        self::$routeErrors[] = [
            'error' => $error,
            'request' => $requestInfo
        ];

        $logMessage = sprintf(
            "[%s] ROUTE_ERROR: %s\nRequest: %s %s\n",
            $requestInfo['time'],
            $error,
            $requestInfo['method'],
            $requestInfo['uri']
        );

        self::writeLog($logMessage);
    }

    /**
     * 处理异常 (向后兼容)
     * 现在委托给统一的错误处理系统
     */
    public static function handleException(Throwable $exception)
    {
        if (!self::$enabled) return;

        if (self::$errorHandler) {
            self::$errorHandler->handleException($exception);
        } else {
            // 回退到原有处理方式
            $isRouteException = $exception instanceof RouteException;
            if ($isRouteException) {
                self::logRouteError("Route Exception: " . $exception->getMessage());
            }

            $logMessage = sprintf(
                "[%s] %sEXCEPTION: %s (Code: %d)\nFile: %s:%d\n%s\n",
                date('Y-m-d H:i:s'),
                $isRouteException ? 'ROUTE_' : '',
                $exception->getMessage(),
                $exception->getCode(),
                $exception->getFile(),
                $exception->getLine(),
                $exception->getTraceAsString()
            );

            self::writeLog($logMessage);
            self::logRequest();

            if ($isRouteException) {
                http_response_code(500);
                exit(1);
            }
        }
    }

    /**
     * 错误处理 (向后兼容)
     * 现在委托给统一的错误处理系统
     */
    public static function handleError($level, $message, $file = '', $line = 0, $context = [])
    {
        if (!self::$enabled) return false;

        if (self::$errorHandler) {
            return self::$errorHandler->handleError($level, $message, $file, $line, $context);
        } else {
            // 回退到原有处理方式
            if (error_reporting() & $level) {
                $errorType = self::getErrorType($level);
                $logMessage = sprintf(
                    "[%s] %s: %s in %s on line %d\n",
                    date('Y-m-d H:i:s'),
                    $errorType,
                    $message,
                    $file,
                    $line
                );

                if (in_array($level, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
                    $logMessage .= "Backtrace:\n" . self::sanitizeOutput(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)) . "\n";
                }

                self::writeLog($logMessage);

                if (in_array($level, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
                    self::logRequest();
                    exit(1);
                }
            }
        }

        return false;
    }

    /**
     * 关闭处理函数 (向后兼容)
     * 现在委托给统一的错误处理系统
     */
    public static function shutdownHandler()
    {
        if (self::$enabled) {
            if (self::$errorHandler) {
                self::$errorHandler->handleShutdown();
            } else {
                // 回退到原有处理方式
                $error = error_get_last();
                if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
                    self::handleError(
                        $error['type'],
                        $error['message'],
                        $error['file'],
                        $error['line']
                    );
                }
            }

            // 确保所有日志已写入
            if (self::$logFile && file_exists(self::$logFile)) {
                @fflush(fopen(self::$logFile, 'a'));
            }
        }
    }

    /**
     * 记录请求信息
     */
    private static function logRequest()
    {
        if (!self::$enabled) return;

        $requestInfo = [
            'Time' => date('Y-m-d H:i:s'),
            'URI' => $_SERVER['REQUEST_URI'] ?? '',
            'Method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'IP' => $_SERVER['REMOTE_ADDR'] ?? '',
            'Referer' => $_SERVER['HTTP_REFERER'] ?? '',
            'User Agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'Session' => isset($_SESSION) ? self::sanitizeOutput($_SESSION) : 'None',
            'Cookies' => self::sanitizeOutput($_COOKIE),
            'Post Data' => self::sanitizeOutput($_POST),
            'Get Data' => self::sanitizeOutput($_GET),
            'Route Errors' => !empty(self::$routeErrors) ? self::sanitizeOutput(self::$routeErrors) : 'None'
        ];

        $logMessage = "REQUEST DETAILS:\n";
        foreach ($requestInfo as $key => $value) {
            $logMessage .= sprintf("%-12s: %s\n", $key, $value);
        }

        self::writeLog($logMessage);
    }

    /**
     * 写入日志
     */
    private static function writeLog($message)
    {
        if (!self::$enabled || !self::$logFile) return;

        $prefix = sprintf(
            "[PID:%d MEM:%s] ",
            getmypid(),
            self::formatMemory(memory_get_usage(true))
        );

        $lines = explode("\n", $message);
        $fullMessage = '';

        foreach ($lines as $line) {
            $fullMessage .= $prefix . $line . "\n";
        }

        @file_put_contents(
            self::$logFile,
            $fullMessage,
            FILE_APPEND | LOCK_EX
        );
    }

    /**
     * 格式化内存大小
     */
    private static function formatMemory($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes >= 1024 && $i < 4; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . $units[$i];
    }

    /**
     * 获取错误类型名称
     */
    private static function getErrorType($level)
    {
        $map = [
            E_ERROR => 'ERROR',
            E_WARNING => 'WARNING',
            E_PARSE => 'PARSE',
            E_NOTICE => 'NOTICE',
            E_CORE_ERROR => 'CORE_ERROR',
            E_CORE_WARNING => 'CORE_WARNING',
            E_COMPILE_ERROR => 'COMPILE_ERROR',
            E_COMPILE_WARNING => 'COMPILE_WARNING',
            E_USER_ERROR => 'USER_ERROR',
            E_USER_WARNING => 'USER_WARNING',
            E_USER_NOTICE => 'USER_NOTICE',
            E_STRICT => 'STRICT',
            E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
            E_DEPRECATED => 'DEPRECATED',
            E_USER_DEPRECATED => 'USER_DEPRECATED'
        ];

        return $map[$level] ?? 'UNKNOWN(' . $level . ')';
    }

    /**
     * 记录调试信息
     * 现在使用统一的错误处理系统
     */
    public static function log($message, $level = E_USER_NOTICE)
    {
        if (self::$enabled) {
            if (self::$errorHandler) {
                // 使用统一的错误处理系统
                $context = ['level' => $level];
                if ($level === E_USER_ERROR) {
                    self::$errorHandler->error($message, $context);
                } elseif ($level === E_USER_WARNING) {
                    self::$errorHandler->warning($message, $context);
                } else {
                    // E_USER_NOTICE 和其他级别都作为 info 处理
                    self::$errorHandler->info($message, $context);
                }
            } else {
                // 回退到原有处理方式
                $errorType = self::getErrorType($level);
                $logMessage = sprintf(
                    "[%s] %s: %s",
                    date('Y-m-d H:i:s'),
                    $errorType,
                    is_string($message) ? $message : self::sanitizeOutput($message)
                );

                self::writeLog($logMessage);
            }
        }
    }

    /**
     * 获取路由错误
     */
    public static function getRouteErrors()
    {
        return self::$routeErrors;
    }

    /**
     * 记录API相关调试信息
     * 现在使用统一的错误处理系统
     */
    public static function logApiInfo($message, $data = null)
    {
        if (!self::$enabled) return;

        if (self::$errorHandler) {
            $context = $data !== null ? ['data' => $data] : [];
            self::$errorHandler->info("API_DEBUG: " . $message, $context);
        } else {
            // 回退到原有处理方式
            $logMessage = "API_DEBUG: " . $message;
            if ($data !== null) {
                $logMessage .= " | Data: " . self::sanitizeOutput($data);
            }
            self::log($logMessage, E_USER_NOTICE);
        }
    }

    /**
     * 记录API错误
     * 现在使用统一的错误处理系统
     */
    public static function logApiError($message, $exception = null)
    {
        if (!self::$enabled) return;

        if (self::$errorHandler) {
            $context = [];
            if ($exception instanceof Throwable) {
                $context['exception'] = [
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine()
                ];
            }
            self::$errorHandler->error("API_ERROR: " . $message, $context);
        } else {
            // 回退到原有处理方式
            $logMessage = "API_ERROR: " . $message;
            if ($exception instanceof Throwable) {
                $logMessage .= " | Exception: " . $exception->getMessage() .
                    " in " . $exception->getFile() .
                    " on line " . $exception->getLine();
            }
            self::log($logMessage, E_USER_WARNING);
        }
    }

    /**
     * 记录API请求处理过程
     * 现在使用统一的错误处理系统
     */
    public static function logApiProcess($stage, $details = [])
    {
        if (!self::$enabled) return;

        if (self::$errorHandler) {
            $context = array_merge(['stage' => $stage], $details);
            self::$errorHandler->info("API_PROCESS", $context);
        } else {
            // 回退到原有处理方式
            $logMessage = "API_PROCESS[" . $stage . "]: " . json_encode($details, JSON_UNESCAPED_UNICODE);
            self::log($logMessage, E_USER_NOTICE);
        }
    }
}

// 初始化
TTDF_Debug::init();
