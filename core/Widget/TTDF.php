<?php

/**
 * TTDF Class
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * TTDF核心类
 * 提供计时器、HTML压缩等实用功能
 */
class TTDF
{
    use ErrorHandler;

    /** @var float 计时器开始时间 */
    private static $timestart;

    /** @var float 计时器结束时间 */
    private static $timeend;

    /** @var TTDF_ErrorHandler 错误处理器实例 */
    private static $errorHandler;

    private function __construct() {}
    private function __clone() {}
    public function __wakeup() {}

    /**
     * 初始化错误处理器
     */
    private static function initErrorHandler(): void
    {
        if (!self::$errorHandler) {
            self::$errorHandler = TTDF_ErrorHandler::getInstance();
            self::$errorHandler->init();
        }
    }

    /**
     * 计时器开始
     * @return bool
     */
    public static function TimerStart(): bool
    {
        try {
            self::initErrorHandler();
            self::$timestart = microtime(true);
            return true;
        } catch (Exception $e) {
            self::$errorHandler->error('计时器启动失败', [], $e);
            return false;
        }
    }

    /**
     * 计时器结束
     * @param bool $display 是否直接输出
     * @param int $precision 精度
     * @return string
     */
    public static function TimerStop(bool $display = false, int $precision = 3): string
    {
        try {
            self::initErrorHandler();

            if (self::$timestart === null) {
                self::$errorHandler->warning('计时器未启动，无法停止');
                return '0.000 ms';
            }

            self::$timeend = microtime(true);
            $timetotal = self::$timeend - self::$timestart;

            // 格式化时间显示
            if ($timetotal < 1) {
                $result = number_format($timetotal * 1000, $precision) . " ms";
            } else {
                $result = number_format($timetotal, $precision) . " s";
            }

            if ($display) {
                echo $result;
            }

            return $result;
        } catch (Exception $e) {
            self::$errorHandler->error('计时器停止失败', [], $e);
            return '0.000 ms';
        }
    }
    /**
     * HTML压缩
     * @param string $html HTML内容
     * @param array $options 压缩选项
     * @return string
     */
    public static function CompressHtml(string $html, array $options = []): string
    {
        try {
            self::initErrorHandler();

            // 默认压缩选项
            $defaultOptions = [
                'remove_comments' => true,
                'compress_css' => true,
                'compress_js' => true,
                'compress_attributes' => true,
                'preserve_line_breaks' => false,
                'aggressive_whitespace' => true
            ];
            $options = array_merge($defaultOptions, $options);

            // 不处理 feed 内容
            if (self::isFeedContent($html)) {
                return $html;
            }

            // 空内容直接返回
            if (empty(trim($html))) {
                return $html;
            }

            // 扩展需要保留的标签列表
            $preserveTags = [
                'script' => $options['compress_js'],
                'style' => $options['compress_css'],
                'pre' => false,
                'textarea' => false,
                'code' => false,
                'xmp' => false,
                'svg' => $options['aggressive_whitespace'], // SVG可以进行空白压缩
                'noscript' => false
            ];

            // 构建更精确的正则表达式
            $tagPatterns = [];
            foreach ($preserveTags as $tag => $canCompress) {
                $tagPatterns[] = "<{$tag}(?:\\s[^>]*)?>.*?<\\/{$tag}>";
            }
            $tagPattern = implode('|', $tagPatterns);

            // 分割HTML，保留需要保护的标签内容
            $chunks = preg_split("/({$tagPattern})/msi", $html, -1, PREG_SPLIT_DELIM_CAPTURE);

            if ($chunks === false) {
                self::$errorHandler->warning('HTML压缩正则表达式失败');
                return $html;
            }

            $compressed = '';
            foreach ($chunks as $chunk) {
                if (empty($chunk)) continue;

                // 检查是否为需要特殊处理的标签
                $tagType = self::getTagType($chunk, $preserveTags);

                if ($tagType === 'script' && $options['compress_js']) {
                    $compressed .= self::compressScriptTag($chunk);
                } elseif ($tagType === 'style' && $options['compress_css']) {
                    $compressed .= self::compressStyleTag($chunk);
                } elseif ($tagType === 'svg' && $options['aggressive_whitespace']) {
                    // SVG标签进行特殊的空白压缩
                    $compressed .= self::compressSvgTag($chunk);
                } elseif ($tagType !== false) {
                    // 其他保护标签，保留原始内容
                    $compressed .= $chunk;
                } else {
                    // 压缩普通HTML内容
                    $chunk = self::compressHtmlChunk($chunk, $options);
                    $compressed .= $chunk;
                }
            }

            // 最终清理
            $compressed = self::finalCleanup($compressed, $options);

            return $compressed;
        } catch (Exception $e) {
            self::$errorHandler->error('HTML压缩失败', ['html_length' => strlen($html)], $e);
            return $html;
        }
    }

