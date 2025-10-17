<?php

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

require_once 'Enums.php';
require_once 'Core.php';
require_once 'Controllers.php';

final class TTDF_API
{
    private ApiRequest $request;
    private ApiResponse $response;
    private TTDF_Db_API $db;
    private ApiFormatter $formatter;

    public function __construct(
        ApiRequest $request,
        ApiResponse $response,
        TTDF_Db_API $db,
        ApiFormatter $formatter
    ) {
        $this->request = $request;
        $this->response = $response;
        $this->db = $db;
        $this->formatter = $formatter;
    }

    private function handleNotFound(string $endpoint): never
    {
        if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
            TTDF_Debug::logApiProcess('ENDPOINT_NOT_FOUND', ['endpoint' => $endpoint]);
        }
        $this->response->error('Endpoint not found', HttpCode::NOT_FOUND);
    }

    public function handleRequest(): never
    {
        if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
            TTDF_Debug::logApiProcess('HANDLE_REQUEST_START');
        }

        try {
            // 处理OPTIONS预检请求
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
                if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
                    TTDF_Debug::logApiProcess('HANDLING_OPTIONS_REQUEST');
                }
                $this->response->send([], HttpCode::OK);
            }

            // 允许 GET 和 POST 方法
            if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'POST'])) {
                if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
                    TTDF_Debug::logApiProcess('METHOD_NOT_ALLOWED', [
                        'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'
                    ]);
                }
                $this->response->error('Method Not Allowed', HttpCode::METHOD_NOT_ALLOWED);
            }

            $endpoint = $this->request->pathParts[0] ?? '';
            if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
                TTDF_Debug::logApiProcess('DETERMINING_ENDPOINT', ['endpoint' => $endpoint]);
            }

            // 检查限制逻辑
            $this->checkRestrictions($endpoint);

            $data = match ($endpoint) {
                '' => $this->handleIndex(),
                'index' => $this->handleIndex(),
                'posts' => $this->handlePostList(),
                'pages' => $this->handlePageList(),
                'content' => $this->handlePostContent(),
                'category' => $this->handleCategory(),
                'tag' => $this->handleTag(),
                'search' => $this->handleSearch(),
                'options' => $this->handleOptions(),
                'fields' => $this->handleFieldSearch(),
                'advancedFields' => $this->handleAdvancedFieldSearch(),
                'comments' => ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
                    ? $this->handlePostComment()
                    : $this->handleComments(),
                'attachments' => $this->handleAttachmentList(),
                'ttdf' => $this->handleTtdf(),
                default => $this->handleNotFound($endpoint),
            };

            if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
                TTDF_Debug::logApiProcess('SENDING_RESPONSE_DATA', [
                    'endpoint' => $endpoint,
                    'data_size' => strlen(json_encode($data, JSON_UNESCAPED_UNICODE))
                ]);
            }
            $this->response->send($data);
        } catch (Throwable $e) {
            if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
                TTDF_Debug::logApiError('Error in handleRequest', $e);
            }
            $this->response->error('Internal Server Error', HttpCode::INTERNAL_ERROR, $e);
        }
    }

    private function checkRestrictions(string $endpoint): void
    {
        $limitConfig = TTDF_CONFIG['REST_API']['LIMIT'] ?? [];

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // 检查GET请求限制
        if ($method === 'GET' && !empty($limitConfig['GET'])) {
            $restrictedEndpoints = explode(',', $limitConfig['GET']);
            if (in_array($endpoint, $restrictedEndpoints)) {
                if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
                    TTDF_Debug::logApiProcess('ACCESS_FORBIDDEN', [
                        'endpoint' => $endpoint,
                        'method' => $method,
                        'reason' => 'GET request forbidden'
                    ]);
                }
                $this->response->error('Access Forbidden', HttpCode::FORBIDDEN);
            }
        }

        // 检查POST请求限制
        if ($method === 'POST' && !empty($limitConfig['POST'])) {
            $restrictedEndpoints = explode(',', $limitConfig['POST']);
            if (in_array($endpoint, $restrictedEndpoints)) {
                if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
                    TTDF_Debug::logApiProcess('ACCESS_FORBIDDEN', [
                        'endpoint' => $endpoint,
                        'method' => $method,
                        'reason' => 'POST request forbidden'
                    ]);
                }
                $this->response->error('Access Forbidden', HttpCode::FORBIDDEN);
            }
        }
    }

    private function handleIndex(): array
    {
        $controller = new IndexController($this->request, $this->response, $this->db, $this->formatter);
        return $controller->handle();
    }

    private function handlePostList(): array
    {
        $controller = new PostController($this->request, $this->response, $this->db, $this->formatter);
        return $controller->handleList();
    }

    private function handlePageList(): array
    {
        $controller = new PageController($this->request, $this->response, $this->db, $this->formatter);
        return $controller->handleList();
    }

    private function handlePostContent(): array
    {
        $controller = new PostController($this->request, $this->response, $this->db, $this->formatter);
        return $controller->handleContent();
    }

    private function handleCategory(): array
    {
        $controller = new CategoryController($this->request, $this->response, $this->db, $this->formatter);
        return $controller->handle();
    }

    private function handleTag(): array
    {
        $controller = new TagController($this->request, $this->response, $this->db, $this->formatter);
        return $controller->handle();
    }

    private function handleSearch(): array
    {
        $controller = new SearchController($this->request, $this->response, $this->db, $this->formatter);
        return $controller->handle();
    }

    private function handleOptions(): array
    {
        $controller = new OptionController($this->request, $this->response, $this->db, $this->formatter);
        return $controller->handleOptions();
    }

    private function handleFieldSearch(): array
    {
        $controller = new FieldController($this->request, $this->response, $this->db, $this->formatter);
        return $controller->handleFieldSearch();
    }

    private function handleAdvancedFieldSearch(): array
    {
        $controller = new FieldController($this->request, $this->response, $this->db, $this->formatter);
        return $controller->handleAdvancedFieldSearch();
    }

    private function handleComments(): array
    {
        $controller = new CommentController($this->request, $this->response, $this->db, $this->formatter);
        return $controller->handle();
    }

    private function handlePostComment(): array
    {
        $controller = new CommentController($this->request, $this->response, $this->db, $this->formatter);
        return $controller->handlePostComment();
    }

    private function handleAttachmentList(): array
    {
        $controller = new AttachmentController($this->request, $this->response, $this->db, $this->formatter);
        return $controller->handleList();
    }

    private function handleTtdf(): array
    {
        $controller = new TTDFController($this->request, $this->response, $this->db, $this->formatter);
        return $controller->handle();
    }
}
