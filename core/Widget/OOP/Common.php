<?php

/**
 * Get 方法
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * Get 方法类
 * 提供页面头部、尾部等通用功能
 */
class Get
{
    use ErrorHandler, SingletonWidget;

    /** @var TTDF_ErrorHandler 错误处理器实例 */
    private static $errorHandler;

    private function __construct() {}
    private function __clone() {}
    public function __wakeup() {}

    /**
     * 初始化错误处理器和缓存管理器
     */
    private static function initErrorHandler(): void
    {
        if (!self::$errorHandler) {
            self::$errorHandler = TTDF_ErrorHandler::getInstance();
            self::$errorHandler->init();
        }

        // 初始化缓存管理器
        if (class_exists('TTDF_CacheManager')) {
            TTDF_CacheManager::init();
        }
    }

    /**
     * 输出header头部元数据和link标签
     * 
     * 此方法会基于一组预定义的键名来过滤相关数据（预定义键名如下：
     * - 'description'
     * - 'keywords'
     * - 'generator'
     * - 'template'
     * - 'pingback'
     * - 'xmlrpc'
     * - 'wlw'
     * - 'rss2'
     * - 'rss1'
     * - 'commentReply'
     * - 'antiSpam'
     * - 'social'
     * - 'atom'
     * 对于link标签，可以指定rel属性值来排除
     * 
     * @param bool $echo 当设置为 true 时，会直接输出；当设置为 false 时，则返回结果值。
     * @param string|null $exclude 要排除的meta或link标签，多个用逗号分隔
     * @return string 头部信息输出
     */
    public static function Header(bool $echo = true, ?string $exclude = null): string
    {
        try {
            self::initErrorHandler();

            // 生成缓存键
            $cacheKey = TTDF_CacheManager::generateKey('header', ['exclude' => $exclude]);

            // 检查缓存
            $content = TTDF_CacheManager::get($cacheKey);
            if ($content === null) {
                ob_start();
                self::getArchive()->header(); // 获取原始 header HTML
                $content = ob_get_clean();

                // 移除指定的 meta 或 link 标签
                if ($exclude) {
                    $excluded = array_map('trim', explode(',', $exclude));
                    foreach ($excluded as $item) {
                        if (empty($item)) continue;

                        // 匹配 meta 和 link 标签
                        $pattern = '/\s*<(meta\s+(name|property)=["\']' . preg_quote($item, '/') . '["\']|link\s+rel=["\']' . preg_quote($item, '/') . '["\'])[^>]*>\s*/i';
                        $content = preg_replace($pattern, '', $content);
                    }
                }

                // 在所有 meta 和 link 标签前添加四个空格
                $content = preg_replace('/(<(meta|link)[^>]*>)/', '    $1', $content);

                // 格式化 HTML：清理多余空行，保留合理缩进
                $content = preg_replace('/\n\s*\n/', "\n", $content); // 合并连续空行
                $content = preg_replace('/^\s+/m', '', $content);      // 移除行首多余空格

                // 缓存结果
                TTDF_CacheManager::set($cacheKey, $content);
            }

            if ($echo) {
                echo $content;
            }

            return $content;
        } catch (Exception $e) {
            self::$errorHandler->error('获取Header失败', ['exclude' => $exclude], $e);
            return '';
        }
    }

    /**
     * 执行页脚自定义内容
     * 调用 footer 钩子，允许插件修改页脚内容
     * 
     * @param bool $echo 是否直接输出，默认 true
     * @return string|null 返回页脚 HTML（如果 $echo=false）
     */
    public static function Footer(bool $echo = true): ?string
    {
        try {
            self::initErrorHandler();

            // 检查缓存
            $cacheKey = 'footer';
            $content = TTDF_CacheManager::get($cacheKey);
            if ($content === null) {
                // 获取 Archive 实例
                $archive = self::getArchive();

                // 先触发 footer 钩子，让插件可以修改内容
                if (method_exists($archive, 'pluginHandle')) {
                    $archive->pluginHandle()->call('footer', $archive);
                }

                // 捕获输出
                ob_start();
                $archive->footer();
                $content = ob_get_clean();

                // 缓存结果
                TTDF_CacheManager::set($cacheKey, $content);
            }

            // 如果 $echo=true，直接输出；否则返回内容
            if ($echo) {
                echo $content;
                return null;
            }

            return $content;
        } catch (Exception $e) {
            self::$errorHandler->error('获取Footer失败', [], $e);
            return null;
        }
    }

