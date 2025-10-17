<?php

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

require_once 'Enums.php';
require_once 'DebugLogger.php';

/**
 * 封装 HTTP 请求信息，与超全局变量解耦。
 */
class ApiRequest
{
    public string $path;
    public array $pathParts;
    public ContentFormat $contentFormat;
    public int $pageSize;
    public int $currentPage;
    public int $excerptLength;

    public function __construct()
    {
        // 初始化调试记录器（仅在调试模式下）
        if (defined('__DEBUG__') && __DEBUG__) {
            TTDF_DebugLogger::init();
            TTDF_DebugLogger::logApiProcess('APIREQUEST_CONSTRUCT_START');
        }

        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';

        $basePath = '/' . ltrim(__TTDF_RESTAPI_ROUTE__ ?? '', '/');

        $this->path = str_starts_with($requestUri, $basePath)
            ? (substr($requestUri, strlen($basePath)) ?: '/')
            : '/';

        $this->pathParts = array_values(array_filter(explode('/', trim($this->path, '/'))));

        $this->contentFormat = ContentFormat::tryFrom(strtolower($this->getQuery('format', 'html'))) ?? ContentFormat::HTML;

        $this->pageSize = max(1, min((int)$this->getQuery('pageSize', 10), 100));
        $this->currentPage = max(1, (int)$this->getQuery('page', 1));
        $this->excerptLength = max(0, (int)$this->getQuery('excerptLength', 200));

        if (defined('__DEBUG__') && __DEBUG__) {
            TTDF_DebugLogger::logApiProcess('APIREQUEST_CONSTRUCT_END', [
                'path' => $this->path,
                'pageSize' => $this->pageSize,
                'currentPage' => $this->currentPage,
                'excerptLength' => $this->excerptLength
            ]);
        }
    }

    public function getQuery(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }
}

/**
 * 专门负责发送 JSON 响应
 */
final class ApiResponse
{
    public function __construct(private ContentFormat $contentFormat)
    {
        if (defined('__DEBUG__') && __DEBUG__) {
            TTDF_DebugLogger::logApiProcess('APIRESPONSE_CONSTRUCT', ['format' => $contentFormat->value]);
        }
    }

    public function send(array $data = [], HttpCode $code = HttpCode::OK): never
    {
        if (defined('__DEBUG__') && __DEBUG__) {
            TTDF_DebugLogger::logApiProcess('APIRESPONSE_SEND_START', [
                'code' => $code->value,
                'has_data' => !empty($data)
            ]);
        }

        try {
            if (!headers_sent()) {
                \Typecho\Response::getInstance()->setStatus($code->value);
                header('Content-Type: application/json; charset=UTF-8');
                $this->setSecurityHeaders();
            }

            $response = [
                'code' => $code->value,
                'message' => $code === HttpCode::OK ? 'success' : ($data['message'] ?? 'Error'),
                'data' => $data['data'] ?? null,
                'meta' => [
                    'format' => $this->contentFormat->value,
                    'timestamp' => time(),
                    ...($data['meta'] ?? [])
                ]
            ];

            $options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;
            if (defined('__DEBUG__') && __DEBUG__) {
                $options |= JSON_PRETTY_PRINT;
            }

            if (defined('__DEBUG__') && __DEBUG__) {
                TTDF_DebugLogger::logApiProcess('SENDING_RESPONSE', [
                    'response_size' => strlen(json_encode($response, $options))
                ]);
            }

            echo json_encode($response, $options);

            if (defined('__DEBUG__') && __DEBUG__) {
                TTDF_DebugLogger::logApiProcess('RESPONSE_SENT');
            }
            exit;
        } catch (Throwable $e) {
            if (defined('__DEBUG__') && __DEBUG__) {
                TTDF_DebugLogger::logApiError('Error in ApiResponse::send', $e);
            }
            throw $e;
        }
    }

    public function error(string $message, HttpCode $code, ?Throwable $e = null): never
    {
        if (defined('__DEBUG__') && __DEBUG__) {
            TTDF_DebugLogger::logApiProcess('APIRESPONSE_ERROR', [
                'message' => $message,
                'code' => $code->value,
                'has_exception' => $e !== null
            ]);
        }

        $response = ['message' => $message];
        if ($e !== null && (defined('__DEBUG__') && __DEBUG__)) {
            $response['error_details'] = [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ];
        }
        $this->send($response, $code);
    }

    private function setSecurityHeaders(): void
    {
        if (defined('__DEBUG__') && __DEBUG__) {
            TTDF_DebugLogger::logApiProcess('SETTING_SECURITY_HEADERS');
        }

        try {
            $headers = $GLOBALS['TTDF_CONFIG']['REST_API']['HEADERS'] ?? [];

            // 动态设置允许的来源
            $requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
            $allowedOrigins = [$requestOrigin, $_SERVER['HTTP_HOST'] ?? ''];
            $headers['Access-Control-Allow-Origin'] = in_array($requestOrigin, $allowedOrigins, true)
                ? $requestOrigin
                : ($allowedOrigins[1] ?? '*');

            // 添加必要的CORS头
            $headers['Access-Control-Allow-Headers'] = 'Content-Type, Authorization, X-Requested-With';
            $headers['Access-Control-Allow-Methods'] = 'GET, POST, OPTIONS';
            $headers['Access-Control-Allow-Credentials'] = 'true';

            // 防止缓存
            $headers['Cache-Control'] = 'no-cache, no-store, must-revalidate';
            $headers['Pragma'] = 'no-cache';
            $headers['Expires'] = '0';

            foreach ($headers as $name => $value) {
                if (!headers_sent() && $value !== null) {
                    header("$name: $value");
                }
            }

            if (defined('__DEBUG__') && __DEBUG__) {
                TTDF_DebugLogger::logApiProcess('SECURITY_HEADERS_SET');
            }
        } catch (Throwable $e) {
            if (defined('__DEBUG__') && __DEBUG__) {
                TTDF_DebugLogger::logApiError('Error in setSecurityHeaders', $e);
            }
        }
    }
}

