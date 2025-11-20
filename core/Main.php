<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

if (version_compare(PHP_VERSION, '8.1', '<')) {
    exit('PHP版本需要8.1及以上, 请先升级!');
}

// 配置文件加载
$configPath = __DIR__ . '/../app/app.config.php';
if (!file_exists($configPath)) {
    $configPath = __DIR__ . '/../app/TTDF.Config.php';
}

if (!file_exists($configPath)) {
    throw new RuntimeException('TTDF配置文件未找到! 请检查路径: ' . $configPath);
}

$configData = require $configPath;
if (!is_array($configData)) {
    throw new RuntimeException('TTDF配置文件格式无效');
}

// 初始化配置管理器
TTDF_ConfigManager::init($configData);

// 定义框架常量
define('__FRAMEWORK_VER__', '4.2.1');
define('__TYPECHO_GRAVATAR_PREFIX__', TTDF_ConfigManager::get('modules.gravatar.prefix', 'https://cravatar.cn/avatar/'));
define('__TTDF_RESTAPI__', TTDF_ConfigManager::get('modules.restapi.enabled', false));
define('__TTDF_RESTAPI_ROUTE__', TTDF_ConfigManager::get('modules.restapi.route', 'ty-json'));

// 预先映射所有老配置键到新配置的值
$TTDF_CONFIG_ARRAY = [
    'DEBUG' => TTDF_ConfigManager::get('app.debug', false),
    'FIELDS_ENABLED' => TTDF_ConfigManager::get('app.fields.enabled', false),
    'TYAJAX_ENABLED' => TTDF_ConfigManager::get('plugins.tyajax.enabled', false),
    'COMPRESS_HTML' => TTDF_ConfigManager::get('app.compress_html', false),
    'GRAVATAR_PREFIX' => TTDF_ConfigManager::get('modules.gravatar.prefix', 'https://cravatar.cn/avatar/'),

    // REST_API 相关配置
    'REST_API' => [
        'ENABLED' => TTDF_ConfigManager::get('modules.restapi.enabled', false),
        'ROUTE' => TTDF_ConfigManager::get('modules.restapi.route', 'ty-json'),
        'OVERRIDE_SETTING' => TTDF_ConfigManager::get('modules.restapi.override_setting', 'RESTAPI_Switch'),
        'TOKEN' => TTDF_ConfigManager::get('modules.restapi.token.value', '1778273540'),
        'LIMIT' => TTDF_ConfigManager::get('modules.restapi.limit', []),
        'HEADERS' => TTDF_ConfigManager::get('modules.restapi.headers', []),
    ],
];

// 定义 TTDF_CONFIG 常量
define('TTDF_CONFIG', $TTDF_CONFIG_ARRAY);

/**
 * TTDF 配置管理器
 * 支持不区分大小写的配置访问和向后兼容的配置键映射
 */
class TTDF_ConfigManager
{
    /** @var array 配置数据 */
    private static $config = [];

    /** @var array 配置键映射（小写 => 原始键） */
    private static $keyMap = [];

    /** @var array 配置值缓存 */
    private static $cache = [];

    /** @var bool 是否已初始化 */
    private static $initialized = false;

    /** @var array 老配置键到新配置路径的映射 */
    private static $legacyKeyMap = [
        'DEBUG' => 'app.debug',
        'FIELDS_ENABLED' => 'app.fields.enabled',
        'TYAJAX_ENABLED' => 'plugins.tyajax.enabled',
        'COMPRESS_HTML' => 'app.compress_html',
        'GRAVATAR_PREFIX' => 'modules.gravatar.prefix',
        'REST_API' => 'modules.restapi',
    ];

    /**
     * 初始化配置管理器
     * 
     * @param array $config 配置数组
     * @throws RuntimeException 如果重复初始化
     */
    public static function init(array $config): void
    {
        if (self::$initialized) {
            throw new RuntimeException('配置管理器已经初始化，不能重复初始化');
        }

        self::$config = $config;
        self::buildKeyMap($config);
        self::$initialized = true;
    }

    /**
     * 构建键映射
     * 
     * @param array $config 配置数组
     * @param string $prefix 前缀
     */
    private static function buildKeyMap(array $config, string $prefix = '')
    {
        foreach ($config as $key => $value) {
            $fullKey = $prefix ? $prefix . '.' . $key : $key;
            $lowerKey = strtolower($fullKey);
            self::$keyMap[$lowerKey] = $fullKey;

            if (is_array($value)) {
                self::buildKeyMap($value, $fullKey);
            }
        }
    }

    /**
     * 获取配置值
     * 
     * @param string $key 配置键（支持点号分隔，不区分大小写）
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        // 生成缓存键
        $cacheKey = $key . '::' . serialize($default);

        // 检查缓存
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        $lowerKey = strtolower($key);
        $result = null;

        // 检查是否有映射的键
        if (isset(self::$keyMap[$lowerKey])) {
            $actualKey = self::$keyMap[$lowerKey];
            $result = self::getNestedValue(self::$config, $actualKey, $default);
        } else {
            // 直接尝试获取（兼容原有方式）
            $result = self::getNestedValue(self::$config, $key, $default);
        }

        // 缓存结果
        self::$cache[$cacheKey] = $result;

        return $result;
    }

    /**
     * 获取老配置键对应的值（向后兼容）
     * 
     * @param string $legacyKey 老配置键
     * @return mixed
     */
    public static function getLegacyKey(string $legacyKey)
    {
        // 检查是否有映射
        if (isset(self::$legacyKeyMap[$legacyKey])) {
            $newKey = self::$legacyKeyMap[$legacyKey];
            return self::get($newKey);
        }

        // 特殊处理 REST_API 子键
        if (strpos($legacyKey, 'REST_API.') === 0) {
            $subKey = substr($legacyKey, 9); // 移除 'REST_API.' 前缀
            $keyMap = [
                'ENABLED' => 'modules.restapi.enabled',
                'ROUTE' => 'modules.restapi.route',
                'OVERRIDE_SETTING' => 'modules.restapi.override_setting',
                'TOKEN' => 'modules.restapi.token',
                'LIMIT' => 'modules.restapi.limit',
                'HEADERS' => 'modules.restapi.headers',
            ];

            if (isset($keyMap[$subKey])) {
                return self::get($keyMap[$subKey]);
            }
        }

        return null;
    }