    /**
     * 压缩HTML片段
     * @param string $chunk HTML片段
     * @param array $options 压缩选项
     * @return string
     */
    private static function compressHtmlChunk(string $chunk, array $options = []): string
    {
        try {
            if (empty(trim($chunk))) {
                return '';
            }

            // 移除HTML注释（保留条件注释和IE注释）
            if ($options['remove_comments']) {
                $chunk = preg_replace('/<!--(?!\[if\s)(?!<!)[^\[>].*?-->/s', '', $chunk);
            }

            // 定义块级元素和内联元素
            $blockElements = 'div|p|h[1-6]|ul|ol|li|dl|dt|dd|table|tr|td|th|thead|tbody|tfoot|form|fieldset|legend|address|blockquote|center|dir|menu|article|aside|details|figcaption|figure|footer|header|main|nav|section|summary';
            $inlineElements = 'a|abbr|acronym|b|bdi|bdo|big|br|button|cite|code|dfn|em|i|img|input|kbd|label|map|mark|meter|noscript|object|output|progress|q|ruby|s|samp|script|select|small|span|strong|sub|sup|textarea|time|tt|u|var|wbr';

            // 智能处理空白字符
            if ($options['aggressive_whitespace']) {
                // 移除块级元素间的空白
                $chunk = preg_replace('/(<\/(?:' . $blockElements . ')>)\s+(<(?:' . $blockElements . ')(?:\s[^>]*)?>)/i', '$1$2', $chunk);

                // 保留内联元素间的必要空格
                $chunk = preg_replace('/(<\/(?:' . $inlineElements . ')>)\s+(<(?:' . $inlineElements . ')(?:\s[^>]*)?>)/i', '$1 $2', $chunk);

                // 合并多个空白字符为单个空格
                $chunk = preg_replace('/\s+/', ' ', $chunk);

                // 移除标签前后的多余空白（但保留内联元素的空格）
                $chunk = preg_replace('/\s*(<(?:' . $blockElements . ')(?:\s[^>]*)?>)\s*/i', '$1', $chunk);
                $chunk = preg_replace('/\s*(<\/(?:' . $blockElements . ')>)\s*/i', '$1', $chunk);
            } else {
                // 温和的空白处理
                $chunk = preg_replace('/\s+/', ' ', $chunk);
                $chunk = preg_replace('/>\s+</', '><', $chunk);
            }

            // 压缩HTML属性
            if ($options['compress_attributes']) {
                $chunk = self::compressAttributes($chunk);
            }

            // 移除行首行尾空白
            $chunk = preg_replace('/^\s+|\s+$/m', '', $chunk);

            // 处理换行符
            if (!$options['preserve_line_breaks']) {
                $chunk = preg_replace('/\n\s*\n/', "\n", $chunk);
                $chunk = str_replace("\n", '', $chunk);
            }

            return trim($chunk);
        } catch (Exception $e) {
            self::$errorHandler->warning('HTML片段压缩失败', ['chunk_length' => strlen($chunk)], $e);
            return $chunk;
        }
    }

