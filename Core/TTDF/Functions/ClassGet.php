<?php

/**
 * Get Class
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class Get
{
    use ErrorHandler, SingletonWidget;

    private function __construct() {}
    private function __clone() {}
    public function __wakeup() {}

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
     * @param bool|null $echo 当设置为 true 时，会直接输出；当设置为 false 时，则返回结果值。
     * @param string|null $exclude 要排除的meta或link标签，多个用逗号分隔
     * @param string|null $excludeLinkTypes 要排除的link标签的rel属性值，多个用逗号分隔
     * @return string 头部信息输出
     * @throws self::handleError()
     */
    public static function Header(?bool $echo = true, ?string $exclude = null)
    {
        try {
            ob_start();
            self::getArchive()->header(); // 获取原始 header HTML
            $content = ob_get_clean();

            // 移除指定的 meta 或 link 标签
            if ($exclude) {
                $excluded = explode(',', $exclude);
                foreach ($excluded as $item) {
                    $item = trim($item);
                    // 匹配 meta name="xxx" 或 link rel="xxx"
                    $content = preg_replace(
                        '/\s*<(meta\s+name=["\']' . preg_quote($item, '/') . '["\']|link\s+rel=["\']' . preg_quote($item, '/') . '["\'])[^>]*>\s*/i',
                        '',
                        $content
                    );
                }
            }

            // 在所有 meta 和 link 标签前添加四个空格
            $content = preg_replace('/(<(meta|link)[^>]*>)/', '    $1', $content);

            // 格式化 HTML：清理多余空行，保留合理缩进
            $content = preg_replace('/\n\s*\n/', "\n", $content); // 合并连续空行
            $content = preg_replace('/^\s+/m', '', $content);      // 移除行首多余空格（可选）

            if ($echo) {
                echo $content;
            }

            return $content;
        } catch (Exception $e) {
            return self::handleError('获取Header失败', $e);
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

            // 如果 $echo=true，直接输出；否则返回内容
            if ($echo) {
                echo $content;
                return null;
            }

            return $content;
        } catch (Exception $e) {
            return self::handleError('获取Footer失败', $e);
        }
    }

    /**
     * 获取站点URL
     * 
     * @param bool|null $echo 当设置为 true 时，会直接输出；
     *                        当设置为 false 时，则返回结果值。
     * @return string
     */
    public static function SiteUrl(?bool $echo = true)
    {
        try {
            $SiteUrl = \Helper::options()->siteUrl;

            if ($echo) echo $SiteUrl;

            return $SiteUrl;
        } catch (Exception $e) {
            return self::handleError('获取站点URL失败', $e);
        }
    }
    /**
     * 获取站点名称
     * 
     * @param bool|null $echo 当设置为 true 时，会直接输出；
     *                        当设置为false 时，则返回结果值。
     * @return string
     */
    public static function SiteName(?bool $echo = true)
    {
        try {
            $SiteName = \Helper::options()->title;

            if ($echo) echo $SiteName;

            return $SiteName;
        } catch (Exception $e) {
            return self::handleError('获取站点名称失败', $e);
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

    // 获取配置参数
    public static function Options($param, ?bool $echo = false)
    {
        try {
            $value = Helper::options()->$param;

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

    // 引入Template目录文件
    public static function Template($file)
    {
        return self::Need(self::buildFilePath('Template', $file));
    }

    // 引入Core文件
    public static function Core($file)
    {
        return self::Need(self::buildFilePath('Core', $file));
    }

    public static function CoreFunctions($file)
    {
        return self::Need(self::buildFilePath('Core/TTDF/Functions', $file));
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
    public static function PageLink($html = '', $next = '')
    {
        try {
            $widget = self::getArchive();
            if ($widget->have()) {
                $link = ($next === 'next') ? $widget->pageLink($html, 'next') : $widget->pageLink($html);
                echo $link;
            }
        } catch (Exception $e) {
            self::handleError('获取页面链接失败', $e);
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
}