/**
 * 专门负责格式化数据
 */
final class ApiFormatter
{
    public function __construct(
        private readonly TTDF_Db_API $dbApi,
        private readonly ContentFormat $contentFormat,
        private readonly int $excerptLength
    ) {}

    public function formatPost(array $post): array
    {
        $formattedPost = [
            'cid' => (int)($post['cid'] ?? 0),
            'title' => $post['title'] ?? '',
            'slug' => $post['slug'] ?? '',
            'type' => $post['type'] ?? 'post',
            'created' => date('c', $post['created'] ?? time()),
            'modified' => date('c', $post['modified'] ?? time()),
            'commentsNum' => (int)($post['commentsNum'] ?? 0),
            'authorId' => (int)($post['authorId'] ?? 0),
            'status' => $post['status'] ?? 'publish',
            'contentType' => $this->contentFormat->value,
            'fields' => $this->dbApi->getPostFields($post['cid'] ?? 0),
            'content' => $this->formatContent($post['text'] ?? ''),
            'excerpt' => $this->generatePlainExcerpt($post['text'] ?? '', $this->excerptLength),
        ];

        if ($formattedPost['type'] === 'post') {
            $formattedPost['categories'] = array_map(
                [$this, 'formatCategory'],
                $this->dbApi->getPostCategories($post['cid'] ?? 0)
            );
            $formattedPost['tags'] = array_map(
                [$this, 'formatTag'],
                $this->dbApi->getPostTags($post['cid'] ?? 0)
            );
        }
        return $formattedPost;
    }

    public function formatCategory(array $category): array
    {
        $category['description'] = $this->formatContent($category['description'] ?? '');
        return $category;
    }

    public function formatTag(array $tag): array
    {
        $tag['description'] = $this->formatContent($tag['description'] ?? '');
        return $tag;
    }

    public function formatComment(array $comment): array
    {
        return [
            'coid' => (int)($comment['coid'] ?? 0),
            'cid' => (int)($comment['cid'] ?? 0),
            'author' => $comment['author'] ?? '',
            'mail' => md5($comment['mail'] ?? ''),
            'url' => $comment['url'] ?? '',
            // 'ip' => $comment['ip'] ?? '',
            'created' => date('c', $comment['created'] ?? time()),
            'modified' => date('c', $comment['modified'] ?? time()),
            'text' => $this->formatContent($comment['text'] ?? ''),
            'status' => $comment['status'] ?? 'approved',
            'parent' => (int)($comment['parent'] ?? 0),
            'authorId' => (int)($comment['authorId'] ?? 0)
        ];
    }

    public function formatAttachment(array $attachment): array
    {
        return [
            'cid' => (int)($attachment['cid'] ?? 0),
            'title' => $attachment['title'] ?? '',
            'type' => $attachment['type'] ?? '',
            'size' => (int)($attachment['size'] ?? 0),
            'created' => date('c', $attachment['created'] ?? time()),
            'modified' => date('c', $attachment['modified'] ?? time()),
            'status' => $attachment['status'] ?? 'publish',
        ];
    }

    private function formatContent(string $content): string
    {
        if ($this->contentFormat === ContentFormat::MARKDOWN) {
            return $content;
        }
        if (!class_exists('Markdown')) {
            require_once __TYPECHO_ROOT_DIR__ . '/var/Typecho/Common/Markdown.php';
        }
        return Markdown::convert(preg_replace('/<!--.*?-->/s', '', $content));
    }

    private function generatePlainExcerpt(string $content, int $length): string
    {
        if ($length <= 0) {
            return '';
        }
        // 移除HTML和Markdown
        $text = strip_tags($content);
        $text = preg_replace([
            '/```.*?```/s',
            '/~~~.*?~~~/s',
            '/\[[^\]]*\]\([^\)]*\)/', // 修复链接正则表达式
            '/!\[[^\]]*\]\([^\)]*\)/', // 修复图片正则表达式
            '/\[([^\]]*)\]\([^\)]*\)/', // 修复链接文本正则表达式
            '/^#{1,6}\s*/m',
            '/[\*\_]{1,3}/',
            '/^\s*>\s*/m',
            '/\s+/'
        ], ' ', $text);
        $text = trim($text ?? ''); // 修复可能为null的问题

        if (mb_strlen($text) > $length) {
            $text = mb_substr($text, 0, $length);
            // 避免截断在单词中间
            if (preg_match('/^(.*)\s\S*$/u', $text, $matches)) {
                $text = $matches[1];
            }
        }
        return $text;
    }
}