    /**
     * 获取标签类型
     * @param string $chunk HTML片段
     * @param array $preserveTags 保护标签列表
     * @return string|false
     */
    private static function getTagType(string $chunk, array $preserveTags)
    {
        foreach ($preserveTags as $tag => $canCompress) {
            if (preg_match("/^<{$tag}(?:\s|>)/i", $chunk)) {
                return $tag;
            }
        }
        return false;
    }

    /**
     * 压缩Script标签内容
     * @param string $chunk Script标签内容
     * @return string
     */
    private static function compressScriptTag(string $chunk): string
    {
        try {
            // 提取script标签的内容
            if (preg_match('/<script([^>]*)>(.*?)<\/script>/is', $chunk, $matches)) {
                $attributes = $matches[1];
                $content = $matches[2];

                // JavaScript压缩
                $content = self::safeCompressJavaScript($content);

                return "<script{$attributes}>{$content}</script>";
            }
            return $chunk;
        } catch (Exception $e) {
            self::$errorHandler->warning('Script标签压缩失败', [], $e);
            return $chunk;
        }
    }

    /**
     * 安全压缩JavaScript代码
     * @param string $content JavaScript内容
     * @return string
     */
    private static function safeCompressJavaScript(string $content): string
    {
        try {
            // 保护字符串字面量和正则表达式
            $protectedStrings = [];
            $stringIndex = 0;

            // 保护双引号字符串
            $content = preg_replace_callback('/"(?:[^"\\\\]|\\\\.)*"/', function($matches) use (&$protectedStrings, &$stringIndex) {
                $placeholder = "___PROTECTED_STRING_{$stringIndex}___";
                $protectedStrings[$placeholder] = $matches[0];
                $stringIndex++;
                return $placeholder;
            }, $content);

            // 保护单引号字符串
            $content = preg_replace_callback("/'(?:[^'\\\\]|\\\\.)*'/", function($matches) use (&$protectedStrings, &$stringIndex) {
                $placeholder = "___PROTECTED_STRING_{$stringIndex}___";
                $protectedStrings[$placeholder] = $matches[0];
                $stringIndex++;
                return $placeholder;
            }, $content);

            // 保护模板字符串
            $content = preg_replace_callback('/`(?:[^`\\\\]|\\\\.)*`/', function($matches) use (&$protectedStrings, &$stringIndex) {
                $placeholder = "___PROTECTED_STRING_{$stringIndex}___";
                $protectedStrings[$placeholder] = $matches[0];
                $stringIndex++;
                return $placeholder;
            }, $content);

            // 保护正则表达式
            $content = preg_replace_callback('/\/(?:[^\/\n\\\\]|\\\\.)+\/[gimuy]*/', function($matches) use (&$protectedStrings, &$stringIndex) {
                $placeholder = "___PROTECTED_STRING_{$stringIndex}___";
                $protectedStrings[$placeholder] = $matches[0];
                $stringIndex++;
                return $placeholder;
            }, $content);

            // 安全移除注释
            // 移除多行注释，但避免在字符串中的情况
            $content = preg_replace('/\/\*(?:[^*]|\*(?!\/))*\*\//', '', $content);
            
            // 移除单行注释，但保护URL中的//
            $content = preg_replace('/(?<!:)\/\/(?![\/\*]).*$/m', '', $content);

            // 智能压缩空白字符
            // 保护JSON结构中的空白
            $content = preg_replace('/\s*([{}[\]:,;])\s*/', '$1', $content);
            
            // 压缩其他多余空白，但保留必要的空格
            $content = preg_replace('/\s+/', ' ', $content);
            
            // 移除行首行尾空白
            $content = preg_replace('/^\s+|\s+$/m', '', $content);
            
            // 移除空行
            $content = preg_replace('/\n\s*\n/', "\n", $content);

            // 恢复被保护的字符串
            foreach ($protectedStrings as $placeholder => $original) {
                $content = str_replace($placeholder, $original, $content);
            }

            return trim($content);
        } catch (Exception $e) {
            self::$errorHandler->warning('JavaScript安全压缩失败', [], $e);
            return $content;
        }
    }