    /**
     * 检查老配置键是否存在
     * 
     * @param string $legacyKey 老配置键
     * @return bool
     */
    public static function hasLegacyKey(string $legacyKey): bool
    {
        return self::getLegacyKey($legacyKey) !== null;
    }

    /**
     * 获取嵌套配置值
     * 
     * @param array $config 配置数组
     * @param string $key 配置键
     * @param mixed $default 默认值
     * @return mixed
     */
    private static function getNestedValue(array $config, string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $config;

        foreach ($keys as $k) {
            if (!is_array($value) || !isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * 检查配置是否存在
     * 
     * @param string $key 配置键
     * @return bool
     */
    public static function has(string $key): bool
    {
        $lowerKey = strtolower($key);
        return isset(self::$keyMap[$lowerKey]) || self::getNestedValue(self::$config, $key) !== null;
    }

    /**
     * 获取所有配置
     * 
     * @return array
     */
    public static function all(): array
    {
        return self::$config;
    }

    /**
     * 清除配置缓存
     * 
     * @return void
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }

    /**
     * 获取缓存统计信息
     * 
     * @return array
     */
    public static function getCacheStats(): array
    {
        return [
            'cache_size' => count(self::$cache),
            'initialized' => self::$initialized,
            'config_keys' => count(self::$keyMap)
        ];
    }
}

/**
 * 全局配置访问函数
 * 
 * @param string $key 配置键（支持点号分隔，不区分大小写）
 * @param mixed $default 默认值
 * @return mixed
 */
function config(string $key, $default = null)
{
    return TTDF_ConfigManager::get($key, $default);
}

// ErrorHandler trait 已移至 Modules/ErrorHandler.php 文件中

/**
 * 单例Widget Trait
 */
trait SingletonWidget
{
    /** @var Widget\Archive|null */
    private static $widget;

    private static function getArchive()
    {
        if (self::$widget === null) {
            try {
                self::$widget = \Widget\Archive::widget('Widget_Archive');
            } catch (Exception $e) {
                throw new RuntimeException('初始化Widget失败: ' . $e->getMessage(), 0, $e);
            }
        }
        return self::$widget;
    }
}

class TTDF_Main
{
    /** @var array 已加载模块 */
    private static $loadedModules = [];

    /**
     * 运行框架
     */
    public static function run()
    {
        // 加载核心模块
        self::loadCoreModules();

        // 加载Widgets
        self::loadWidgets();

        // 加载可选模块
        self::loadOptionalModules();

        // 配置检查
        if (!defined('TTDF_CONFIG')) {
            throw new RuntimeException('TTDF配置未初始化');
        }

        // 初始化数据库
        TTDF_Db::init();
    }

    /**
     * 加载核心模块
     */
    private static function loadCoreModules()
    {
        require_once __DIR__ . '/Modules/ErrorHandler.php';
        require_once __DIR__ . '/Modules/Database.php';
        require_once __DIR__ . '/Modules/CacheManager.php';
        if (config('app.debug', false)) {
            require_once __DIR__ . '/Modules/Debug.php';
        }
    }

    /**
     * 加载Widgets
     */
    private static function loadWidgets()
    {
        $widgetFiles = [
            'Tools.php',
            'Hook.php',
            'TTDF.php',
            'AddRoute.php',
            'OOP/Common.php',
            'OOP/Site.php',
            'OOP/Post.php',
            'OOP/Theme.php',
            'OOP/User.php',
            'OOP/Comment.php',
        ];

        foreach ($widgetFiles as $file) {
            require_once __DIR__ . '/Widget/' . $file;
        }
    }

    /**
     * 加载可选模块
     */
    private static function loadOptionalModules()
    {
        $moduleFiles = [
            'OPP.php',
            'Api.php',
            'Options.php',
            'RouterAuto.php',
        ];

        foreach ($moduleFiles as $file) {
            require_once __DIR__ . '/Modules/' . $file;
        }

        if (config('plugins.tyajax.enabled', false)) {
            require_once __DIR__ . '/Widget/TyAjax.php';
        }
    }

    /**
     * 初始化框架
     */
    public static function init()
    {
        // 运行框架
        self::run();

        // HTML压缩
        if (config('app.compress_html', false)) {
            ob_start(function ($buffer) {
                return TTDF::compressHtml($buffer);
            });
        }
    }
}

// 只有在 Typecho 环境下才初始化框架
if (defined('__TYPECHO_ROOT_DIR__') && !defined('TTDF_TEST_MODE')) {
    // 初始化框架
    try {
        TTDF_Main::init();

        // 初始化错误处理系统
        $errorHandler = TTDF_ErrorHandler::getInstance();
        $errorHandler->init([
            'debug' => config('app.debug', false),
            'log_file' => dirname(__DIR__) . '/logs/error.log'
        ]);
    } catch (Exception $e) {
        // 框架初始化失败
        $errorHandler = TTDF_ErrorHandler::getInstance();
        $errorHandler->fatal('Framework initialization failed', [], $e);

        if (config('app.debug', false)) {
            throw $e;
        }
        error_log('Framework init error: ' . $e->getMessage());
        exit('系统初始化失败');
    }
}