    /**
     * 获取站点URL
     * 
     * @param bool $echo 当设置为 true 时，会直接输出；当设置为 false 时，则返回结果值。
     * @return string
     */
    public static function SiteUrl(bool $echo = true): string
    {
        try {
            self::initErrorHandler();

            // 检查缓存
            $cacheKey = 'site_url';
            $siteUrl = TTDF_CacheManager::get($cacheKey);

            if ($siteUrl === null) {
                $siteUrl = \Helper::options()->siteUrl ?? '';
                TTDF_CacheManager::set($cacheKey, $siteUrl, 3600); // 缓存1小时
            }

            if ($echo) {
                echo $siteUrl;
            }

            return $siteUrl;
        } catch (Exception $e) {
            self::$errorHandler->error('获取站点URL失败', [], $e);
            return '';
        }
    }
    /**
     * 获取站点域名
     * 
     * @param bool $echo 当设置为 true 时，会直接输出；当设置为 false 时，则返回结果值。
     * @return string
     */
    public static function SiteDomain(bool $echo = true): string
    {
        try {
            self::initErrorHandler();

            // 检查缓存
            $cacheKey = 'site_domain';
            $siteDomain = TTDF_CacheManager::get($cacheKey);

            if ($siteDomain === null) {
                $siteUrl = \Helper::options()->siteUrl ?? '';
                $siteDomain = parse_url($siteUrl, PHP_URL_HOST) ?? '';
                TTDF_CacheManager::set($cacheKey, $siteDomain, 3600); // 缓存1小时
            }

            if ($echo) {
                echo $siteDomain;
            }

            return $siteDomain;
        } catch (Exception $e) {
            self::$errorHandler->error('获取站点域名失败', [], $e);
            return '';
        }
    }
    /**
     * 获取站点名称
     * 
     * @param bool $echo 当设置为 true 时，会直接输出；当设置为 false 时，则返回结果值。
     * @return string
     */
    public static function SiteName(bool $echo = true): string
    {
        try {
            self::initErrorHandler();

            // 检查缓存
            $cacheKey = 'site_name';
            $siteName = TTDF_CacheManager::get($cacheKey);

            if ($siteName === null) {
                $siteName = \Helper::options()->title ?? '';
                TTDF_CacheManager::set($cacheKey, $siteName, 3600); // 缓存1小时
            }

            if ($echo) {
                echo $siteName;
            }

            return $siteName;
        } catch (Exception $e) {
            self::$errorHandler->error('获取站点名称失败', [], $e);
            return '';
        }
    }
    /**
     * 获取站点关键字
     */
    public static function SiteKeywords(?bool $echo = true)
    {
        try {
            $SiteKeywords = \Helper::options()->keywords;

            if ($echo) echo $SiteKeywords;

            return $SiteKeywords;
        } catch (Exception $e) {
            return self::handleError('获取站点关键字失败', $e);
        }
    }
    /**
     * 获取站点描述
     * 
     * @param bool|null $echo 当设置为 true 时，会直接输出；
     *                        当设置为 false 时，则返回结果值。
     * @return string
     */
    public static function SiteDescription(?bool $echo = true)
    {
        try {
            $SiteDescription = \Helper::options()->description;

            if ($echo) echo $SiteDescription;

            return $SiteDescription;
        } catch (Exception $e) {
            return self::handleError('获取站点描述失败', $e);
        }
    }

    /**
     * 返回堆栈（数组）中每一行的值
     * 一般用于循环输出文章
     *
     * @return mixed
     */
    public static function Next()
    {
        try {
            if (method_exists(self::getArchive(), 'Next')) {
                return self::getArchive()->Next();
            }
            throw new Exception('Next 方法不存在');
        } catch (Exception $e) {
            return self::handleError('Next 调用失败', $e, null);
        }
    }