    /**
     * 压缩Style标签内容
     * @param string $chunk Style标签内容
     * @return string
     */
    private static function compressStyleTag(string $chunk): string
    {
        try {
            // 提取style标签的内容
            if (preg_match('/<style([^>]*)>(.*?)<\/style>/is', $chunk, $matches)) {
                $attributes = $matches[1];
                $content = $matches[2];

                // 基础CSS压缩
                $content = preg_replace('/\/\*.*?\*\//s', '', $content); // 移除注释
                $content = preg_replace('/\s+/', ' ', $content); // 合并空白
                $content = preg_replace('/;\s*}/', '}', $content); // 移除最后一个分号
                $content = preg_replace('/\s*{\s*/', '{', $content); // 清理大括号
                $content = preg_replace('/\s*}\s*/', '}', $content);
                $content = preg_replace('/\s*;\s*/', ';', $content); // 清理分号
                $content = preg_replace('/\s*:\s*/', ':', $content); // 清理冒号
                $content = trim($content);

                return "<style{$attributes}>{$content}</style>";
            }
            return $chunk;
        } catch (Exception $e) {
            self::$errorHandler->warning('Style标签压缩失败', [], $e);
            return $chunk;
        }
    }

    /**
     * 压缩HTML属性
     * @param string $html HTML内容
     * @return string
     */
    private static function compressAttributes(string $html): string
    {
        try {
            // 移除不必要的引号（单个单词属性值）
            $html = preg_replace('/(\s+\w+)=(["\'])([a-zA-Z0-9_-]+)\2/', '$1=$3', $html);

            // 移除空属性值
            $html = preg_replace('/\s+\w+=["\']["\']/', '', $html);

            // 压缩多个空格为单个空格
            $html = preg_replace('/\s+/', ' ', $html);

            // 移除标签内的首尾空格
            $html = preg_replace('/(<[^>]+)\s+>/', '$1>', $html);
            $html = preg_replace('/<\s+([^>]+>)/', '<$1', $html);

            return $html;
        } catch (Exception $e) {
            self::$errorHandler->warning('属性压缩失败', [], $e);
            return $html;
        }
    }

    /**
     * 压缩SVG标签内容
     * @param string $chunk SVG标签内容
     * @return string
     */
    private static function compressSvgTag(string $chunk): string
    {
        try {
            // 提取SVG标签的内容
            if (preg_match('/<svg([^>]*)>(.*?)<\/svg>/is', $chunk, $matches)) {
                $attributes = $matches[1];
                $content = $matches[2];

                // SVG特殊压缩处理
                // 移除SVG注释
                $content = preg_replace('/<!--.*?-->/s', '', $content);

                // 移除多余的空白字符，但保留必要的空格
                $content = preg_replace('/\s+/', ' ', $content);

                // 移除标签间的空白
                $content = preg_replace('/>\s+</', '><', $content);

                // 移除标签前后的空白
                $content = preg_replace('/\s*(<[^>]+>)\s*/', '$1', $content);

                // 压缩SVG属性中的空白
                $content = preg_replace('/\s*=\s*/', '=', $content);
                $content = preg_replace('/\s*,\s*/', ',', $content);

                $content = trim($content);

                return "<svg{$attributes}>{$content}</svg>";
            }
            return $chunk;
        } catch (Exception $e) {
            self::$errorHandler->warning('SVG标签压缩失败', [], $e);
            return $chunk;
        }
    }

    /**
     * 最终清理
     * @param string $html HTML内容
     * @param array $options 选项
     * @return string
     */
    private static function finalCleanup(string $html, array $options): string
    {
        try {
            // 移除多余的空白行
            $html = preg_replace('/\n\s*\n/', "\n", $html);

            // 移除首尾空白
            $html = trim($html);

            // 如果不保留换行符，则全部移除
            if (!$options['preserve_line_breaks']) {
                $html = str_replace(["\r\n", "\r", "\n"], '', $html);
            }

            return $html;
        } catch (Exception $e) {
            self::$errorHandler->warning('最终清理失败', [], $e);
            return $html;
        }
    }

    /**
     * 检查是否为 Feed 内容
     * @param string $content 内容
     * @return bool
     */
    private static function isFeedContent($content)
    {
        // 检查是否包含典型的 feed 标识
        if (
            strpos($content, '<feed') === 0 ||
            strpos($content, '<?xml') === 0 ||
            (strpos($content, '<rss') !== false && strpos($content, '<rss') < 10) ||
            (strpos($content, '<rdf:RDF') !== false && strpos($content, '<rdf:RDF') < 10)
        ) {
            return true;
        }

        // 检查全局路由信息是否为 feed
        if (
            isset($GLOBALS['_ttdf_route']) &&
            isset($GLOBALS['_ttdf_route']['path']) &&
            strpos($GLOBALS['_ttdf_route']['path'], 'feed') === 0
        ) {
            return true;
        }

        return false;
    }

    /**
     * HelloWorld
     * @param bool $echo 是否输出
     */
    public static function HelloWorld(?bool $echo = true)
    {
        if ($echo) echo '您已成功安装开发框架！<br>这是显示在index.php中调用的默认内容。';
        return '您已成功安装开发框架！<br>这是显示在index.php中调用的默认内容。';
    }

    /**
     * 获取PHP版本
     * @param bool $echo 是否输出
     * @return string
     */
    public static function PHPVer(?bool $echo = true)
    {
        try {
            $PHPVer = PHP_VERSION;
            if ($echo) echo $PHPVer;
            return $PHPVer;
        } catch (Exception $e) {
            return self::handleError('获取PHP版本失败', $e, 'Unknown');
        }
    }

    /**
     * 获取框架版本
     * @param bool|null $echo 是否输出
     * @return string|null 
     * @throws Exception
     */
    public static function Ver(?bool $echo = true)
    {
        try {
            $FrameworkVer = __FRAMEWORK_VER__;
            if ($echo) echo $FrameworkVer;
            return $FrameworkVer;
        } catch (Exception $e) {
            return self::handleError('获取框架版本失败', $e, 'Unknown');
        }
    }

    /**
     * 获取 typecho 版本
     * @param bool|null $echo 是否输出
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
            return self::handleError('获取Typecho版本失败', $e, 'Unknown');
        }
    }

    /**
     * 引入函数库
     * @param string $TTDF
     */
    public static function Modules($TTDF)
    {
        require_once __DIR__ . '/../Modules/' .  $TTDF . '.php';
    }
}

class TTDF_Widget
{
    use ErrorHandler;

    /** @var TTDF_ErrorHandler 错误处理器实例 */
    protected static $errorHandler;

    private function __construct()
    {
        // 初始化错误处理器
        if (!self::$errorHandler) {
            self::$errorHandler = TTDF_ErrorHandler::getInstance();
        }
    }
    private function __clone() {}
    public function __wakeup() {}

    public static function TimerStop(?bool $echo = true)
    {
        try {
            if ($echo) echo TTDF::TimerStop();
            ob_start();
            echo TTDF::TimerStop();
            $content = ob_get_clean();
            return $content;
        } catch (Exception $e) {
            return self::handleError('获取加载时间失败', $e, 'Unknown');
        }
    }

    private static function getArchiveInstance()
    {
        return \Widget\Archive::alloc();
    }

    /**
     * 获取客户端真实IP
     * @return string
     */
    public static function GetClientIp()
    {
        // 可能的IP来源数组
        $sources = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'REMOTE_ADDR'
        ];