    /**
     * 获取框架版本
     *
     * @param bool|null $echo 当设置为 true 时，会直接输出；
     *                        当设置为 false 时，则返回结果值。
     * @return string|null 
     * @throws Exception
     */
    public static function FrameworkVer(?bool $echo = true)
    {
        try {
            $FrameworkVer = __FRAMEWORK_VER__;

            if ($echo) echo $FrameworkVer;

            return $FrameworkVer;
        } catch (Exception $e) {
            return self::handleError('获取框架版本失败', $e);
        }
    }

    /**
     * 获取 typecho 版本
     *
     * @param bool|null $echo 当设置为 true 时，会直接输出；
     *                        当设置为 false 时，则返回结果值。
     * @return string|null 
     * @throws Exception
     */
    public static function TypechoVer(?bool $echo = true)
    {
        try {
            $TypechoVer = \Helper::options()->Version;

            if ($echo) echo $TypechoVer;

            return $TypechoVer;
        } catch (Exception $e) {
            return self::handleError('获取Typecho版本失败', $e);
        }
    }

    /**
     * 获取TTDF主题配置项
     * 
     * @param string $name 配置名称
     * @param mixed $default 默认值
     * @param bool $ignoreTheme 是否忽略主题名前缀，默认为false
     * @return mixed 配置值
     */
    private static function TtdfOption(string $name, $default = null, bool $ignoreTheme = false)
    {
        try {
            $themeName = Helper::options()->theme;

            // 如果忽略主题名，需要动态处理主题名前缀
            if ($ignoreTheme) {
                // 检查字段名是否以当前主题名开头
                $themePrefix = $themeName . '_';
                if (strpos($name, $themePrefix) === 0) {
                    // 移除当前主题名前缀
                    $nameWithoutPrefix = substr($name, strlen($themePrefix));
                    return TTDF_Db::getTtdf($nameWithoutPrefix, $default);
                } else {
                    // 直接使用原字段名查询
                    return TTDF_Db::getTtdf($name, $default);
                }
            }

            $fullName = $themeName . '_' . $name;

            // 首先尝试获取带主题名前缀的配置项
            $value = TTDF_Db::getTtdf($fullName, null);

            // 如果没有找到，则回退到原来的名称
            if ($value === null) {
                $value = TTDF_Db::getTtdf($name, $default);
            }

            return $value;
        } catch (Exception $e) {
            return $default;
        }
    }

    /**
     * 获取配置参数
     * 
     * @param string $param 参数名
     * @param bool|null $echo 是否输出，默认为false
     * @param bool $ignoreTheme 是否忽略主题名前缀，默认为false
     * @return mixed 配置值
     */
    public static function Options($param, ?bool $echo = false, bool $ignoreTheme = false)
    {
        try {
            // 首先尝试从 ttdf 表获取配置参数
            $value = self::TtdfOption($param, null, $ignoreTheme);

            // 如果 ttdf 表中没有该配置
            if ($value === null) {
                $value = Helper::options()->$param;
            }

            if ($echo) {
                echo $value;
            }

            return $value;
        } catch (Exception $e) {
            return self::handleError('获取配置参数失败', $e);
        }
    }

    // 获取字段
    public static function Fields($param)
    {
        try {
            return self::getArchive()->fields->$param;
        } catch (Exception $e) {
            return self::handleError('获取字段失败', $e);
        }
    }

    /**
     * 引入文件
     * 
     * @param string $file 文件名
     * @return mixed
     */
    public static function Need($file)
    {
        try {
            return self::getArchive()->need($file);
        } catch (Exception $e) {
            return self::handleError('获取文件失败', $e);
        }
    }

    /**
     * 拼接文件路径
     * 
     * @param string $base 基础路径
     * @param string $file 文件名
     * @return string
     */
    private static function buildFilePath($base, $file)
    {
        return $base . '/' . $file . '.php';
    }

    // 引入文件
    public static function File($file)
    {
        return self::Need(self::buildFilePath('', $file));
    }

    /**
     * 引入Template目录文件
     * 
     * @param string $file 模板文件名
     * @return mixed
     */
    public static function Template($file)
    {
        return self::loadDirFile('app/template', $file, '加载Template失败');
    }