        foreach ($sources as $source) {
            if (!empty($_SERVER[$source])) {
                $ip = $_SERVER[$source];

                // 处理X-Forwarded-For可能有多个IP的情况
                if ($source === 'HTTP_X_FORWARDED_FOR') {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }

                // 验证IP格式
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    // 将IPv6本地回环地址转换为IPv4格式
                    if ($ip === '::1') {
                        return '127.0.0.1';
                    }
                    return $ip;
                }
            }
        }

        // 所有来源都找不到有效IP时返回默认值
        return null;
    }

    /**
     * HeadMeta
     * @return string
     */
    public static function HeadMeta($HeadSeo = true)
    {
?>
<meta charset="<?php Get::Options('charset', true) ?>">
    <meta name="renderer" content="webkit" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, shrink-to-fit=no" />
    <?php if ($HeadSeo) {
        TTDF::Modules('UseSeo');
    } ?>
    <meta name="generator" content="Typecho <?php TTDF::TypechoVer(true) ?>" />
    <meta name="framework" content="TTDF <?php TTDF::Ver(true) ?>" />
    <meta name="template" content="<?php GetTheme::Name(true) ?>" />
<?php
    Get::Header(true, 'description,keywords,generator,template,pingback,EditURI,wlwmanifest,alternate,twitter:domain,twitter:card,twitter:description,twitter:title,og:url,og:site_name,og:type');
?>
    <link rel="canonical" href="<?php Get::PageUrl(true, false, null, true); ?>" />
    <link rel="pingback" href="<?php Get::Options('xmlRpcUrl', true) ?>" />
    <link rel="EditURI" type="application/rsd+xml" title="RSD" href="<?php Get::Options('xmlRpcUrl', true) ?>?rsd" />
    <link rel="wlwmanifest" type="application/wlwmanifest+xml" href="<?php Get::Options('xmlRpcUrl', true) ?>?wlw" />
    <link rel="alternate" type="application/rss+xml" title="<?php Get::SiteName(true) ?> &raquo; RSS 2.0" href="<?php echo \Widget\Archive::alloc()->getArchiveFeedUrl(); ?>" />
    <link rel="alternate" type="application/rdf+xml" title="<?php Get::SiteName(true) ?> &raquo; RSS 1.0" href="<?php echo \Widget\Archive::alloc()->getArchiveFeedRssUrl(); ?>" />
    <link rel="alternate" type="application/atom+xml" title="<?php Get::SiteName(true) ?> &raquo; ATOM 1.0" href="<?php echo \Widget\Archive::alloc()->getArchiveFeedAtomUrl(); ?>" />
    <?php
    }
}

// 初始化计时器
TTDF::TimerStart();

/**
 * 默认钩子
 * 添加头部元信息
 */
TTDF_Hook::add_action('load_head', function ($skipHead = false) {
    TTDF_Widget::HeadMeta();
});
TTDF_Hook::add_action('load_foot', function () {
    Get::Footer(true);
?>
<script type="text/javascript">
        window.frameWorkConfig = {
            TyAjax: <?php echo json_encode(TTDF_ConfigManager::get('plugins.tyajax.enabled', false)) ?>,
            RESTAPI: {
                enabled: <?php echo json_encode(TTDF_ConfigManager::get('modules.restapi.enabled', false)) ?>,
                route: '<?php echo TTDF_ConfigManager::get('modules.restapi.route', 'ty-json') ?>',
            },
            version: {
                theme: '<?php GetTheme::Ver(true) ?>',
                typecho: '<?php TTDF::TypechoVer(true) ?>',
                framework: '<?php TTDF::Ver(true) ?>',
            },
            loadtime: '<?php echo htmlspecialchars(TTDF_Widget::TimerStop(false)); ?>',
        }
        console.log("\n %c %s \n", "color: #fff; background: #34495e; padding:5px 0;", "TTDF v" + window.frameWorkConfig.version.framework);
        console.log('页面加载耗时 ' + window.frameWorkConfig.loadtime);
    </script>
<?php
});