    /**
     * 引入app/layouts目录文件
     * 
     * @param string $file 布局文件名
     * @return mixed
     */
    public static function Layouts($file)
    {
        return self::loadDirFile('app/layouts', $file, '加载Layouts失败');
    }

    /**
     * 引入app/components目录文件
     * 
     * @param string $file 组件文件名
     * @return mixed
     */
    public static function Components($file)
    {
        return self::loadDirFile('app/components', $file, '加载Components失败');
    }

    /**
     * 通用目录文件加载方法
     * 
     * @param string $dirName 目录名（默认大小写）
     * @param string $file 文件名
     * @param string $errorMsg 错误消息
     * @return mixed
     */
    private static function loadDirFile($dirName, $file, $errorMsg)
    {
        try {
            // 如果是 404 页面，特殊处理
            if (strpos($file, '404.php') !== false) {
                Typecho_Widget::widget('Widget_Archive', array('type' => 'error'));
            }

            $themeDir = self::getArchive()->getThemeDir();
            $items = scandir($themeDir);

            foreach ($items as $item) {
                if (strtolower($item) === strtolower($dirName) && is_dir($themeDir . '/' . $item)) {
                    return self::Need(self::buildFilePath($item, $file));
                }
            }

            return self::Need(self::buildFilePath($dirName, $file));
        } catch (Exception $e) {
            error_log("Searching for dir: {$dirName} in {$themeDir}");
            error_log("Trying to load: " . self::buildFilePath($dirName, $file));
            return self::handleError($errorMsg, $e);
        }
    }

    // 判断页面类型
    public static function Is($type)
    {
        try {
            return self::getArchive()->is($type);
        } catch (Exception $e) {
            return self::handleError('判断页面类型失败', $e, false);
        }
    }
    /**
     * 判断是否为指定HTTP状态码
     * 
     * @param int $code HTTP状态码
     * @return bool
     */
    public static function IsHttpCode($code)
    {
        try {
            $currentCode = http_response_code();
            return $currentCode === (int)$code;
        } catch (Exception $e) {
            return self::handleError('判断HTTP状态码失败', $e, false);
        }
    }

    // 分页导航
    public static function PageNav($prev = '&laquo; 前一页', $next = '后一页 &raquo;')
    {
        try {
            self::getArchive()->pageNav($prev, $next);
        } catch (Exception $e) {
            self::handleError('分页导航失败', $e);
        }
    }

    // 获取总数
    public static function Total()
    {
        try {
            return self::getArchive()->getTotal();
        } catch (Exception $e) {
            return self::handleError('获取总数失败', $e, 0);
        }
    }

    // 获取页面大小
    public static function PageSize()
    {
        try {
            return self::getArchive()->parameter->pageSize;
        } catch (Exception $e) {
            return self::handleError('获取页面大小失败', $e, 10);
        }
    }

    // 获取页面链接
    public static function PageLink($html = '', $next = '', $onlyUrl = false, $echo = true)
    {
        try {
            $widget = self::getArchive();
            ob_start();
            if ($next === 'next') {
                $widget->pageLink($html, 'next');
            } else {
                $widget->pageLink($html);
            }
            $link = ob_get_clean();
            if ($onlyUrl) {
                $url = '';
                if (is_string($link) && $link !== '' && preg_match('/href=["\']([^"\']+)["\']/', $link, $m)) {
                    $url = $m[1];
                }
                if ($echo) echo $url;
                return $url;
            }
            if ($echo) echo $link;
            return $link;
        } catch (Exception $e) {
            self::handleError('获取页面链接失败', $e);
            return '';
        }
    }

    // 获取当前页码
    public static function CurrentPage()
    {
        try {
            return self::getArchive()->_currentPage;
        } catch (Exception $e) {
            return self::handleError('获取当前页码失败', $e, 1);
        }
    }

    // 获取页面Permalink
    public static function Permalink()
    {
        try {
            return self::getArchive()->permalink();
        } catch (Exception $e) {
            return self::handleError('获取页面Url失败', $e);
        }
    }

    /**
     * 获取当前页面url
     * 
     * @param bool $echo 是否输出
     * @param bool $removePort 是否移除端口号
     * @param array|null $excludeParams 需要屏蔽的参数名数组
     * @param bool $removeAllQuery 是否移除所有查询参数
     * @return string|null
     */
    public static function PageUrl(
        ?bool $echo = true,
        ?bool $removePort = false,
        ?array $excludeParams = null,
        ?bool $removeAllQuery = false // 新增参数
    ) {
        try {
            // 获取协议
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";

            // 获取主机名
            $host = $_SERVER['HTTP_HOST'];

            // 移除端口号（如果需要）
            if ($removePort) {
                $host = preg_replace('/:\d+$/', '', $host);
            }

            // 处理查询参数
            $uri = $_SERVER['REQUEST_URI'];
            if ($removeAllQuery) {
                // 移除所有查询参数
                $parsedUrl = parse_url($uri);
                $uri = $parsedUrl['path'] ?? '/';
            } elseif ($excludeParams && is_array($excludeParams)) {
                $parsedUrl = parse_url($uri);
                $query = $parsedUrl['query'] ?? '';

                // 解析查询参数
                parse_str($query, $queryParams);

                // 移除需要屏蔽的参数
                foreach ($excludeParams as $param) {
                    unset($queryParams[$param]);
                }

                // 重新构建查询字符串
                $newQuery = http_build_query($queryParams);
                $uri = $parsedUrl['path'] . ($newQuery ? "?$newQuery" : '');
            }

            // 拼接完整URL
            $url = $protocol . '://' . $host . $uri;

            if ($echo) {
                echo $url;
            }

            return $url;
        } catch (Exception $e) {
            return self::handleError('获取当前页面url失败', $e);
        }
    }

    /**
     * 获取assets url
     * @param string $file 资源文件路径
     * @param bool $echo 是否输出
     * @param bool $appendVersion 是否自动拼接主题版本号，默认为true
     * @return string
     */
    public static function Assets($file = '', ?bool $echo = true, bool $appendVersion = true)
    {
        try {
            $url = '';

            // 检查是否启用CDN
            if (config('app.assets.cdn.enabled', false)) {
                $baseUrl = config('app.assets.cdn.url', Helper::options()->themeUrl . '/assets/');
                $url = rtrim((string)$baseUrl, '/') . '/' . ltrim((string)$file, '/');
            } else {
                $assetsDir = config('app.assets.dir', 'assets/');
                // 直接获取主题URL
                $themeBaseUrl = Helper::options()->themeUrl;
                // 移除所有路径的开头和结尾斜杠，然后统一拼接
                $themeBaseUrl = rtrim((string)$themeBaseUrl, '/');
                $assetsDir = trim((string)$assetsDir, '/');
                $file = ltrim((string)$file, '/');
                $url = $themeBaseUrl . '/' . $assetsDir . '/' . $file;
            }

            // 默认追加版本号
            if ($appendVersion) {
                $themeVersion = GetTheme::Ver(false);
                $themeVersion = $themeVersion ?? '';
                if ($themeVersion !== '') {
                    $url .= (strpos($url, '?') !== false ? '&' : '?') . 'ver=' . urlencode((string)$themeVersion);
                }
            }

            if ($echo) {
                echo $url;
                return null;
            } else {
                return $url;
            }
        } catch (Exception $e) {
            return self::handleError('获取资源URL失败', $e);
        }
    }

    /**
     * 获取客户端ip
     * @return string
     */
    public static function ClientIp(?bool $echo = true)
    {
        try {
            $ip = TTDF_Widget::GetClientIp();
            if ($echo) {
                echo $ip;
            }
            return $ip;
        } catch (Exception $e) {
            return self::handleError('获取客户端ip失败', $e);
        }
    }

    public static function ClientUA(?bool $echo = true)
    {
        try {
            $ua = $_SERVER['HTTP_USER_AGENT'];
            if ($echo) {
                echo $ua;
            } else {
                return $ua;
            }
        } catch (Exception $e) {
            return self::handleError('获取客户端UA失败', $e);
        }
    }
}
